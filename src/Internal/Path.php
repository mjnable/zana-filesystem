<?php

declare(strict_types=1);

namespace Zana\Filesystem\Internal;

use Zana\Filesystem\PathTraversalException;

/**
 * Path normalisation shared by the disks.
 *
 * @internal
 */
final class Path
{
    /**
     * "/a//b/./c/../d\e" becomes "a/b/d/e". A ".." above the root throws.
     */
    public static function normalize(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw PathTraversalException::for(str_replace("\0", '\\0', $path));
        }

        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($segments === []) {
                    throw PathTraversalException::for($path);
                }
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * The public URL for a normalised path.
     */
    public static function url(string $base, string $path): string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

        return rtrim($base, '/') . '/' . $encoded;
    }
}
