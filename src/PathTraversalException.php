<?php

declare(strict_types=1);

namespace Zana\Filesystem;

/**
 * Thrown for a path that would leave the disk root.
 */
final class PathTraversalException extends FilesystemException
{
    public static function for(string $path): self
    {
        return new self("The path \"{$path}\" leaves the disk root.");
    }

    /**
     * On a disk that does not follow links (2.33).
     */
    public static function link(string $path): self
    {
        return new self("The path \"{$path}\" goes through a symbolic link, and this disk does not follow links.");
    }
}
