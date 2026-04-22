<?php

namespace Tito10047\AssetMapperTestBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\AssetMapperTestBundle\Command\InitCommand;
use Tito10047\AssetMapperTestBundle\Setup\PackageJsonGenerator;

class InitCommandTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tmpDir = sys_get_temp_dir() . '/init_cmd_' . uniqid();
        $this->fs->mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    private function tester(): CommandTester
    {
        $cmd = new InitCommand(new PackageJsonGenerator($this->fs), $this->tmpDir);
        return new CommandTester($cmd);
    }

    public function testNonInteractiveWithOptions(): void
    {
        $tester = $this->tester();
        $exit = $tester->execute(
            ['--variant' => 'loader', '--runner' => 'node'],
            ['interactive' => false],
        );

        $this->assertSame(0, $exit);
        $this->assertFileExists($this->tmpDir . '/package.json');
        $this->assertFileExists($this->tmpDir . '/tests/js/setup.mjs');
        $data = json_decode(file_get_contents($this->tmpDir . '/package.json'), true);
        $this->assertStringContainsString('asset-mapper-test:export', $data['scripts']['pretest']);
    }

    public function testInteractiveSymlinkVitest(): void
    {
        $tester = $this->tester();
        $tester->setInputs(['symlink', 'vitest']);
        $exit = $tester->execute([]);

        $this->assertSame(0, $exit);
        $data = json_decode(file_get_contents($this->tmpDir . '/package.json'), true);
        $this->assertStringContainsString('asset-mapper-test:setup', $data['scripts']['pretest']);
        $this->assertSame('vitest run', $data['scripts']['test']);
        $this->assertFileDoesNotExist($this->tmpDir . '/tests/js/setup.mjs');
    }

    public function testRejectsInvalidVariant(): void
    {
        $tester = $this->tester();
        $exit = $tester->execute(
            ['--variant' => 'nonsense', '--runner' => 'node'],
            ['interactive' => false],
        );

        $this->assertSame(1, $exit);
    }

    public function testSkipsExistingPackageJsonWithoutForce(): void
    {
        $this->fs->dumpFile($this->tmpDir . '/package.json', '{"name":"existing"}');

        $tester = $this->tester();
        $exit = $tester->execute(
            ['--variant' => 'symlink', '--runner' => 'node'],
            ['interactive' => false],
        );

        $this->assertSame(0, $exit);
        $data = json_decode(file_get_contents($this->tmpDir . '/package.json'), true);
        $this->assertSame('existing', $data['name']);
        $this->assertStringContainsString('already exists', $tester->getDisplay());
    }

    public function testForceOverwritesPackageJson(): void
    {
        $this->fs->dumpFile($this->tmpDir . '/package.json', '{"name":"existing"}');

        $tester = $this->tester();
        $exit = $tester->execute(
            ['--variant' => 'symlink', '--runner' => 'node', '--force' => true],
            ['interactive' => false],
        );

        $this->assertSame(0, $exit);
        $data = json_decode(file_get_contents($this->tmpDir . '/package.json'), true);
        $this->assertSame('module', $data['type']);
    }
}
