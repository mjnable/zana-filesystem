# Zana Filesystem

Storage disks with one API, safe paths, and storage of uploaded files.
Requires PHP 8.5 or later.

```
composer require mj-zana/filesystem
```

## Usage

Register `FilesystemServiceProvider` and configure disks in `config/filesystem.php`:

```php
return [
    'default' => 'local',
    'disks' => [
        'local' => ['driver' => 'local', 'root' => 'var/storage'],
        'public' => ['driver' => 'local', 'root' => 'public/files', 'url' => 'https://bmi.example/files'],
    ],
];
```

```php
public function __construct(private FilesystemInterface $disk, private UploadedFileStore $uploads) {}

$this->disk->write('reports/2026-10.csv', $csv);
$csv = $this->disk->read('reports/2026-10.csv');

$path = $this->uploads->store($request->getUploadedFiles()['scan'], $this->disk, "patients/{$id}", maxBytes: 5_000_000, allowedMimeTypes: ['image/png', 'application/pdf']);
```

- **Paths** are relative to the disk root. A `..` that would leave the root
  throws `PathTraversalException`; on local disks, so does a symbolic link
  pointing outside it.
- **Writes** create parent directories and are atomic on local disks.
- **Uploads** are stored under random names. Only a safe, lower-cased extension
  is kept from the client's file name; extensions a web server could execute
  (`php`, `phar`, `cgi`, …) are dropped. The size limit is checked against the
  bytes received.
- **Disks:** `local` (a directory) and `memory`, which is the default without
  configuration and suits tests.

## License

MIT — see [LICENSE](LICENSE).
