<?php

declare(strict_types=1);

namespace Zana\Filesystem\Tests;

use PHPUnit\Framework\TestCase;
use Zana\Filesystem\FilesystemException;
use Zana\Filesystem\FilesystemInterface;
use Zana\Filesystem\PathTraversalException;

/**
 * The FilesystemInterface contract, run against each disk.
 */
abstract class FilesystemContractTestCase extends TestCase
{
    abstract protected function disk(?string $url = null): FilesystemInterface;

    public function testWriteReadExistsDelete(): void
    {
        $disk = $this->disk();

        $this->assertFalse($disk->exists('reports/bmi.csv'));
        $disk->write('reports/bmi.csv', "patient,bmi\n42,22.5\n");

        $this->assertTrue($disk->exists('reports/bmi.csv'));
        $this->assertSame("patient,bmi\n42,22.5\n", $disk->read('reports/bmi.csv'));
        $this->assertSame(20, $disk->size('reports/bmi.csv'));
        $this->assertEqualsWithDelta(time(), $disk->lastModified('reports/bmi.csv'), 5);

        $disk->delete('reports/bmi.csv');
        $disk->delete('reports/bmi.csv');
        $this->assertFalse($disk->exists('reports/bmi.csv'));
    }

    public function testStreams(): void
    {
        $disk = $this->disk();
        $source = fopen('php://memory', 'w+b');
        $this->assertNotFalse($source);
        fwrite($source, 'streamed');
        rewind($source);

        $disk->write('a/stream.txt', $source);
        $stream = $disk->readStream('a/stream.txt');

        $this->assertSame('streamed', stream_get_contents($stream));
        fclose($stream);
        fclose($source);
    }

    public function testCopyAndMove(): void
    {
        $disk = $this->disk();
        $disk->write('original.txt', 'data');

        $disk->copy('original.txt', 'copies/copy.txt');
        $disk->move('original.txt', 'moved/original.txt');

        $this->assertSame('data', $disk->read('copies/copy.txt'));
        $this->assertSame('data', $disk->read('moved/original.txt'));
        $this->assertFalse($disk->exists('original.txt'));
    }

    public function testListingIsSortedAndFilesOnly(): void
    {
        $disk = $this->disk();
        foreach (['b.txt', 'a.txt', 'docs/x.txt', 'docs/deep/y.txt'] as $path) {
            $disk->write($path, $path);
        }

        $this->assertSame(['a.txt', 'b.txt'], $disk->listContents());
        $this->assertSame(['docs/x.txt'], $disk->listContents('docs'));
        $this->assertSame(['docs/deep/y.txt', 'docs/x.txt'], $disk->listContents('/docs/', recursive: true));
        $this->assertSame([], $disk->listContents('missing'));
    }

    public function testDirectories(): void
    {
        $disk = $this->disk();
        $disk->createDirectory('empty/dir');
        $disk->write('tree/a.txt', 'a');
        $disk->write('tree/sub/b.txt', 'b');
        $disk->write('treehouse.txt', 'not inside tree');

        $disk->deleteDirectory('tree');
        $disk->deleteDirectory('missing');

        $this->assertFalse($disk->exists('tree/a.txt'));
        $this->assertFalse($disk->exists('tree/sub/b.txt'));
        $this->assertTrue($disk->exists('treehouse.txt'));

        $this->expectException(FilesystemException::class);
        $disk->deleteDirectory('/');
    }

    public function testPathsAreNormalised(): void
    {
        $disk = $this->disk();
        $disk->write('\\a//b/./c/../d.txt', 'normalised');

        $this->assertSame('normalised', $disk->read('a/b/d.txt'));
        $this->assertSame(['a/b/d.txt'], $disk->listContents('a', recursive: true));
    }

    public function testTraversalIsRejected(): void
    {
        $disk = $this->disk();

        foreach (['../escape.txt', 'a/../../escape.txt', "file\0.txt"] as $path) {
            try {
                $disk->write($path, 'x');
                $this->fail("{$path} must be rejected.");
            } catch (PathTraversalException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testMissingFiles(): void
    {
        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('File not found: missing/file.txt.');

        $this->disk()->read('/missing//file.txt');
    }

    public function testMimeTypes(): void
    {
        $disk = $this->disk();
        $disk->write('image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true) ?: '');
        $disk->write('data.csv', "a,b\n1,2\n");
        $disk->write('blob', "\x00\x01\x02");

        $this->assertSame('image/png', $disk->mimeType('image.png'));
        $this->assertSame('text/csv', $disk->mimeType('data.csv'));
        $this->assertSame('application/octet-stream', $disk->mimeType('blob'));
    }

    public function testUrls(): void
    {
        $this->assertSame('https://bmi.example/files/patients/42/scan%201.png', $this->disk('https://bmi.example/files/')->url('/patients/42/scan 1.png'));

        $this->expectException(FilesystemException::class);
        $this->disk()->url('x');
    }
}
