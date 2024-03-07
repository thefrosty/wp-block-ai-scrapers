<?php

declare(strict_types=1);

namespace TheFrosty\WpBlockAiScrapers\Api;

use TheFrosty\WpBlockAiScrapers\Http\DarkVisitors;
use function implode;

/**
 * Class Nginx
 * @package TheFrosty\WpBlockAiScrapers\Api
 */
class Nginx extends Writer
{

    /**
     * Write contents.
     * @param array $results
     * @param File|null $enum
     * @return void
     */
    protected function write(array $results, ?File $enum): void
    {
    }

    /**
     * Build contents from results.
     * @param array $results
     * @return string
     */
    protected function buildContent(array $results): string
    {
        $agents = implode('|', $results);
        $content = DarkVisitors::BEGIN_STRING . PHP_EOL;
        $content .= '# Case sensitive matching' . PHP_EOL;
        $content .= "if (\$http_user_agent ~ ($agents)) {" . PHP_EOL;
        $content .= "\t" . 'return 403;' . PHP_EOL;
        $content .= '}' . PHP_EOL;
        $content .= DarkVisitors::END_STRING;

        return $content;
    }
}
