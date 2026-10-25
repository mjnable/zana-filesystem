<?php

declare(strict_types=1);

namespace Zana\Filesystem;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Stores uploaded files under random names, so client file names never reach the disk.
 *
 * The client's extension is kept unless a web server could execute it
 * (php, phar, cgi, …). Pass $allowedMimeTypes to accept only known types.
 */
final class UploadedFileStore
{
    private const array EXECUTABLE_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phtml', 'phar', 'phps', 'inc',
        'htaccess', 'htpasswd', 'shtml', 'cgi', 'pl', 'py', 'rb', 'sh',
        'asp', 'aspx', 'ashx', 'jsp', 'jspx', 'exe', 'bat', 'cmd', 'com', 'dll',
    ];

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

        $this->assertSize($file->getSize(), $maxBytes);

        $extension = strtolower(pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION));
        $safe = preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1 && !in_array($extension, self::EXECUTABLE_EXTENSIONS, true);
        $name = bin2hex(random_bytes(16)) . ($safe ? ".{$extension}" : '');
        $path = trim(str_replace('\\', '/', $directory), '/') . '/' . $name;
        $path = ltrim($path, '/');

        $stream = $file->getStream();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $contents = $stream->getContents();
        // The reported size may be missing or wrong; check what was received
        $this->assertSize(strlen($contents), $maxBytes);
        $disk->write($path, $contents);

        if ($allowedMimeTypes !== null && !in_array($type = $disk->mimeType($path), $allowedMimeTypes, true)) {
            $disk->delete($path);
            throw new FilesystemException("Files of type {$type} are not allowed.");
        }

        return $path;
    }

    private function assertSize(?int $size, ?int $maxBytes): void
    {
        if ($maxBytes !== null && $size !== null && $size > $maxBytes) {
            throw new FilesystemException("The uploaded file is {$size} bytes; the maximum is {$maxBytes}.");
        }
    }
}
