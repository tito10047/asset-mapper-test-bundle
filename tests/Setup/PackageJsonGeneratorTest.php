<?php

namespace Tito10047\AssetMapperTestBundle\Tests\Setup;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\AssetMapperTestBundle\Setup\PackageJsonGenerator;
use Tito10047\AssetMapperTestBundle\Setup\PackageJsonGenerator\Runner;
use Tito10047\AssetMapperTestBundle\Setup\PackageJsonGenerator\Variant;

class PackageJsonGeneratorTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tmpDir = sys_get_temp_dir() . '/pkgjson_gen_' . uniqid();
        $this->fs->mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    private function gen(): PackageJsonGenerator
    {
        return new PackageJsonGenerator($this->fs);
    }

    public function testSymlinkNodeRunnerProducesExpectedPackageJson(): void
    {
        $result = $this->gen()->generate($this->tmpDir, Variant::Symlink, Runner::Node);

        $this->assertFileExists($this->tmpDir . '/package.json');
        $data = json_decode(file_get_contents($this->tmpDir . '/package.json'), true);
        $this->assertSame('module', $data['type']);
        $this->assertStringContainsString('asset-mapper-test:setup', $data['scripts']['pretest']);
        $this->assertStringStartsWith('node ', $data['scripts']['test']);
        $this->assertStringContainsString('--test', $data['scripts']['test']);
        $this->assertStringNotContainsString('register.mjs', $data['scripts']['test']);
        $this->assertArrayHasKey('happy-dom', $data['devDependencies']);
        $this->assertTrue($result->packageJsonCreated);
    }

    public function testLoaderNodeRunnerUsesRegisterImport(): void
    {
        $this->gen()->generate($this->tmpDir, Variant::Loader, Runner::Node);

        $data = json_decode(file_get_contents($this->tmpDir . '/package.json'), true);
        $this->assertStringContainsString('asset-mapper-test:export', $data['scripts']['pretest']);
        $this->assertStringContainsString('--import', $data['scripts']['test']);
        $this->assertStringContainsString('register.mjs', $data['scripts']['test']);
        $this->assertStringContainsString('--test', $data['scripts']['test']);
    }

    public function testSymlinkVitestRunnerUsesVitest(): void
    {
        $this->gen()->generate($this->tmpDir, Variant::Symlink, Runner::Vitest);

        $data = json_decode(file_get_contents($this->tmpDir . '/package.json'), true);
        $this->assertSame('vitest run', $data['scripts']['test']);
        $this->assertArrayHasKey('vitest', $data['devDependencies']);
    }

    public function testLoaderVitestRunnerStillExports(): void
    {
        $this->gen()->generate($this->tmpDir, Variant::Loader, Runner::Vitest);

        $data = json_decode(file_get_contents($this->tmpDir . '/package.json'), true);
        $this->assertStringContainsString('asset-mapper-test:export', $data['scripts']['pretest']);
        $this->assertSame('vitest run', $data['scripts']['test']);
    }

    public function testSetupMjsGeneratedOnlyForNodeRunner(): void
    {
        $result = $this->gen()->generate($this->tmpDir, Variant::Symlink, Runner::Node);

        $setupPath = $this->tmpDir . '/tests/js/setup.mjs';
        $this->assertFileExists($setupPath);
        $this->assertTrue($result->setupMjsCreated);

        $content = file_get_contents($setupPath);
        $this->assertStringContainsString("import { Window } from 'happy-dom'", $content);
        $this->assertStringContainsString('globalThis.window = window', $content);
        $this->assertStringContainsString('globalThis.document = window.document', $content);
        $this->assertStringContainsString('globalThis.HTMLElement = window.HTMLElement', $content);
        $this->assertStringContainsString('globalThis.Event = window.Event', $content);
    }

    public function testSetupMjsNotGeneratedForVitestRunner(): void
    {
        $result = $this->gen()->generate($this->tmpDir, Variant::Loader, Runner::Vitest);

        $this->assertFileDoesNotExist($this->tmpDir . '/tests/js/setup.mjs');
        $this->assertFalse($result->setupMjsCreated);
    }

    public function testNodeRunnerIncludesSetupMjsImportFlag(): void
    {
        $this->gen()->generate($this->tmpDir, Variant::Symlink, Runner::Node);

        $data = json_decode(file_get_contents($this->tmpDir . '/package.json'), true);
        $this->assertStringContainsString('tests/js/setup.mjs', $data['scripts']['test']);
    }

    public function testExistingPackageJsonIsNotOverwrittenWithoutForce(): void
    {
        $this->fs->dumpFile($this->tmpDir . '/package.json', '{"name":"existing"}');

        $result = $this->gen()->generate($this->tmpDir, Variant::Symlink, Runner::Node);

        $this->assertFalse($result->packageJsonCreated);
        $data = json_decode(file_get_contents($this->tmpDir . '/package.json'), true);
        $this->assertSame('existing', $data['name']);
    }

    public function testForceOverwritesExistingPackageJson(): void
    {
        $this->fs->dumpFile($this->tmpDir . '/package.json', '{"name":"existing"}');

        $result = $this->gen()->generate($this->tmpDir, Variant::Symlink, Runner::Node, force: true);

        $this->assertTrue($result->packageJsonCreated);
        $data = json_decode(file_get_contents($this->tmpDir . '/package.json'), true);
        $this->assertArrayNotHasKey('name', $data);
        $this->assertSame('module', $data['type']);
    }

    public function testExistingSetupMjsIsNotOverwritten(): void
    {
        $this->fs->dumpFile($this->tmpDir . '/tests/js/setup.mjs', '// custom setup');

        $result = $this->gen()->generate($this->tmpDir, Variant::Symlink, Runner::Node);

        $this->assertFalse($result->setupMjsCreated);
        $this->assertSame('// custom setup', file_get_contents($this->tmpDir . '/tests/js/setup.mjs'));
    }
}
