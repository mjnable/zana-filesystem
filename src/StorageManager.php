<?php

declare(strict_types=1);

namespace Zana\Filesystem;

use Zana\Configuration\ConfigurationInterface;

/**
 * Builds and caches named disks from the "filesystem" configuration.
 *
 *     'filesystem' => [
 *         'default' => 'local',
 *         'disks' => [
 *             'local' => ['driver' => 'local', 'root' => 'var/storage'],
 *             'public' => ['driver' => 'local', 'root' => 'public/files', 'url' => 'https://bmi.example/files'],
 *         ],
 *     ]
 */
final class StorageManager
{
    /** @var array<string, FilesystemInterface> */
    private array $disks = [];

    public function __construct(
        private readonly ConfigurationInterface $config,
        private readonly string $basePath,
    ) {
    }

    /**
     * @throws \InvalidArgumentException For an unknown disk or driver
     */
    public function disk(?string $name = null): FilesystemInterface
    {
        $name ??= $this->config->string('filesystem.default', 'memory');

        return $this->disks[$name] ??= $this->create($name);
    }

    private function create(string $name): FilesystemInterface
    {
        $settings = $this->config->array("filesystem.disks.{$name}", []);

        if ($settings === []) {
            if ($name === 'memory' && !$this->config->has('filesystem.disks')) {
                return new InMemoryFilesystem();
            }

            throw new \InvalidArgumentException("Disk \"{$name}\" is not configured.");
        }

        $url = isset($settings['url']) ? (string) $settings['url'] : null;

        return match ($settings['driver'] ?? null) {
            'local' => new LocalFilesystem($this->path((string) ($settings['root'] ?? 'var/storage')), $url),
            'memory' => new InMemoryFilesystem($url),
            default => throw new \InvalidArgumentException(sprintf('Disk "%s" has an unknown driver: %s.', $name, var_export($settings['driver'] ?? null, true))),
        };
    }

    private function path(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        return $isAbsolute ? $path : rtrim($this->basePath, '/') . '/' . $path;
    }
}
