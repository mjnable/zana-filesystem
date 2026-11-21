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

    /** @var list<string> Links made by a test, removed first: walking into one would delete what it points to */
    private array $links = [];

    #[After]
    public function removeDirectories(): void
    {
        foreach ($this->links as $link) {
            is_link($link) ? @unlink($link) : @rmdir($link);   // a junction is removed with rmdir(), which leaves its target
        }
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
        mkdir($root, 0777, true);
        $outside = $this->root();
        mkdir($outside, 0777, true);
        file_put_contents("{$outside}/secret.txt", 'secret');

        $this->link($outside, "{$root}/link");   // a junction on Windows without the privilege (2.33)

        $disk = new LocalFilesystem($root);

        $this->expectException(PathTraversalException::class);
        $disk->read('link/secret.txt');
    }

    // ── 2.33: a disk that does not follow links ────────────────────────────

    public function testADiskThatFollowsNoLinksRefusesEveryPathThroughOne(): void
    {
        $root = $this->root();
        mkdir("{$root}/real", 0777, true);
        file_put_contents("{$root}/real/report.txt", 'kept');
        $this->link("{$root}/real", "{$root}/inside");   // a link inside the root, to inside the root

        $following = new LocalFilesystem($root);
        $this->assertSame('kept', $following->read('inside/report.txt'), 'followed by default, as before 2.33');
        $this->assertSame(['report.txt'], array_map('basename', $following->listContents('inside')));

        $strict = new LocalFilesystem($root, followLinks: false);
        $this->assertSame('kept', $strict->read('real/report.txt'), 'the real path still works');
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->assertSame('kept', $strict->read('REAL/report.txt'), 'Windows: another case is the same place, not a link');
        }
        foreach ([
            'read' => static fn () => $strict->read('inside/report.txt'),
            'exists' => static fn () => $strict->exists('inside/report.txt'),
            'write' => static fn () => $strict->write('inside/new.txt', 'planted'),
            'write deeper' => static fn () => $strict->write('inside/a/b/new.txt', 'planted'),
            'delete' => static fn () => $strict->delete('inside/report.txt'),
            'copy from' => static fn () => $strict->copy('inside/report.txt', 'real/copy.txt'),
            'copy to' => static fn () => $strict->copy('real/report.txt', 'inside/copy.txt'),
            'move' => static fn () => $strict->move('real/report.txt', 'inside/moved.txt'),
            'list' => static fn () => $strict->listContents('inside'),
            'the link itself' => static fn () => $strict->deleteDirectory('inside'),
        ] as $operation => $call) {
            try {
                $call();
                $this->fail("{$operation} went through the link.");
            } catch (PathTraversalException $e) {
                $this->assertStringContainsString('goes through a symbolic link', $e->getMessage(), $operation);
            }
        }
        $this->assertFileDoesNotExist("{$root}/real/new.txt");
        $this->assertSame(['real/report.txt'], $strict->listContents('', true), 'a listing leaves what is behind a link out');
        $this->assertSame('kept', file_get_contents("{$root}/real/report.txt"), 'nothing was deleted through it');
    }

    /**
     * Links to files, and to nothing, need a real symbolic link: they run on Linux (CI) and are skipped on Windows
     * without the privilege, where a junction is a directory only.
     */
    public function testFileLinksAndLinksToNothingAreRefusedToo(): void
    {
        $root = $this->root();
        mkdir("{$root}/real", 0777, true);
        file_put_contents("{$root}/real/report.txt", 'kept');
        if (!@symlink("{$root}/real/report.txt", "{$root}/alias.txt") || !@symlink("{$root}/gone", "{$root}/dangling")) {
            $this->markTestSkipped('Symbolic links to files cannot be created here.');
        }
        $this->links[] = "{$root}/alias.txt";
        $this->links[] = "{$root}/dangling";
        $strict = new LocalFilesystem($root, followLinks: false);

        $this->assertSame(['real/report.txt'], $strict->listContents('', true), 'a link to a file is left out of a listing');
        foreach (['alias.txt', 'dangling'] as $path) {
            try {
                $strict->write($path, 'planted');
                $this->fail("Wrote through {$path}.");
            } catch (PathTraversalException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('kept', file_get_contents("{$root}/real/report.txt"));
        $this->assertFileDoesNotExist("{$root}/gone");
    }

    public function testAStorageDiskReadsFollowLinks(): void
    {
        $root = $this->root();
        mkdir("{$root}/real", 0777, true);
        file_put_contents("{$root}/real/a.txt", 'a');
        $this->link("{$root}/real", "{$root}/inside");

        $manager = new \Zana\Filesystem\StorageManager(new \Zana\Configuration\Configuration(['filesystem' => ['disks' => [
            'uploads' => ['driver' => 'local', 'root' => $root, 'follow_links' => false],
            'files' => ['driver' => 'local', 'root' => $root],
        ]]]), sys_get_temp_dir());

        $this->assertSame('a', $manager->disk('files')->read('inside/a.txt'));
        $this->expectException(PathTraversalException::class);
        $manager->disk('uploads')->read('inside/a.txt');
    }

    /**
     * A symbolic link, or on Windows without the privilege, a junction, which PHP does not report as a link but
     * realpath() resolves: the disk must refuse both.
     */
    private function link(string $target, string $link): void
    {
        if (!@symlink($target, $link)) {
            if (DIRECTORY_SEPARATOR !== '\\') {
                $this->markTestSkipped('Symbolic links cannot be created here.');
            }
            exec('cmd /c mklink /J ' . escapeshellarg(str_replace('/', '\\', $link)) . ' ' . escapeshellarg(str_replace('/', '\\', $target)) . ' 2>NUL', $output, $code);
            if ($code !== 0) {
                $this->markTestSkipped('Neither a symbolic link nor a junction can be created here.');
            }
        }
        $this->links[] = $link;
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
