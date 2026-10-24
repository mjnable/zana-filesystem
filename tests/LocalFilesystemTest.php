<?php

declare(strict_types=1);

namespace Zana\Filesystem\Tests;

use PHPUnit\Framework\Attributes\After;
use Zana\Filesystem\FilesystemInterface;
use Zana\Filesystem\LocalFilesystem;
use Zana\Filesystem\PathTraversalException;

final class LocalFilesystemTest extends FilesystemContractTestCase
{
    /** @var list<string> */
    private array $directories = [];

    #[After]
    public function removeDirectories(): void
    {
        foreach ($this->directories as $directory) {
            self::remove($directory);
        }
    }

    public function testFilesLandInTheRoot(): void
    {
        $root = $this->root();
        (new LocalFilesystem($root))->write('nested/file.txt', 'on disk');

        $this->assertSame('on disk', file_get_contents("{$root}/nested/file.txt"));
        $this->assertSame([], glob("{$root}/nested/*.tmp") ?: [], 'writes are atomic');
    }

    public function testSymlinksCannotEscapeTheRoot(): void
    {
        $root = $this->root();
        $outside = $this->root();
        mkdir($outside, 0777, true);
        file_put_contents("{$outside}/secret.txt", 'secret');

        if (!@symlink($outside, "{$root}/link")) {
            $this->markTestSkipped('Symbolic links cannot be created here (Windows without the privilege).');
        }

        $disk = new LocalFilesystem($root);

        $this->expectException(PathTraversalException::class);
        $disk->read('link/secret.txt');
    }

    protected function disk(?string $url = null): FilesystemInterface
    {
        return new LocalFilesystem($this->root(), $url);
    }

    private function root(): string
    {
        $directory = sys_get_temp_dir() . '/zana-fs-' . bin2hex(random_bytes(6));
        $this->directories[] = $directory;

        return $directory;
    }

    private static function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
