<?php

declare(strict_types=1);

namespace TheFrosty\WpBlockAiScrapers\Http;

use DOMNodeList;

/**
 * Interface Agents
 * @package TheFrosty\WpBlockAiScrapers\Http
 */
interface Agents
{

    /**
     * Update Remote Cache.
     * @return void
     */
    public function updateCache(): void;

    /**
     * Get the Agents from cache.
     * @return mixed
     */
    public function getAgents(): mixed;

    /**
     * Get the DOM Node List.
     * @param string $source
     * @return DOMNodeList|null
     */
    public function getNodeList(string $source): ?DOMNodeList;

    /**
     * Build the results array.
     * @param DOMNodeList $list
     * @return array
     */
    public function buildResults(DOMNodeList $list): array;
}
