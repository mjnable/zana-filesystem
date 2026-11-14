<?php

declare(strict_types=1);

namespace Zana\Filesystem\Inspection;

use Zana\Core\ApplicationInterface;
use Zana\Core\Inspection\ApplicationCheck;
use Zana\Core\Inspection\Finding;
use Zana\Core\Inspection\Severity;

/**
 * security.public_disk (2.21, FE-68, ADR-033), always a warning: a local
 * disk whose root is inside the web root (public/), so the web server serves
 * every file on it, uploads included, without the application deciding.
 *
 * A disk that declares a "url" is served on purpose, such as public images,
 * and is not reported.
 */
final readonly class PublicDiskCheck implements ApplicationCheck
{
    public function findings(ApplicationInterface $application): array
    {
        $public = self::normalize($application->path('public'));
        $findings = [];
        foreach ($application->config()->array('filesystem.disks', []) as $name => $disk) {
            if (!is_array($disk) || ($disk['driver'] ?? null) !== 'local' || isset($disk['url'])) {
                continue;
            }
            $root = (string) ($disk['root'] ?? 'var/storage');
            $absolute = str_starts_with($root, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $root) === 1 ? $root : $application->basePath() . '/' . $root;
            $root = self::normalize($absolute);
            if ($root === $public || str_starts_with($root, $public . '/')) {
                $findings[] = new Finding(
                    'security.public_disk',
                    Severity::Warning,
                    "The disk \"{$name}\" is inside the web root ({$disk['root']}), so the web server serves every file on it, uploads included.",
                    hint: 'Keep uploads outside public/, e.g. var/storage, and send them through a controller that checks access. If the files are public on purpose, give the disk a "url".',
                    security: true,
                );
            }
        }

        return $findings;
    }

    /**
     * Forward slashes, no "." or ".." segments, and lower case on Windows,
     * whose paths ignore case: "public/../public/files" is in public/.
     */
    private static function normalize(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $i => $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '.' && ($part !== '' || $i === 0)) {
                $parts[] = $part;
            }
        }
        $normalized = rtrim(implode('/', $parts), '/');

        return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
    }
}
