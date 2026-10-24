<?php

declare(strict_types=1);

namespace Zana\Filesystem;

use Zana\Filesystem\Internal\MimeTypes;
use Zana\Filesystem\Internal\Path;

/**
 * A disk backed by a local directory. Writes are atomic, and no path may
 * leave the root, including through symbolic links.
 */
final class LocalFilesystem implements FilesystemInterface
{
    private readonly string $root;

    public function __construct(string $root, private readonly ?string $url = null)
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (!is_dir($root) && !@mkdir($root, 0775, true) && !is_dir($root)) {
            throw FilesystemException::failed('create the disk root', $root);
        }

        $this->root = rtrim(str_replace('\\', '/', (string) realpath($root)), '/');
    }

    public function exists(string $path): bool
    {
        return is_file($this->location($path));
    }

    public function read(string $path): string
    {
        $location = $this->existingFile($path);
        $contents = @file_get_contents($location);

        return $contents === false ? throw FilesystemException::failed('read', $path) : $contents;
    }

    public function readStream(string $path)
    {
        $stream = @fopen($this->existingFile($path), 'rb');

        return $stream === false ? throw FilesystemException::failed('open', $path) : $stream;
    }

    public function write(string $path, mixed $contents): void
    {
        if (!is_string($contents) && !is_resource($contents)) {
            throw new \InvalidArgumentException('File contents must be a string or a stream resource.');
        }

        $location = $this->location($path);
        $this->ensureDirectory(dirname($location), $path);

        $temporary = $location . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $written = is_string($contents)
            ? @file_put_contents($temporary, $contents) !== false
            : $this->copyStream($contents, $temporary);

        if (!$written || !@rename($temporary, $location)) {
            @unlink($temporary);
            throw FilesystemException::failed('write', $path);
        }
    }

    public function delete(string $path): void
    {
        $location = $this->location($path);
        if (is_file($location) && !@unlink($location)) {
            throw FilesystemException::failed('delete', $path);
        }
    }

    public function copy(string $from, string $to): void
    {
        $stream = $this->readStream($from);
        try {
            $this->write($to, $stream);
        } finally {
            fclose($stream);
        }
    }

    public function move(string $from, string $to): void
    {
        $source = $this->existingFile($from);
        $target = $this->location($to);
        $this->ensureDirectory(dirname($target), $to);

        if (!@rename($source, $target)) {
            throw FilesystemException::failed('move', $from, "to {$to}");
        }
    }

    public function size(string $path): int
    {
        $size = @filesize($this->existingFile($path));

        return $size === false ? throw FilesystemException::failed('read the size of', $path) : $size;
    }

    public function lastModified(string $path): int
    {
        $time = @filemtime($this->existingFile($path));

        return $time === false ? throw FilesystemException::failed('read the modification time of', $path) : $time;
    }

    public function mimeType(string $path): string
    {
        $location = $this->existingFile($path);
        $head = (string) @file_get_contents($location, false, null, 0, 8192);

        return MimeTypes::fromContents($head, $location);
    }

    public function listContents(string $directory = '', bool $recursive = false): array
    {
        $base = Path::normalize($directory);
        $location = $this->location($base);
        if (!is_dir($location)) {
            return [];
        }

        $iterator = $recursive
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($location, \FilesystemIterator::SKIP_DOTS))
            : new \FilesystemIterator($location);

        $files = [];
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && !str_ends_with($file->getFilename(), '.tmp')) {
                $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($this->root) + 1);
                $files[] = $relative;
            }
        }
        sort($files);

        return $files;
    }

    public function createDirectory(string $path): void
    {
        $this->ensureDirectory($this->location($path), $path);
    }

    public function deleteDirectory(string $path): void
    {
        $normalized = Path::normalize($path);
        if ($normalized === '') {
            throw FilesystemException::failed('delete', 'the disk root');
        }

        $location = $this->location($normalized);
        if (!is_dir($location)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($location, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $ok = $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            if (!$ok) {
                throw FilesystemException::failed('delete', $path);
            }
        }

        if (!@rmdir($location)) {
            throw FilesystemException::failed('delete', $path);
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
     * The absolute location of a path, checked to stay inside the root
     * once symbolic links in its existing part are resolved.
     */
    private function location(string $path): string
    {
        $normalized = Path::normalize($path);
        $location = $normalized === '' ? $this->root : "{$this->root}/{$normalized}";

        $existing = $location;
        while (!file_exists($existing) && $existing !== $this->root && strlen($existing) > strlen($this->root)) {
            $existing = dirname($existing);
        }

        $resolved = str_replace('\\', '/', (string) realpath($existing));
        if ($resolved !== $this->root && !str_starts_with($resolved . '/', $this->root . '/')) {
            throw PathTraversalException::for($path);
        }

        return $location;
    }

    private function existingFile(string $path): string
    {
        $location = $this->location($path);

        return is_file($location) ? $location : throw FilesystemException::notFound(Path::normalize($path));
    }

    private function ensureDirectory(string $directory, string $path): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw FilesystemException::failed('create the directory for', $path);
        }
    }

    /**
     * @param resource $stream
     */
    private function copyStream($stream, string $target): bool
    {
        $handle = @fopen($target, 'wb');
        if ($handle === false) {
            return false;
        }

        try {
            return stream_copy_to_stream($stream, $handle) !== false;
        } finally {
            fclose($handle);
        }
    }
}
