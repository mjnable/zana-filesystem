<?php

declare(strict_types=1);

namespace Zana\Filesystem;

/**
 * Thrown when a file operation fails.
 */
class FilesystemException extends \RuntimeException
{
    public static function notFound(string $path): self
    {
        return new self("File not found: {$path}.");
    }

    public static function failed(string $operation, string $path, ?string $reason = null): self
    {
        return new self("Cannot {$operation} {$path}" . ($reason === null ? '.' : ": {$reason}."));
    }
}
