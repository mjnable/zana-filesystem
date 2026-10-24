<?php

declare(strict_types=1);

namespace Zana\Filesystem;

use Closure;
use Zana\Filesystem\Internal\MimeTypes;
use Zana\Filesystem\Internal\Path;

/**
 * A disk held in memory, for tests and temporary data. Directories are implicit.
 */
final class InMemoryFilesystem implements FilesystemInterface
{
    /** @var array<string, array{string, int}> path => [contents, modified at] */
    private array $files = [];

    /** @var Closure(): int */
    private readonly Closure $clock;

    /**
     * @param (Closure(): int)|null $clock Current Unix time, for tests
     */
    public function __construct(private readonly ?string $url = null, ?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function exists(string $path): bool
    {
        return isset($this->files[Path::normalize($path)]);
    }

    public function read(string $path): string
    {
        return $this->file($path)[0];
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://memory', 'w+b');
        if ($stream === false) {
            throw FilesystemException::failed('open', $path);
        }

        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function write(string $path, mixed $contents): void
    {
        if (is_resource($contents)) {
            $contents = (string) stream_get_contents($contents);
        }

        if (!is_string($contents)) {
            throw new \InvalidArgumentException('File contents must be a string or a stream resource.');
        }

        $normalized = Path::normalize($path);
        if ($normalized === '') {
            throw FilesystemException::failed('write', 'the disk root');
        }

        $this->files[$normalized] = [$contents, ($this->clock)()];
    }

    public function delete(string $path): void
    {
        unset($this->files[Path::normalize($path)]);
    }

    public function copy(string $from, string $to): void
    {
        $this->write($to, $this->read($from));
    }

    public function move(string $from, string $to): void
    {
        $contents = $this->read($from);
        $this->delete($from);
        $this->write($to, $contents);
    }

    public function size(string $path): int
    {
        return strlen($this->read($path));
    }

    public function lastModified(string $path): int
    {
        return $this->file($path)[1];
    }

    public function mimeType(string $path): string
    {
        return MimeTypes::fromContents($this->read($path), Path::normalize($path));
    }

    public function listContents(string $directory = '', bool $recursive = false): array
    {
        $prefix = Path::normalize($directory);
        $prefix = $prefix === '' ? '' : $prefix . '/';

        $files = [];
        foreach (array_keys($this->files) as $path) {
            $path = (string) $path;
            if (!str_starts_with($path, $prefix)) {
                continue;
            }
            if (!$recursive && str_contains(substr($path, strlen($prefix)), '/')) {
                continue;
            }
            $files[] = $path;
        }
        sort($files);

        return $files;
    }

    public function createDirectory(string $path): void
    {
        Path::normalize($path);
    }

    public function deleteDirectory(string $path): void
    {
        $prefix = Path::normalize($path);
        if ($prefix === '') {
            throw FilesystemException::failed('delete', 'the disk root');
        }

        foreach (array_keys($this->files) as $file) {
            if (str_starts_with((string) $file, $prefix . '/')) {
                unset($this->files[$file]);
            }
        }
    }

    public function url(string $path): string
    {
        if ($this->url === null) {
            throw new FilesystemException('This disk has no public URL.');
        }

        return Path::url($this->url, Path::normalize($path));
    }

    /**
     * @return array{string, int}
     */
    private function file(string $path): array
    {
        $normalized = Path::normalize($path);

        return $this->files[$normalized] ?? throw FilesystemException::notFound($normalized);
    }
}
