<?php

declare(strict_types=1);

namespace TheFrosty\WpBlockAiScrapers;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use TheFrosty\WpUtilities\Utils\View;

/**
 * Class ServiceProvider
 * @package TheFrosty\WpBlockAiScrapers
 */
class ServiceProvider implements ServiceProviderInterface
{
    public const WP_UTILITIES_VIEW = 'wp_utilities.view';

    /**
     * Register services.
     * @param Container $pimple Container instance.
     */
    public function register(Container $pimple): void
    {
        $pimple[self::WP_UTILITIES_VIEW] = static function (): View {
            $view = new View();
            $view->addPath(dirname(__DIR__) . '/resources/views/');

            return $view;
        };
    }
}
