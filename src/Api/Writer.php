<?php

declare(strict_types=1);

namespace TheFrosty\WpBlockAiScrapers\Api;

use Exception;
use TheFrosty\WpBlockAiScrapers\Http\DarkVisitors;
use TheFrosty\WpUtilities\Plugin\AbstractHookProvider;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestInterface;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestTrait;
use function add_query_arg;
use function admin_url;
use function array_column;
use function esc_url;
use function in_array;
use function wp_nonce_url;
use function wp_verify_nonce;

/**
 * Class Writer
 * @package TheFrosty\WpBlockAiScrapers\Api
 */
abstract class Writer extends AbstractHookProvider implements HttpFoundationRequestInterface
{

    use Filesystem, HttpFoundationRequestTrait;

    final public const ACTION = 'write_ai_scrapers';
    final protected const NONCE_ACTION = 'wpBlockAiScrapers';
    final protected const NONCE_KEY = '_wpAiNonce';

    /**
     * Add class hooks.
     * @return void
     */
    public function addHooks(): void
    {
        $this->addAction('admin_action_' . self::ACTION, [$this, 'build']);
    }

    /**
     * Build a nonce action URL.
     * @param File $file
     * @return string
     */
    public static function getActionUrl(File $file): string
    {
        return esc_url(
            wp_nonce_url(
                add_query_arg(['action' => self::ACTION, 'file' => $file->value], admin_url('admin.php')),
                self::NONCE_ACTION,
                self::NONCE_KEY
            )
        );
    }

    /**
     * Return the HTML agents content.
     * @return string|null
     */
    public function retrieveContent(): ?string
    {
        $results = $this->buildResults();

        if (!$results) {
            return null;
        }

        return $this->buildContent($results);
    }

    /**
     * Builds the HTML agents content and write to filesystem.
     * @return never
     */
    protected function build(): never
    {
        $query = $this->getRequest()->query;
        $referer = !wp_get_referer() ? admin_url('plugins.php') : wp_get_referer();
        if (!$this->verifyNonce()) {
            $location = add_query_arg('success', 'false', $referer);
        }

        $_file = \wp_unslash($query->get('file'));
        $enum = !\is_string($_file) ? null : File::tryFrom($_file);
        if ($enum) {
            $results = $this->getResults($this->path($enum));
            if ($results) {
                try {
                    $class = match ($enum) {
                        File::_HTACCESS => new Htaccess(),
                        File::NGINX => throw new Exception('Can not write to Nginx config.'),
                        File::ROBOTS_TXT => new RobotsTxt(),
                    };
                    $class->write($results, $enum);
                    $location = add_query_arg('success', $enum->value, $referer);
                } catch (Exception) {
                }
            }
        }

        wp_safe_redirect($location ?? $referer);
        exit;
    }

    /**
     * Build the contents from results.
     * @param array $results
     * @return string
     */
    abstract protected function buildContent(array $results): string;

    /**
     * Return the results from file type.
     * @param string $file
     * @return array|null
     */
    protected function getResults(string $file): ?array
    {
        if (!$this->exists($file)) {
            if ($this->touch($file) === false) {
                return null;
            }
        }

        if (!$this->isReadable($file) || !$this->isWritable($file)) {
            return null;
        }

        return $this->buildResults();
    }

    /**
     * Build the results from Dark Visitors.
     * @return array|null
     */
    protected function buildResults(): ?array
    {
        $visitors = new DarkVisitors();
        $agents = $visitors->getAgents();

        if (!$agents) {
            return null;
        }

        $list = $visitors->getNodeList($agents);

        if (!$list) {
            return null;
        }

        return $visitors->buildResults($list);
    }

    /**
     * Get file path.
     * @param File $file
     * @return string
     */
    protected function path(File $file): string
    {
        return sprintf('%s/%s', untrailingslashit(get_home_path()), $file->value);
    }

    /**
     * Verify nonce.
     * @return bool
     */
    protected function verifyNonce(): bool
    {
        $query = $this->getRequest()->query;
        return ($query->has('action') && $query->get('action') === self::ACTION) &&
            ($query->has('file') && in_array($query->get('file'), array_column(File::cases(), 'value'))) &&
            ($query->has(self::NONCE_KEY) && wp_verify_nonce($query->get(self::NONCE_KEY), self::NONCE_ACTION) !== 1);
    }

    /**
     * Write results to file.
     * @param array $results
     * @param File|null $enum
     * @return void
     */
    abstract protected function write(array $results, ?File $enum): void;
}
