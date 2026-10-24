<?php

declare(strict_types=1);

namespace Zana\Filesystem\Tests;

use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;
use Zana\Configuration\Configuration;
use Zana\Core\Application;
use Zana\Filesystem\FilesystemException;
use Zana\Filesystem\FilesystemInterface;
use Zana\Filesystem\FilesystemServiceProvider;
use Zana\Filesystem\InMemoryFilesystem;
use Zana\Filesystem\LocalFilesystem;
use Zana\Filesystem\StorageManager;
use Zana\Filesystem\UploadedFileStore;

final class StorageAndUploadTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    // ── StorageManager ─────────────────────────────────────────────────────

    public function testDisksComeFromConfiguration(): void
    {
        $base = sys_get_temp_dir() . '/zana-fs-manager-' . bin2hex(random_bytes(4));
        $manager = new StorageManager(new Configuration(['filesystem' => [
            'default' => 'local',
            'disks' => [
                'local' => ['driver' => 'local', 'root' => 'storage'],
                'memory' => ['driver' => 'memory', 'url' => 'https://cdn.example'],
            ],
        ]]), $base);

        $this->assertInstanceOf(LocalFilesystem::class, $manager->disk());
        $this->assertSame($manager->disk(), $manager->disk('local'));
        $this->assertDirectoryExists("{$base}/storage", 'relative roots are below the base path');
        $this->assertSame('https://cdn.example/a.png', $manager->disk('memory')->url('a.png'));

        rmdir("{$base}/storage");
        rmdir($base);
    }

    public function testTheDefaultDiskIsInMemory(): void
    {
        $this->assertInstanceOf(InMemoryFilesystem::class, new StorageManager(new Configuration(), '/app')->disk());
    }

    public function testUnknownDisksAndDriversAreRejected(): void
    {
        $manager = new StorageManager(new Configuration(['filesystem' => ['disks' => ['s3' => ['driver' => 's3']]]]), '/app');

        foreach (['missing' => 'Disk "missing" is not configured.', 's3' => "unknown driver: 's3'"] as $disk => $message) {
            try {
                $manager->disk($disk);
                $this->fail("Expected: {$message}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function testTheProviderRegistersTheDefaultDisk(): void
    {
        $container = Application::configure(sys_get_temp_dir() . '/zana-fs-app')->withProviders(FilesystemServiceProvider::class)->build()->container();

        $this->assertSame($container->get(StorageManager::class)->disk(), $container->get(FilesystemInterface::class));
        $this->assertInstanceOf(UploadedFileStore::class, $container->get(UploadedFileStore::class));
    }

    // ── UploadedFileStore ──────────────────────────────────────────────────

    public function testUploadsAreStoredUnderRandomNames(): void
    {
        $disk = new InMemoryFilesystem();
        $path = (new UploadedFileStore())->store($this->upload('../../Scan Result.PNG', base64_decode(self::PNG, true) ?: ''), $disk, '/patients/42/');

        $this->assertMatchesRegularExpression('#^patients/42/[0-9a-f]{32}\.png$#', $path);
        $this->assertSame('image/png', $disk->mimeType($path));
    }

    public function testUnsafeExtensionsAreDropped(): void
    {
        $path = (new UploadedFileStore())->store($this->upload('report.ph p', 'x'), new InMemoryFilesystem(), 'uploads');

        $this->assertMatchesRegularExpression('#^uploads/[0-9a-f]{32}$#', $path);
    }

    public function testUploadErrorsSizeAndTypeAreChecked(): void
    {
        $store = new UploadedFileStore();
        $disk = new InMemoryFilesystem();

        foreach ([
            [fn () => $store->store($this->upload('a.txt', 'x', UPLOAD_ERR_PARTIAL), $disk, 'u'), 'error code 3'],
            [fn () => $store->store($this->upload('a.txt', '12345'), $disk, 'u', maxBytes: 4), 'is 5 bytes; the maximum is 4'],
            [fn () => $store->store($this->upload('a.txt', 'plain text'), $disk, 'u', allowedMimeTypes: ['image/png']), 'Files of type text/plain are not allowed.'],
        ] as [$call, $message]) {
            try {
                $call();
                $this->fail("Expected: {$message}");
            } catch (FilesystemException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }

        $this->assertSame([], $disk->listContents('u'), 'rejected files are not kept');
    }

    private function upload(string $name, string $contents, int $error = UPLOAD_ERR_OK): UploadedFile
    {
        return new UploadedFile(Stream::create($contents), strlen($contents), $error, $name, 'application/octet-stream');
    }
}
