<?php
/**
 * Plugin Name: Block Ai Scrapers
 * Description: A simple way to block AI Scraper bots in your WordPress' <code>.htaccess</code>, <code>nginx</code>, or <code>robots.txt</code> file! A <a href="https://frosty.media/">Frosty Media</a> plugin.
 * Author: Austin Passy
 * Author URI: https://github.com/thefrosty
 * Version: 1.0.2
 * Requires at least: 6.2
 * Tested up to: 6.5
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
use function add_action;
use function add_filter;
use function apply_filters;
use function class_exists;
use function defined;
use function delete_transient;
use function dirname;
use function error_log;
use function esc_html__;
use function is_admin;
use function is_readable;
use function load_plugin_textdomain;
use function plugin_basename;
use function register_activation_hook;
use function register_deactivation_hook;
use function sprintf;
use function wp_kses_post;
use function wp_schedule_single_event;
use function wp_unschedule_event;
use function wpautop;
use const PHP_VERSION;
use const WP_DEBUG;

/**
 * Maybe trigger an error notice "message" on the `admin_notices` action hook.
 * Uses an anonymous function which required PHP >= 5.3.
 */
add_action('admin_notices', static function (): void {
    $message = apply_filters('block_ai_scrapers_shutdown_error_message', '');
    if (!is_admin() || empty($message)) {
        return;
    }
    load_plugin_textdomain('wp-block-ai-scrapers', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    echo wp_kses_post(sprintf('<div class="error">%s</div>', wpautop($message)));
});

if (version_compare(PHP_VERSION, '8.1', '<')) {
    return add_filter('block_ai_scrapers_shutdown_error_message', static function (): string {
        return sprintf(
            esc_html__(
                'Notice: WP Block AI Scrapers requires PHP version >= 8.1, you are running %s, all features are currently disabled.',
                'wp-block-ai-scrapers'
            ),
            PHP_VERSION
        );
    });
} elseif (!is_readable(__DIR__ . '/vendor/autoload.php') && !class_exists(PluginFactory::class)) {
    return add_filter('block_ai_scrapers_shutdown_error_message', static function (): string {
        return esc_html__(
            'Error: WP Block AI Scrapers can\'t find the autoload file (if installed from GitHub, please run `composer install`), all features are currently disabled.',
            'wp-block-ai-scrapers'
        );
    });
}

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
