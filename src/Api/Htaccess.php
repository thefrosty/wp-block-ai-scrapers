<?php

declare(strict_types=1);

namespace TheFrosty\WpBlockAiScrapers\Api;

use TheFrosty\WpBlockAiScrapers\Http\DarkVisitors;
use function implode;
use function preg_replace;
use function sprintf;
use function str_contains;

/**
 * Class Htaccess
 * @package TheFrosty\WpBlockAiScrapers\Api
 */
class Htaccess extends Writer
{

    /**
     * Write contents to file.
     * @param array $results
     * @param File|null $enum
     * @return void
     */
    protected function write(array $results, ?File $enum): void
    {
        if ($enum !== File::_HTACCESS) {
            return;
        }

        $file = $this->path($enum);
        $contents = $this->getContents($file);

        if ($contents !== null) {
            // Replace rules if they already exist
            if (str_contains($contents, DarkVisitors::BEGIN_STRING)) {
                $regex = sprintf("/(?s)(%s).*?(%s)/", DarkVisitors::BEGIN_STRING, DarkVisitors::END_STRING);
                $contents = preg_replace($regex, $this->buildContent($results), $contents);

                $this->putContents($file, $contents);
                return;
            }

            $contents = $this->buildContent($results) . $contents;
            $this->putContents($file, $contents);
        }
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
        $content .= '<IfModule mod_rewrite.c>' . PHP_EOL;
        $content .= 'RewriteEngine On' . PHP_EOL;
        $content .= "RewriteCond %{HTTP_USER_AGENT} \"($agents)\" [NC]" . PHP_EOL;
        $content .= 'RewriteRule "^.*$" - [F,L]' . PHP_EOL;
        $content .= '</IfModule>' . PHP_EOL;
        $content .= DarkVisitors::END_STRING;

        return $content;
    }
}
