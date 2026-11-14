<?php

declare(strict_types=1);

namespace Zana\Filesystem;

use Zana\Configuration\ConfigurationInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Container\Contracts\ServiceResolverInterface;
use Zana\Core\ApplicationInterface;
use Zana\Core\Inspection\ApplicationCheck;
use Zana\Core\ServiceProvider;

/**
 * Registers StorageManager, the default disk as FilesystemInterface, UploadedFileStore, and the
 * security.public_disk check (2.21).
 */
final class FilesystemServiceProvider extends ServiceProvider
{
    public function register(ServiceCollectionInterface $services): void
    {
        $services
            ->singleton(StorageManager::class, static fn (ServiceResolverInterface $r): StorageManager => new StorageManager(
                $r->get(ConfigurationInterface::class),
                $r->get(ApplicationInterface::class)->basePath(),
            ))->uses(ConfigurationInterface::class, ApplicationInterface::class)
            ->singleton(FilesystemInterface::class, static fn (ServiceResolverInterface $r): FilesystemInterface => $r->get(StorageManager::class)->disk())->uses(StorageManager::class)
            ->singleton(UploadedFileStore::class)
            ->singleton(Inspection\PublicDiskCheck::class)->tag(ApplicationCheck::TAG, Inspection\PublicDiskCheck::class);
    }

    public function origin(): string
    {
        return 'package:mj-zana/filesystem';
    }
}
