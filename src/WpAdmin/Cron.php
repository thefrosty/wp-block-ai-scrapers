<?php

declare(strict_types=1);

namespace TheFrosty\WpBlockAiScrapers\WpAdmin;

use TheFrosty\WpBlockAiScrapers\Http\DarkVisitors;
use TheFrosty\WpUtilities\Plugin\AbstractHookProvider;
use function add_action;
use function wp_next_scheduled;
use function wp_schedule_event;

/**
 * Class Cron
 * @package TheFrosty\WpBlockAiScrapers
 */
class Cron extends AbstractHookProvider
{

    final public const HOOK = 'wp_block_ai_scrapers_get_agents';

    /**
     * Add class hooks.
     * @return void
     */
    public function addHooks(): void
    {
        $this->addAction('init', [$this, 'scheduleEvent']);
        $this->addAction(self::HOOK, [$this, 'runner']);
    }

    /**
     * Schedule our CRON event.
     * @return void
     */
    protected function scheduleEvent(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time(), 'daily', self::HOOK);
        }
    }

    /**
     * Cron event runner.
     * @return void
     */
    protected function runner(): void
    {
        add_action('shutdown', static function(): void {
            (new DarkVisitors())->updateCache();
        });
    }
}
