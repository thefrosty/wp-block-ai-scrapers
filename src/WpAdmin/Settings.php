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
use function human_time_diff;
use function is_string;
use function method_exists;
use function parse_url;
use function sprintf;
use function str_contains;
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

    final public const ACTION = 'wpBlockAiScrapersCode';

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
        $this->addAction('wp_ajax_' . self::ACTION, [$this, 'ajax']);
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
        if ($file !== $this->getPlugin()->getBasename()) {
            return $meta;
        }

        $visitors = new DarkVisitors();
        $timeout = !method_exists($visitors, 'getTransientTimeout') ? null :
            $visitors->getTransientTimeout($visitors->getKey());
        if ($timeout) {
            $title = 'Refresh';
            $meta[] = sprintf(
                'Cache expires in <strong><time datetime="%2$s" title="%2$s">%1$s</time></strong>',
                human_time_diff($timeout),
                date('Y-m-d H:i:s', $timeout)
            );
        }
        $meta[] = sprintf(
            '%s cache',
            $title ?? 'Update',
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
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $query = parse_url($this->getRequest()->server->get('REQUEST_URI', ''), PHP_URL_QUERY);
        $class = is_string($query) && str_contains($plugin_file, $query) ? '' : 'hidden';

        $view = $this->getView(ServiceProvider::WP_UTILITIES_VIEW);
        $view->render('plugins/after-plugin-row', [
            'query' => $query,
            'class' => $class,
        ]);
    }

    /**
     * AJAX callback.
     * @return void
     */
    protected function ajax(): void
    {
        $request = $this->getRequest()->request;
        if (
            !$request->has('action') || $request->get('action') !== self::ACTION ||
            !check_ajax_referer(self::ACTION, 'nonce', false)
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
}
