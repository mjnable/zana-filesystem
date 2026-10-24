<?php

declare(strict_types=1);

namespace Zana\Filesystem;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Stores uploaded files under random names, so client file names never reach the disk.
 */
final class UploadedFileStore
{
    /**
     * @param string $directory Directory on the disk, e.g. "patients/42/documents"
     * @param int|null $maxBytes Reject larger files
     * @param list<string>|null $allowedMimeTypes Reject other detected types
     * @return string The stored path
     * @throws FilesystemException For upload errors and rejected files
     */
    public function store(
        UploadedFileInterface $file,
        FilesystemInterface $disk,
        string $directory,
        ?int $maxBytes = null,
        ?array $allowedMimeTypes = null,
    ): string {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new FilesystemException("The upload failed with error code {$file->getError()}.");
        }

        $size = $file->getSize();
        if ($maxBytes !== null && $size !== null && $size > $maxBytes) {
            throw new FilesystemException("The uploaded file is {$size} bytes; the maximum is {$maxBytes}.");
        }

        $extension = strtolower(pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION));
        $name = bin2hex(random_bytes(16)) . (preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1 ? ".{$extension}" : '');
        $path = trim(str_replace('\\', '/', $directory), '/') . '/' . $name;
        $path = ltrim($path, '/');

        $stream = $file->getStream();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $disk->write($path, $stream->getContents());

        if ($allowedMimeTypes !== null && !in_array($type = $disk->mimeType($path), $allowedMimeTypes, true)) {
            $disk->delete($path);
            throw new FilesystemException("Files of type {$type} are not allowed.");
        }

        return $path;
    }
}
