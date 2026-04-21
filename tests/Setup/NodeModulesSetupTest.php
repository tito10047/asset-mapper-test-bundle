<?php

namespace Tito10047\AssetMapperTestBundle\Tests\Setup;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\AssetMapperTestBundle\Setup\NodeModulesSetup;

class NodeModulesSetupTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $filesystem;
    private string $projectDir;
    private string $vendorDir;
    private string $importmapPath;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmpDir = sys_get_temp_dir() . '/node_modules_setup_test_' . uniqid();
        $this->projectDir = $this->tmpDir . '/project';
        $this->vendorDir = $this->projectDir . '/assets/vendor';
        $this->importmapPath = $this->projectDir . '/importmap.php';

        $this->filesystem->mkdir([
            $this->vendorDir,
            $this->projectDir . '/assets',
        ]);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tmpDir);
    }

    private function makeSetup(): NodeModulesSetup
    {
        return new NodeModulesSetup(
            $this->importmapPath,
            $this->vendorDir,
            $this->projectDir,
            $this->filesystem,
            new NullLogger(),
        );
    }

    private function writeImportmap(array $data): void
    {
        $export = var_export($data, true);
        $this->filesystem->dumpFile(
            $this->importmapPath,
            "<?php\nreturn {$export};\n"
        );
    }

    public function testThrowsWhenImportmapNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/importmap\.php not found/');

        $this->makeSetup()->run();
    }

    public function testCreatesNodeModulesDirAndPackageJson(): void
    {
        $this->writeImportmap([]);

        $this->makeSetup()->run();

        $nodeModules = $this->projectDir . '/node_modules';
        $this->assertDirectoryExists($nodeModules);
        $this->assertFileExists($nodeModules . '/package.json');

        $data = json_decode(file_get_contents($nodeModules . '/package.json'), true);
        $this->assertSame('module', $data['type']);
    }

    public function testReturnsCorrectCountsForMixedImportmap(): void
    {
        $jsFile = $this->projectDir . '/assets/app.js';
        $this->filesystem->dumpFile($jsFile, 'export default {}');

        $pkgDir = $this->vendorDir . '/pitchfinder';
        $this->filesystem->mkdir($pkgDir);
        $this->filesystem->dumpFile($pkgDir . '/pitchfinder.js', 'export default {}');

        $this->writeImportmap([
            'app' => ['path' => './assets/app.js'],
            'pitchfinder' => ['version' => '2.3.4'],
            'nonexistent' => ['version' => '1.0.0'],
        ]);

        $result = $this->makeSetup()->run();

        $this->assertSame(2, $result->created);
        $this->assertSame(1, $result->skipped);
        $this->assertFalse($result->hasErrors());
    }

    public function testSkipsAlreadyCreatedSymlinks(): void
    {
        $jsFile = $this->projectDir . '/assets/app.js';
        $this->filesystem->dumpFile($jsFile, 'export default {}');

        $this->writeImportmap([
            'app' => ['path' => './assets/app.js'],
        ]);

        $setup = $this->makeSetup();
        $setup->run();
        $result = $setup->run();

        $this->assertSame(0, $result->created);
        $this->assertSame(1, $result->skipped);
    }

    public function testHandlesScopedPackages(): void
    {
        $pkgDir = $this->vendorDir . '/@tonejs/midi';
        $this->filesystem->mkdir($pkgDir);
        $this->filesystem->dumpFile($pkgDir . '/index.js', 'export default {}');

        $this->writeImportmap([
            '@tonejs/midi' => ['version' => '2.0.28'],
        ]);

        $result = $this->makeSetup()->run();

        $this->assertSame(1, $result->created);
        $this->assertTrue(is_link($this->projectDir . '/node_modules/@tonejs/midi'));
    }
}
