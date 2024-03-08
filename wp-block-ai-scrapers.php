<?php
/**
 * Plugin Name: Block Ai Scrapers
 * Description: A simple way to block AI Scraper bots in your WordPress' <code>.htaccess</code>, <code>nginx</code>, or <code>robots.txt</code> file! A <a href="https://frosty.media/">Frosty Media</a> plugin.
 * Author: Austin Passy
 * Author URI: https://github.com/thefrosty
 * Version: 1.0.0
 * Requires at least: 6.2
 * Tested up to: 6.4.4
 * Requires PHP: 8.1
 * Plugin URI: https://github.com/thefrosty/wp-block-ai-scrapers
 * GitHub Plugin URI: https://github.com/thefrosty/wp-block-ai-scrapers
 * Primary Branch: develop
 * Release Asset: true
 */

namespace TheFrosty\WpBlockAiScrapers;

defined('ABSPATH') || exit;

use Pimple\Container;
use TheFrosty\WpBlockAiScrapers\Api\Htaccess;
use TheFrosty\WpBlockAiScrapers\Api\RobotsTxt;
use TheFrosty\WpBlockAiScrapers\Http\DarkVisitors;
use TheFrosty\WpBlockAiScrapers\WpAdmin\Cron;
use TheFrosty\WpBlockAiScrapers\WpAdmin\Settings;
use TheFrosty\WpUtilities\Plugin\PluginFactory;
use UnexpectedValueException;
use function class_exists;
use function defined;
use function delete_transient;
use function error_log;
use function is_readable;
use function register_activation_hook;
use function register_deactivation_hook;
use function wp_schedule_single_event;
use function wp_unschedule_event;
use const WP_DEBUG;

if (is_readable(__DIR__ . '/vendor/autoload.php')) {
    include_once __DIR__ . '/vendor/autoload.php';
}

$plugin = PluginFactory::create('block-ai-scrapers');
$container = $plugin->getContainer();
if (!$container instanceof Container) {
    throw new UnexpectedValueException('Unexpected object in Plugin container.');
}
$container->register(new ServiceProvider());

$plugin
    ->add(new Cron())
    ->addOnHook(Htaccess::class, 'admin_init')
    ->addOnHook(RobotsTxt::class, 'admin_init')
    ->addOnConditionDeferred(
        wp_hook: Settings::class,
        function: fn() => current_user_can('activate_plugins'),
        tag: 'init',
        admin_only: true,
        args: [$container]
    )
    ->initialize();

register_activation_hook(
    __FILE__,
    static function (): void {
        wp_schedule_single_event(time(), Cron::HOOK);
    }
);

register_deactivation_hook(
    __FILE__,
    static function (): void {
        if (!is_readable(__DIR__ . '/vendor/autoload.php') || !class_exists(DarkVisitors::class)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('The Block Ai Scrapers plugin could not run proper deactivation hook.');
            }
            return;
        }
        include_once __DIR__ . '/vendor/autoload.php';
        delete_transient((new DarkVisitors())->getKey());
        wp_unschedule_event(time(), Cron::HOOK);
    }
);
