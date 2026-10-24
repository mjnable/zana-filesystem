<?php

declare(strict_types=1);

namespace Zana\Filesystem;

/**
 * A storage disk. Paths are relative to the disk root and normalised.
 */
interface FilesystemInterface
{
    public function exists(string $path): bool;

    /**
     * @throws FilesystemException If the file does not exist
     */
    public function read(string $path): string;

    /**
     * @return resource
     * @throws FilesystemException If the file does not exist
     */
    public function readStream(string $path);

    /**
     * Write a file, creating its parent directories.
     *
     * @param string|resource $contents
     */
    public function write(string $path, mixed $contents): void;

    /**
     * Delete a file. A missing file is not an error.
     */
    public function delete(string $path): void;

    public function copy(string $from, string $to): void;

    public function move(string $from, string $to): void;

    public function size(string $path): int;

    /**
     * Unix time of the last modification.
     */
    public function lastModified(string $path): int;

    public function mimeType(string $path): string;

    /**
     * Files (not directories) under the directory, sorted, relative to the disk root.
     *
     * @return list<string>
     */
    public function listContents(string $directory = '', bool $recursive = false): array;

    public function createDirectory(string $path): void;

    /**
     * Delete a directory and everything in it. A missing directory is not an error.
     */
    public function deleteDirectory(string $path): void;

    /**
     * The public URL of a file.
     *
     * @throws FilesystemException If the disk has no URL configured
     */
    public function url(string $path): string;
}
