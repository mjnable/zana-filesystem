<?php

declare(strict_types=1);

namespace Zana\Filesystem\Tests;

use PHPUnit\Framework\TestCase;
use Zana\Core\Application;
use Zana\Core\Inspection\ApplicationCheck;
use Zana\Core\Inspection\Finding;
use Zana\Core\Inspection\Severity;
use Zana\Filesystem\FilesystemServiceProvider;
use Zana\Filesystem\Inspection\PublicDiskCheck;

/**
 * 2.21, FE-68: a local disk inside the web root is served file by file by
 * the web server, uploads included. Always a warning: public images are a
 * reason to do it on purpose, and a disk with a "url" says so.
 */
final class PublicDiskCheckTest extends TestCase
{
    public function testADiskInsideTheWebRootIsReported(): void
    {
        $base = str_replace('\\', '/', sys_get_temp_dir()) . '/zana-fs-public';
        $findings = $this->findings($base, [
            'uploads' => ['driver' => 'local', 'root' => 'public/uploads'],                 // reported
            'sneaky' => ['driver' => 'local', 'root' => 'var/../public/./files'],           // reported: the same folder
            'absolute' => ['driver' => 'local', 'root' => "{$base}/public"],                // reported: the web root itself
            'cased' => ['driver' => 'local', 'root' => PHP_OS_FAMILY === 'Windows' ? 'PUBLIC/x' : 'public/x'],
            'images' => ['driver' => 'local', 'root' => 'public/images', 'url' => 'https://example.com/images'],   // on purpose
            'private' => ['driver' => 'local', 'root' => 'var/storage'],
            'lookalike' => ['driver' => 'local', 'root' => 'public-files'],                 // not inside public/
            'memory' => ['driver' => 'memory'],
        ]);

        $this->assertSame(['uploads', 'sneaky', 'absolute', 'cased'], array_map(static fn (Finding $f): string => explode('"', $f->message)[1], $findings));
        $this->assertSame('security.public_disk', $findings[0]->id);
        $this->assertSame(Severity::Warning, $findings[0]->severity);
        $this->assertTrue($findings[0]->security);
    }

    public function testItStaysAWarningInStrictMode(): void
    {
        $findings = $this->findings(sys_get_temp_dir() . '/zana-fs-public', ['uploads' => ['driver' => 'local', 'root' => 'public/uploads']], strict: true);

        $this->assertSame(Severity::Warning, $findings[0]->severity);
    }

    /**
     * @param array<string, array<string, string>> $disks
     * @return list<Finding>
     */
    private function findings(string $base, array $disks, bool $strict = false): array
    {
        $app = Application::configure($base)->withEnvironment('testing')
            ->withConfiguration(['filesystem.disks' => $disks, 'security.strict' => $strict])
            ->withProviders(FilesystemServiceProvider::class)
            ->build();
        foreach ($app->container()->tagged(ApplicationCheck::TAG) as $check) {
            if ($check instanceof PublicDiskCheck) {
                return $check->findings($app);
            }
        }
        self::fail('PublicDiskCheck is not tagged.');
    }
}
