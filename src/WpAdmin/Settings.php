<?php

declare(strict_types=1);

namespace TheFrosty\WpBlockAiScrapers\WpAdmin;

use TheFrosty\WpBlockAiScrapers\Api\File;
use TheFrosty\WpBlockAiScrapers\Api\Htaccess;
use TheFrosty\WpBlockAiScrapers\Api\Nginx;
use TheFrosty\WpBlockAiScrapers\Api\RobotsTxt;
use TheFrosty\WpBlockAiScrapers\Http\DarkVisitors;
use TheFrosty\WpBlockAiScrapers\ServiceProvider;
use TheFrosty\WpUtilities\Plugin\AbstractContainerProvider;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestInterface;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestTrait;
use TheFrosty\WpUtilities\Utils\Viewable;
use WP_Error;
use function add_management_page;
use function array_unshift;
use function check_ajax_referer;
use function current_user_can;
use function date;
use function esc_attr__;
use function esc_html__;
use function explode;
use function human_time_diff;
use function is_string;
use function method_exists;
use function parse_url;
use function remove_query_arg;
use function sprintf;
use function str_contains;
use function str_replace;
use function wp_send_json_error;
use function wp_send_json_success;
use const PHP_URL_QUERY;

/**
 * Class Settings
 * @package TheFrosty\WpBlockAiScrapers\WpAdmin
 */
class Settings extends AbstractContainerProvider implements HttpFoundationRequestInterface
{

    use HttpFoundationRequestTrait, Viewable;

    final public const ACTION_RETRIEVE = 'wpBlockAiScrapersCode';
    final public const ACTION_CACHE = 'wpBlockAiScrapersCache';

    /**
     * Add class hooks.
     * @return void
     */
    public function addHooks(): void
    {
        $this->addAction('admin_menu', [$this, 'adminMenu']);
        $this->addFilter('plugin_action_links_' . $this->getPlugin()->getBasename(), [$this, 'pluginActionLinks']);
        $this->addFilter('plugin_row_meta', [$this, 'pluginRowMeta'], 10, 2);
        $this->addFilter('after_plugin_row_' . $this->getPlugin()->getBasename(), [$this, 'afterPluginRow']);
        $this->addAction('wp_ajax_' . self::ACTION_RETRIEVE, [$this, 'ajaxRetrieveContent']);
        $this->addAction('admin_post_' . self::ACTION_CACHE, [$this, 'maybeRefreshCache']);
    }

    /**
     * Add management page.
     * @return void
     */
    protected function adminMenu(): void
    {
        add_management_page(
            esc_html__('Block AI Scrapers', 'wp-block-ai-scrapers'),
            esc_html__('Block AI Scrapers', 'wp-block-ai-scrapers'),
            'activate_plugins',
            sprintf('plugins.php?%s', $this->getPlugin()->getSlug()),
        );
    }

    /**
     * Add settings page link to the plugins page.
     * @param array $actions
     * @return array
     */
    protected function pluginActionLinks(array $actions): array
    {
        if (!$this->currentUserHasAccess()) {
            return $actions;
        }

        array_unshift(
            $actions,
            sprintf(
                '<a href="javascript:;" 
onclick="document.getElementById(\'block-ai-scrapers\').classList.toggle(\'hidden\')" title="%1$s">%2$s</a>',
                esc_attr__('Show the plugin settings', 'wp-block-ai-scrapers'),
                esc_html__('Settings', 'wp-block-ai-scrapers')
            )
        );

        return $actions;
    }

    /**
     * Add settings page link to the plugins page.
     * @param array $meta
     * @param string $file
     * @return array
     */
    protected function pluginRowMeta(array $meta, string $file): array
    {
        if ($file !== $this->getPlugin()->getBasename() || !$this->currentUserHasAccess()) {
            return $meta;
        }

        $visitors = new DarkVisitors();
        $timeout = !method_exists($visitors, 'getTransientTimeout') ? null :
            $visitors->getTransientTimeout($visitors->getKey());
        if ($timeout) {
            $title = esc_html__('Refresh cache', 'wp-block-ai-scrapers');
            $meta[] = sprintf(
                esc_html__('Cache expires in %s', 'wp-block-ai-scrapers'),
                sprintf(
                    '<strong><time datetime="%2$s" title="%2$s">%1$s</time></strong>',
                    human_time_diff($timeout),
                    date('Y-m-d H:i:s', $timeout)
                )
            );
        }
        $meta[] = sprintf(
            '<a href="%1$s">%2$s</a>',
            wp_nonce_url(
                add_query_arg(
                    [
                        'action' => self::ACTION_CACHE,
                        'force' => isset($title) ? 'refresh' : 'update',
                    ],
                    admin_url('admin-post.php')
                ),
                self::ACTION_CACHE,
            ),
            $title ?? esc_html__('Update cache', 'wp-block-ai-scrapers')
        );

        return $meta;
    }

    /**
     * Add Settings HTML after plugin row.
     * @param string $plugin_file
     * @return void
     */
    protected function afterPluginRow(string $plugin_file): void
    {
        if (!$this->currentUserHasAccess()) {
            return;
        }

        $plugin = str_replace('wp-', '', explode('/', $plugin_file)[0]);
        $query = parse_url($this->getRequest()->server->get('REQUEST_URI', ''), PHP_URL_QUERY);
        $class = is_string($query) && str_contains($query, $plugin) ? '' : 'hidden';

        $view = $this->getView(ServiceProvider::WP_UTILITIES_VIEW);
        $view->render('plugins/after-plugin-row', [
            'query' => $query,
            'class' => $class,
        ]);
    }

    /**
     * AJAX callback to retrieve the contents.
     * @return void
     */
    protected function ajaxRetrieveContent(): void
    {
        $request = $this->getRequest()->request;
        if (
            !$this->currentUserHasAccess() ||
            !$request->has('action') || $request->get('action') !== self::ACTION_RETRIEVE ||
            !check_ajax_referer(self::ACTION_RETRIEVE, 'nonce', false)
        ) {
            wp_send_json_error(new WP_Error('bad_request', 'Bad request, or nonce.'));
        }

        $enum = !is_string($request->get('file')) ? null : File::tryFrom($request->get('file'));
        if ($enum) {
            $class = match ($enum) {
                File::_HTACCESS => new Htaccess(),
                File::NGINX => new Nginx(),
                File::ROBOTS_TXT => new RobotsTxt(),
            };
            wp_send_json_success($class->retrieveContent());
        }

        wp_send_json_error();
    }

    /**
     * Maybe refresh the transient cache.
     * @return never
     */
    protected function maybeRefreshCache(): never
    {
        $query = $this->getRequest()->query;
        $referer = !wp_get_referer() ? admin_url('plugins.php') : remove_query_arg('success', wp_get_referer());
        if (
            !$this->currentUserHasAccess() ||
            !$query->has('action') || $query->get('action') !== self::ACTION_CACHE ||
            !$query->has('force') ||
            !$query->has('_wpnonce') ||
            !wp_verify_nonce($query->get('_wpnonce'), self::ACTION_CACHE)
        ) {
            wp_safe_redirect(add_query_arg('success', 'false', $referer));
            exit;
        }

        $force = $query->get('force') === 'refresh';
        (new DarkVisitors())->updateCache($force);

        wp_safe_redirect($referer);
        exit;
    }

    /**
     * Can the current user `activate_plugins`?
     * @return bool
     */
    private function currentUserHasAccess(): bool
    {
        return current_user_can('activate_plugins');
    }
}
