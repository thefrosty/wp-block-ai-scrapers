<?php

declare(strict_types=1);

namespace TheFrosty\WpBlockAiScrapers\Api;

use WP_Filesystem_Base;

/**
 * Trait Filesystem
 * @package TheFrosty\WpBlockAiScrapers\Api
 */
trait Filesystem
{

    /**
     * Parser constructor.
     * @param WP_Filesystem_Base|null $filesystem
     */
    public function __construct(protected ?WP_Filesystem_Base $filesystem = null)
    {
        if (!$filesystem) {
            global $wp_filesystem;

            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }

            $this->filesystem = $wp_filesystem;
        }
    }

    /**
     * Get file contents.
     * @param string $file
     * @return string|null
     */
    public function getContents(string $file): ?string
    {
        $contents = $this->filesystem->get_contents($file);
        return $contents === false ? null : $contents;
    }

    /**
     * Write contents to file.
     * @param string $file
     * @param string $contents
     * @return bool
     */
    public function putContents(string $file, string $contents): bool
    {
        return $this->filesystem->put_contents($file, $contents);
    }

    /**
     * Checks if a file or directory exists.
     * @param string $path Path to file or directory.
     * @return bool
     */
    public function exists(string $path): bool
    {
        return $this->filesystem->exists($path);
    }


    /**
     * Checks if a file is readable.
     * @param string $file Path to file.
     * @return bool
     */
    public function isReadable(string $file): bool
    {
        return $this->filesystem->is_readable($file);
    }

    /**
     * Checks if a file or directory is writable.
     * @param string $path Path to file or directory.
     * @return bool
     */
    public function isWritable(string $path): bool
    {
        return $this->filesystem->is_writable($path);
    }

    /**
     * Sets the access and modification times of a file.
     * Note: If $file doesn't exist, it will be created.
     * @param string $file Path to file.
     * @return bool
     */
    public function touch(string $file): bool
    {
        return $this->filesystem->touch($file);
    }
}
