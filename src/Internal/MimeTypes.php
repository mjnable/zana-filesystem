<?php

declare(strict_types=1);

namespace Zana\Filesystem\Internal;

/**
 * MIME type detection: finfo when available, otherwise the file extension.
 *
 * @internal
 */
final class MimeTypes
{
    private const BY_EXTENSION = [
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * finfo's answer, unless it is generic (octet-stream, plain text) and the
     * extension is more specific, e.g. text/csv for a .csv file.
     */
    public static function fromContents(string $contents, string $path): string
    {
        $byExtension = self::BY_EXTENSION[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

        if (function_exists('finfo_open') && $contents !== '') {
            $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
            if (is_string($detected) && $detected !== '') {
                $generic = in_array($detected, ['application/octet-stream', 'text/plain'], true);

                return $generic && $byExtension !== null ? $byExtension : $detected;
            }
        }

        return $byExtension ?? 'application/octet-stream';
    }
}
