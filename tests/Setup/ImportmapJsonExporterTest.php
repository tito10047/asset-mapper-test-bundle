<?php

namespace Tito10047\AssetMapperTestBundle\Tests\Setup;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\AssetMapperTestBundle\Setup\ImportmapJsonExporter;

class ImportmapJsonExporterTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $filesystem;
    private string $projectDir;
    private string $vendorDir;
    private string $importmapPath;
    private string $outputPath;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmpDir = sys_get_temp_dir() . '/importmap_exporter_test_' . uniqid();
        $this->projectDir = $this->tmpDir . '/project';
        $this->vendorDir = $this->projectDir . '/assets/vendor';
        $this->importmapPath = $this->projectDir . '/importmap.php';
        $this->outputPath = $this->projectDir . '/var/asset-mapper-test/importmap.json';

        $this->filesystem->mkdir([$this->vendorDir, $this->projectDir . '/assets']);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tmpDir);
    }

    private function writeImportmap(array $data): void
    {
        $this->filesystem->dumpFile($this->importmapPath, '<?php return ' . var_export($data, true) . ';');
    }

    private function makeExporter(): ImportmapJsonExporter
    {
        return new ImportmapJsonExporter(
            $this->importmapPath,
            $this->vendorDir,
            $this->projectDir,
            $this->filesystem,
            new NullLogger(),
            $this->outputPath,
        );
    }

    public function testExportsResolvedEntriesToJson(): void
    {
        $this->filesystem->dumpFile($this->projectDir . '/assets/app.js', '');
        $this->filesystem->mkdir($this->vendorDir . '/happy-dom');
        $this->filesystem->dumpFile($this->vendorDir . '/happy-dom/index.js', '');
        $this->filesystem->mkdir($this->vendorDir . '/@scope/pkg');
        $this->filesystem->dumpFile($this->vendorDir . '/@scope/pkg/index.js', '');

        $this->writeImportmap([
            'app' => ['path' => './assets/app.js'],
            'happy-dom' => ['version' => '1.0'],
            '@scope/pkg' => ['version' => '2.0'],
            'missing' => ['version' => '9.9'],
        ]);

        $result = $this->makeExporter()->export();

        $this->assertSame(3, $result->exported);
        $this->assertSame(1, $result->skipped);
        $this->assertFileExists($this->outputPath);

        $data = json_decode(file_get_contents($this->outputPath), true);
        $this->assertSame($this->projectDir, $data['projectDir']);
        $this->assertSame($this->vendorDir, $data['vendorDir']);
        $this->assertArrayHasKey('app', $data['entries']);
        $this->assertArrayHasKey('happy-dom', $data['entries']);
        $this->assertArrayHasKey('@scope/pkg', $data['entries']);
        $this->assertArrayNotHasKey('missing', $data['entries']);

        $this->assertSame('file', $data['entries']['app']['kind']);
        $this->assertSame('dir', $data['entries']['happy-dom']['kind']);
        $this->assertSame('dir', $data['entries']['@scope/pkg']['kind']);
    }

    public function testThrowsWhenImportmapMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('importmap.php not found');
        $this->makeExporter()->export();
    }

    public function testThrowsWhenImportmapDoesNotReturnArray(): void
    {
        $this->filesystem->dumpFile($this->importmapPath, '<?php return 42;');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('importmap.php must return an array');
        $this->makeExporter()->export();
    }
}
