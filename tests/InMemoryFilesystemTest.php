<?php

declare(strict_types=1);

namespace Zana\Filesystem\Tests;

use Zana\Filesystem\FilesystemInterface;
use Zana\Filesystem\InMemoryFilesystem;

final class InMemoryFilesystemTest extends FilesystemContractTestCase
{
    public function testTheClockSetsModificationTimes(): void
    {
        $disk = new InMemoryFilesystem(clock: static fn (): int => 1_700_000_000);
        $disk->write('a.txt', 'a');

        $this->assertSame(1_700_000_000, $disk->lastModified('a.txt'));
    }

    protected function disk(?string $url = null): FilesystemInterface
    {
        return new InMemoryFilesystem($url);
    }
}
