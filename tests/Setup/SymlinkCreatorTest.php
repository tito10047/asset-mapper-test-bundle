<?php

namespace Tito10047\AssetMapperTestBundle\Tests\Setup;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\AssetMapperTestBundle\Setup\SymlinkCreator;
use Tito10047\AssetMapperTestBundle\Setup\SymlinkResult;

class SymlinkCreatorTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $filesystem;
    private string $vendorDir;
    private string $nodeModulesDir;
    private string $projectDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmpDir = sys_get_temp_dir() . '/symlink_creator_test_' . uniqid();
        $this->projectDir = $this->tmpDir . '/project';
        $this->vendorDir = $this->projectDir . '/assets/vendor';
        $this->nodeModulesDir = $this->tmpDir . '/node_modules';

        $this->filesystem->mkdir([
            $this->vendorDir,
            $this->nodeModulesDir,
            $this->projectDir . '/assets',
        ]);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tmpDir);
    }

    private function makeCreator(): SymlinkCreator
    {
        return new SymlinkCreator(
            $this->filesystem,
            $this->vendorDir,
            $this->nodeModulesDir,
            $this->projectDir,
            new NullLogger(),
        );
    }

    public function testCreatesSymlinkForPathEntry(): void
    {
        $jsFile = $this->projectDir . '/assets/app.js';
        $this->filesystem->dumpFile($jsFile, 'export default {}');

        $result = $this->makeCreator()->create('app', ['path' => './assets/app.js']);

        $this->assertSame(SymlinkResult::Created, $result);
        $this->assertFileExists($this->nodeModulesDir . '/app/index.js');
        $this->assertTrue(is_link($this->nodeModulesDir . '/app/index.js'));
    }

    public function testSkipsWhenSymlinkAlreadyExists(): void
    {
        $jsFile = $this->projectDir . '/assets/app.js';
        $this->filesystem->dumpFile($jsFile, 'export default {}');

        $creator = $this->makeCreator();
        $creator->create('app', ['path' => './assets/app.js']);
        $result = $creator->create('app', ['path' => './assets/app.js']);

        $this->assertSame(SymlinkResult::Skipped, $result);
    }

    public function testSkipsWhenSourceDoesNotExist(): void
    {
        $result = $this->makeCreator()->create('nonexistent', ['version' => '1.0.0']);

        $this->assertSame(SymlinkResult::Skipped, $result);
    }

    public function testCreatesSymlinkForVersionedPackageWithSingleJsFile(): void
    {
        $pkgDir = $this->vendorDir . '/pitchfinder';
        $this->filesystem->mkdir($pkgDir);
        $this->filesystem->dumpFile($pkgDir . '/pitchfinder.js', 'export default {}');

        $result = $this->makeCreator()->create('pitchfinder', ['version' => '2.3.4']);

        $this->assertSame(SymlinkResult::Created, $result);
        $this->assertFileExists($this->nodeModulesDir . '/pitchfinder/index.js');
    }

    public function testCreatesSymlinkForDirectoryPackage(): void
    {
        $pkgDir = $this->vendorDir . '/some-package';
        $this->filesystem->mkdir($pkgDir);
        $this->filesystem->dumpFile($pkgDir . '/index.js', 'export default {}');

        $result = $this->makeCreator()->create('some-package', ['version' => '1.0.0']);

        $this->assertSame(SymlinkResult::Created, $result);
        $this->assertTrue(is_link($this->nodeModulesDir . '/some-package'));
    }

    public function testHandlesScopedPackage(): void
    {
        $pkgDir = $this->vendorDir . '/@tonejs/midi';
        $this->filesystem->mkdir($pkgDir);
        $this->filesystem->dumpFile($pkgDir . '/index.js', 'export default {}');

        $result = $this->makeCreator()->create('@tonejs/midi', ['version' => '2.0.28']);

        $this->assertSame(SymlinkResult::Created, $result);
        $this->assertTrue(is_link($this->nodeModulesDir . '/@tonejs/midi'));
    }

    public function testCssEntryNeverClobbersPackageEntryRegardlessOfOrder(): void
    {
        $pkgDir = $this->vendorDir . '/toastr';
        $jsFile = $pkgDir . '/toastr.index.js';
        $this->filesystem->dumpFile($jsFile, 'export default {}');
        $this->filesystem->dumpFile($pkgDir . '/build/toastr.min.css', 'body {}');

        $orders = [
            ['toastr', 'toastr/build/toastr.min.css'],
            ['toastr/build/toastr.min.css', 'toastr'],
        ];

        foreach ($orders as $order) {
            $this->filesystem->remove($this->nodeModulesDir);
            $this->filesystem->mkdir($this->nodeModulesDir);
            $creator = $this->makeCreator();

            foreach ($order as $name) {
                $config = str_ends_with($name, '.css')
                    ? ['version' => '2.1.4', 'type' => 'css']
                    : ['version' => '2.1.4'];
                $creator->create($name, $config);
            }

            $link = $this->nodeModulesDir . '/toastr/index.js';
            $this->assertTrue(is_link($link), 'order: ' . implode(', ', $order));
            $this->assertSame($jsFile, readlink($link), 'order: ' . implode(', ', $order));
        }
    }

    public function testRefusesToWriteThroughSymlinkedPackageDir(): void
    {
        $pkgDir = $this->vendorDir . '/toastr';
        $this->filesystem->dumpFile($pkgDir . '/toastr.index.js', 'export default {}');
        $this->filesystem->symlink($pkgDir, $this->nodeModulesDir . '/toastr');

        $result = $this->makeCreator()->create('toastr', ['version' => '2.1.4']);

        $this->assertSame(SymlinkResult::Skipped, $result);
        $this->assertFileDoesNotExist($pkgDir . '/index.js');
        $this->assertFileDoesNotExist($pkgDir . '/package.json');
    }

    public function testRefusesToWriteIntoSymlinkedScopeDir(): void
    {
        $scopeDir = $this->vendorDir . '/@scope';
        $this->filesystem->dumpFile($scopeDir . '/pkg.js', 'export default {}');
        $this->filesystem->symlink($scopeDir, $this->nodeModulesDir . '/@scope');

        $result = $this->makeCreator()->create('@scope/pkg', ['version' => '1.0.0']);

        $this->assertSame(SymlinkResult::Skipped, $result);
        $this->assertFileDoesNotExist($scopeDir . '/pkg');
    }

    public function testSkipsSubpathFileEntries(): void
    {
        $this->filesystem->dumpFile(
            $this->vendorDir . '/jquery-ui/ui/widgets/sortable.js',
            'export default {}',
        );

        $result = $this->makeCreator()->create('jquery-ui/ui/widgets/sortable', ['version' => '1.13.2']);

        $this->assertSame(SymlinkResult::Skipped, $result);
        $this->assertFileDoesNotExist($this->nodeModulesDir . '/jquery-ui');
    }

    public function testCreatesPackageJsonForFileSymlink(): void
    {
        $jsFile = $this->projectDir . '/assets/app.js';
        $this->filesystem->dumpFile($jsFile, 'export default {}');

        $this->makeCreator()->create('app', ['path' => './assets/app.js']);

        $packageJson = $this->nodeModulesDir . '/app/package.json';
        $this->assertFileExists($packageJson);
        $data = json_decode(file_get_contents($packageJson), true);
        $this->assertSame('module', $data['type']);
        $this->assertSame('index.js', $data['main']);
    }
}
