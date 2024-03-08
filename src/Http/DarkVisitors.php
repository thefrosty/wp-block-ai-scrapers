<?php

declare(strict_types=1);

namespace TheFrosty\WpBlockAiScrapers\Http;

use DOMDocument;
use DOMNodeList;
use DOMXPath;
use TheFrosty\WpBlockAiScrapers\Api\Filesystem;
use TheFrosty\WpUtilities\Api\TransientsTrait;
use TheFrosty\WpUtilities\Api\WpRemote;
use function array_reverse;
use function delete_transient;
use function in_array;
use function libxml_use_internal_errors;
use function method_exists;
use function time;
use function wp_remote_retrieve_body;
use const DAY_IN_SECONDS;
use const MONTH_IN_SECONDS;

/**
 * Class DarkVisitors
 * @package TheFrosty\Http
 */
class DarkVisitors implements Agents
{

    use Filesystem, TransientsTrait, WpRemote;

    final public const AGENTS_URL = 'https://darkvisitors.com/agents';
    final public const BEGIN_STRING = '# BEGIN Block AI Scrapers';
    final public const END_STRING = '# END Block AI Scrapers';
    private const ENCRYPTION_KEY = 'Bl0ckAiScrap3rs|';
    private const PREFIX = '_ai_scrapers_';

    /**
     * Update the Agents transient cache.
     * @param bool $force
     * @return void
     */
    public function updateCache(bool $force = false): void
    {
        $key = $this->getKey();
        $timeout = !method_exists($this, 'getTransientTimeout') ? null : $this->getTransientTimeout($key);
        if ($force || $timeout && time() - $timeout > DAY_IN_SECONDS) {
            delete_transient($key);
        }
        $this->getAgents($force);
    }

    /**
     * Get the Agents from cache.
     * @param bool $force
     * @return mixed
     */
    public function getAgents(bool $force = false): mixed
    {
        $key = $this->getKey();
        $body = $this->getTransient($key);
        if ($force || empty($body)) {
            $body = wp_remote_retrieve_body($this->wpRemoteGet(self::AGENTS_URL));
            if ($body !== '') {
                $body = $this->encrypt($body, self::ENCRYPTION_KEY);
                $this->setTransient($key, $body, MONTH_IN_SECONDS);
            }
        }

        return empty($body) ? $body : $this->decrypt($body, self::ENCRYPTION_KEY);
    }

    /**
     * Get the DOM Node List.
     * @param string $source
     * @return DOMNodeList|null
     */
    public function getNodeList(string $source): ?DOMNodeList
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($source);
        $classname = "agent-type-tag";
        $list = (new DOMXPath($doc))->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

        return !$list instanceof DOMNodeList ? null : $list;
    }

    /**
     * Build the results array.
     * @param DOMNodeList $list
     * @return array
     */
    public function buildResults(DOMNodeList $list): array
    {
        $types = $this->getAgentTypes();
        $results = [];

        // Iterate through HTML, plucking out only desired agents.
        for ($i = $list->length - 1; $i > -1; $i--) {
            if (!in_array($list->item($i)?->firstChild?->nodeValue, $types, true)) {
                continue;
            }
            $results[] = $list->item(
                $i
            )?->firstChild?->parentNode?->parentNode?->parentNode?->firstElementChild?->firstElementChild?->nodeValue;
        }

        // Change sort order A-Z.
        return array_reverse(array_filter($results));
    }

    /**
     * Get the transient key.
     * @return string
     */
    public function getKey(): string
    {
        return $this->getTransientKey(self::AGENTS_URL, self::PREFIX);
    }

    /**
     * Get allowed Agent types.
     * @see https://darkvisitors.com/agents
     * @return array
     */
    protected function getAgentTypes(): array
    {
        $defaults = ['AI Assistant', 'AI Data Scraper', 'AI Search Crawler', 'Scraper'];
        return \array_filter(\wp_parse_args(\apply_filters('wp_block_ai_scrapers_agent_types', []), $defaults));
    }
}
