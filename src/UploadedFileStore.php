<?php

declare(strict_types=1);

namespace Zana\Filesystem;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Stores uploaded files under random names, so client file names never reach the disk.
 *
 * The client's extension is kept unless a web server could execute it
 * (php, phar, cgi, …) or a browser would run it from the site (html, svg, js,
 * …; 2.19.2). Such an extension gives way to the one of the detected type,
 * when that is safe, so a JPEG named portrait.html is stored as .jpg. Pass
 * $allowedMimeTypes to accept only known types, and $trustedExtensions to
 * keep, say, svg for a disk that is not served from the site's origin.
 */
final class UploadedFileStore
{
    private const array EXECUTABLE_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phtml', 'phar', 'phps', 'inc',
        'htaccess', 'htpasswd', 'shtml', 'cgi', 'pl', 'py', 'rb', 'sh',
        'asp', 'aspx', 'ashx', 'jsp', 'jspx', 'exe', 'bat', 'cmd', 'com', 'dll',
    ];

    /** Run by a browser when served from the site: scripts in the site's origin (2.19.2). */
    private const array ACTIVE_EXTENSIONS = [
        'html', 'htm', 'xhtml', 'xht', 'shtml', 'mht', 'mhtml', 'svg', 'svgz', 'xml', 'xsl', 'xslt', 'js', 'mjs', 'hta', 'swf', 'wasm',
    ];

    private const array EXTENSION_OF_TYPE = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/avif' => 'avif',
        'application/pdf' => 'pdf', 'text/plain' => 'txt', 'text/csv' => 'csv', 'application/zip' => 'zip',
        'audio/mpeg' => 'mp3', 'video/mp4' => 'mp4', 'image/svg+xml' => 'svg', 'text/html' => 'html',
    ];

    /**
     * @param string $directory Directory on the disk, e.g. "patients/42/documents"
     * @param int|null $maxBytes Reject larger files
     * @param list<string>|null $allowedMimeTypes Reject other detected types
     * @param list<string> $trustedExtensions Extensions a browser runs, kept anyway (2.19.2)
     * @return string The stored path
     * @throws FilesystemException For upload errors and rejected files
     */
    public function store(
        UploadedFileInterface $file,
        FilesystemInterface $disk,
        string $directory,
        ?int $maxBytes = null,
        ?array $allowedMimeTypes = null,
        array $trustedExtensions = [],
    ): string {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new FilesystemException("The upload failed with error code {$file->getError()}.");
        }

        $this->assertSize($file->getSize(), $maxBytes);

        $stream = $file->getStream();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $contents = $stream->getContents();
        // The reported size may be missing or wrong; check what was received
        $this->assertSize(strlen($contents), $maxBytes);

        $extension = strtolower(pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION));
        $trusted = array_map(strtolower(...), $trustedExtensions);
        if (!self::isSafe($extension, $trusted)) {
            $detected = self::EXTENSION_OF_TYPE[Internal\MimeTypes::fromContents(substr($contents, 0, 8192), '')] ?? '';
            $extension = self::isSafe($detected, $trusted) ? $detected : '';
        }
        $name = bin2hex(random_bytes(16)) . ($extension === '' ? '' : ".{$extension}");
        $path = trim(str_replace('\\', '/', $directory), '/') . '/' . $name;
        $path = ltrim($path, '/');
        $disk->write($path, $contents);

        if ($allowedMimeTypes !== null && !in_array($type = $disk->mimeType($path), $allowedMimeTypes, true)) {
            $disk->delete($path);
            throw new FilesystemException("Files of type {$type} are not allowed.");
        }

        return $path;
    }

    /**
     * @param list<string> $trusted
     */
    private static function isSafe(string $extension, array $trusted): bool
    {
        return preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1
            && !in_array($extension, self::EXECUTABLE_EXTENSIONS, true)
            && (!in_array($extension, self::ACTIVE_EXTENSIONS, true) || in_array($extension, $trusted, true));
    }

    private function assertSize(?int $size, ?int $maxBytes): void
    {
        if ($maxBytes !== null && $size !== null && $size > $maxBytes) {
            throw new FilesystemException("The uploaded file is {$size} bytes; the maximum is {$maxBytes}.");
        }
    }
}
