<?php

namespace Tito10047\AssetMapperTestBundle\Setup;

use Symfony\Component\Filesystem\Filesystem;
use Tito10047\AssetMapperTestBundle\Setup\PackageJsonGenerator\GenerateResult;
use Tito10047\AssetMapperTestBundle\Setup\PackageJsonGenerator\Runner;
use Tito10047\AssetMapperTestBundle\Setup\PackageJsonGenerator\Variant;

/**
 * Generates a starter `package.json` (and, when using Node's built-in test
 * runner, a `tests/js/setup.mjs` with a happy-dom window bootstrap) that wires
 * the chosen workflow variant (symlink / loader) and JS test runner
 * (node --test / vitest) together.
 *
 * Existing files are never overwritten unless {@see $force} is true.
 */
final class PackageJsonGenerator
{
    public const SETUP_MJS_RELATIVE = 'tests/js/setup.mjs';

    private const LOADER_REGISTER_PATH =
        './vendor/tito10047/asset-mapper-test-bundle/src/Resources/loader/register.mjs';

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
    }

    public function generate(
        string $projectDir,
        Variant $variant,
        Runner $runner,
        bool $force = false,
    ): GenerateResult {
        $packageJsonPath = $projectDir . '/package.json';
        $packageJsonCreated = false;

        if ($force || !$this->filesystem->exists($packageJsonPath)) {
            $this->filesystem->dumpFile(
                $packageJsonPath,
                json_encode(
                    $this->buildPackageJson($variant, $runner),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                ) . "\n"
            );
            $packageJsonCreated = true;
        }

        $setupMjsPath = null;
        $setupMjsCreated = false;

        if ($runner === Runner::Node) {
            $setupMjsPath = $projectDir . '/' . self::SETUP_MJS_RELATIVE;
            if (!$this->filesystem->exists($setupMjsPath)) {
                $this->filesystem->dumpFile($setupMjsPath, $this->buildSetupMjs());
                $setupMjsCreated = true;
            }
        }

        return new GenerateResult(
            $packageJsonPath,
            $packageJsonCreated,
            $setupMjsPath,
            $setupMjsCreated,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPackageJson(Variant $variant, Runner $runner): array
    {
        return [
            'type' => 'module',
            'scripts' => [
                'pretest' => $this->buildPretestScript($variant),
                'test' => $this->buildTestScript($variant, $runner),
            ],
            'devDependencies' => $this->buildDevDependencies($runner),
        ];
    }

    private function buildPretestScript(Variant $variant): string
    {
        return match ($variant) {
            Variant::Symlink => 'php bin/console asset-mapper-test:setup',
            Variant::Loader => 'php bin/console asset-mapper-test:export',
        };
    }

    private function buildTestScript(Variant $variant, Runner $runner): string
    {
        if ($runner === Runner::Vitest) {
            return 'vitest run';
        }

        $parts = ['node'];

        if ($variant === Variant::Loader) {
            $parts[] = '--import ' . self::LOADER_REGISTER_PATH;
        }

        $parts[] = '--import ./' . self::SETUP_MJS_RELATIVE;
        $parts[] = '--test';
        $parts[] = "'tests/js/**/*.test.mjs'";

        return implode(' ', $parts);
    }

    /**
     * @return array<string, string>
     */
    private function buildDevDependencies(Runner $runner): array
    {
        $deps = ['happy-dom' => '^15.0.0'];

        if ($runner === Runner::Vitest) {
            $deps['vitest'] = '^2.0.0';
        }

        ksort($deps);
        return $deps;
    }

    private function buildSetupMjs(): string
    {
        return <<<'JS'
            import { Window } from 'happy-dom'

            const window = new Window()
            globalThis.window = window
            globalThis.document = window.document
            globalThis.HTMLElement = window.HTMLElement
            globalThis.Event = window.Event

            JS;
    }
}
