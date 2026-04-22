<?php

namespace Tito10047\AssetMapperTestBundle\Tests\Setup;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\AssetMapperTestBundle\Setup\ImportmapEntry;
use Tito10047\AssetMapperTestBundle\Setup\ImportmapResolver;

class ImportmapResolverTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $filesystem;
    private string $projectDir;
    private string $vendorDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmpDir = sys_get_temp_dir() . '/importmap_resolver_test_' . uniqid();
        $this->projectDir = $this->tmpDir . '/project';
        $this->vendorDir = $this->projectDir . '/assets/vendor';

        $this->filesystem->mkdir([$this->vendorDir, $this->projectDir . '/assets']);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tmpDir);
    }

    private function makeResolver(): ImportmapResolver
    {
        return new ImportmapResolver($this->vendorDir, $this->projectDir, new NullLogger());
    }

    public function testResolvesLocalPathEntryAsFile(): void
    {
        $jsFile = $this->projectDir . '/assets/app.js';
        $this->filesystem->dumpFile($jsFile, 'export default {}');

        $entry = $this->makeResolver()->resolveEntry('app', ['path' => './assets/app.js']);

        $this->assertNotNull($entry);
        $this->assertSame('app', $entry->name);
        $this->assertSame(ImportmapEntry::KIND_FILE, $entry->kind);
        $this->assertSame($jsFile, $entry->path);
    }

    public function testReturnsNullWhenLocalPathMissing(): void
    {
        $this->assertNull($this->makeResolver()->resolveEntry('app', ['path' => './missing.js']));
    }

    public function testReturnsNullWhenVendorEntryMissing(): void
    {
        $this->assertNull($this->makeResolver()->resolveEntry('nope', ['version' => '1.0']));
    }

    public function testResolvesDirectoryWithIndexJsAsDir(): void
    {
        $pkgDir = $this->vendorDir . '/some-package';
        $this->filesystem->mkdir($pkgDir);
        $this->filesystem->dumpFile($pkgDir . '/index.js', 'export default {}');

        $entry = $this->makeResolver()->resolveEntry('some-package', ['version' => '1.0']);

        $this->assertNotNull($entry);
        $this->assertSame(ImportmapEntry::KIND_DIR, $entry->kind);
        $this->assertSame($pkgDir, $entry->path);
    }

    public function testPromotesSingleJsFileInsideDirectoryToFileEntry(): void
    {
        $pkgDir = $this->vendorDir . '/pitchfinder';
        $this->filesystem->mkdir($pkgDir);
        $this->filesystem->dumpFile($pkgDir . '/pitchfinder.js', 'export default {}');

        $entry = $this->makeResolver()->resolveEntry('pitchfinder', ['version' => '2.3']);

        $this->assertNotNull($entry);
        $this->assertSame(ImportmapEntry::KIND_FILE, $entry->kind);
        $this->assertSame($pkgDir . '/pitchfinder.js', $entry->path);
    }

    public function testResolvesScopedPackage(): void
    {
        $pkgDir = $this->vendorDir . '/@tonejs/midi';
        $this->filesystem->mkdir($pkgDir);
        $this->filesystem->dumpFile($pkgDir . '/index.js', 'export default {}');

        $entry = $this->makeResolver()->resolveEntry('@tonejs/midi', ['version' => '2.0']);

        $this->assertNotNull($entry);
        $this->assertSame(ImportmapEntry::KIND_DIR, $entry->kind);
        $this->assertSame($pkgDir, $entry->path);
    }

    public function testResolveIteratesWholeImportmap(): void
    {
        $this->filesystem->dumpFile($this->projectDir . '/assets/app.js', '');
        $this->filesystem->mkdir($this->vendorDir . '/pkg');
        $this->filesystem->dumpFile($this->vendorDir . '/pkg/index.js', '');

        $entries = $this->makeResolver()->resolve([
            'app' => ['path' => './assets/app.js'],
            'pkg' => ['version' => '1.0'],
            'missing' => ['version' => '1.0'],
        ]);

        $this->assertCount(2, $entries);
        $names = array_map(fn(ImportmapEntry $e) => $e->name, $entries);
        $this->assertSame(['app', 'pkg'], $names);
    }
}
