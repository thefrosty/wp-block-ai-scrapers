<?php

declare(strict_types=1);

namespace TheFrosty\WpBlockAiScrapers\Api;

use TheFrosty\WpBlockAiScrapers\Http\DarkVisitors;
use function implode;
use function preg_replace;
use function sprintf;
use function str_contains;

/**
 * Class RobotsTxt
 * @package TheFrosty\WpBlockAiScrapers\Api
 */
class RobotsTxt extends Writer
{

    /**
     * Write contents to file.
     * @param array $results
     * @param File|null $enum
     * @return void
     */
    protected function write(array $results, ?File $enum): void
    {
        if ($enum !== File::ROBOTS_TXT) {
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

            $contents .= $this->buildContent($results);
            $this->putContents($file, $contents);
        }
    }

    /**
     * Build content from results.
     * @param array $results
     * @return string
     */
    protected function buildContent(array $results): string
    {
        $line_items = [
            DarkVisitors::BEGIN_STRING . "\n\n",
        ];

        foreach ($results as $result) {
            $line_items[] = 'User-agent: ' . $result . "\n" . 'Disallow: /' . "\n\n";
        }

        $line_items[] = DarkVisitors::END_STRING;

        return implode('', $line_items);
    }
}
