<?php

namespace Tito10047\AssetMapperTestBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\AssetMapperTestBundle\Command\SetupNodeModulesCommand;
use Tito10047\AssetMapperTestBundle\Setup\NodeModulesSetup;

class SetupNodeModulesCommandTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $filesystem;
    private string $projectDir;
    private string $vendorDir;
    private string $importmapPath;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmpDir = sys_get_temp_dir() . '/command_test_' . uniqid();
        $this->projectDir = $this->tmpDir . '/project';
        $this->vendorDir = $this->projectDir . '/assets/vendor';
        $this->importmapPath = $this->projectDir . '/importmap.php';

        $this->filesystem->mkdir([$this->vendorDir, $this->projectDir . '/assets']);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tmpDir);
    }

    private function makeCommand(): SetupNodeModulesCommand
    {
        $setup = new NodeModulesSetup(
            $this->importmapPath,
            $this->vendorDir,
            $this->projectDir,
            $this->filesystem,
            new NullLogger(),
        );
        return new SetupNodeModulesCommand($setup);
    }

    private function writeImportmap(array $data): void
    {
        $export = var_export($data, true);
        $this->filesystem->dumpFile(
            $this->importmapPath,
            "<?php\nreturn {$export};\n"
        );
    }

    public function testSuccessfulRunExitsZero(): void
    {
        $jsFile = $this->projectDir . '/assets/app.js';
        $this->filesystem->dumpFile($jsFile, 'export default {}');
        $this->writeImportmap(['app' => ['path' => './assets/app.js']]);

        $tester = new CommandTester($this->makeCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Created: 1', $tester->getDisplay());
        $this->assertStringContainsString('Skipped: 0', $tester->getDisplay());
    }

    public function testMissingImportmapExitsOne(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
    }

    public function testRunWithSkippedPackagesExitsZero(): void
    {
        $this->writeImportmap(['nonexistent' => ['version' => '1.0.0']]);

        $tester = new CommandTester($this->makeCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Skipped: 1', $tester->getDisplay());
    }
}
