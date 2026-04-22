<?php

namespace Tito10047\AssetMapperTestBundle\Setup;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Creates `node_modules/*` symlinks for a single importmap entry.
 *
 * The decision "is this a directory package, a single file, or a local path?"
 * is delegated to {@see ImportmapResolver}, so the symlink-based workflow
 * (`asset-mapper-test:setup`) and the symlink-free JSON export
 * (`asset-mapper-test:export`) share exactly the same resolution logic.
 */
class SymlinkCreator
{
    private readonly ImportmapResolver $resolver;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $vendorDir,
        private readonly string $nodeModulesDir,
        private readonly string $projectDir,
        private readonly ?LoggerInterface $logger = null,
        ?ImportmapResolver $resolver = null,
    ) {
        $this->resolver = $resolver ?? new ImportmapResolver($vendorDir, $projectDir, $logger);
    }

    private function logger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }

    /**
     * Creates a symlink for a single importmap entry.
     *
     * @param array<string, mixed> $config
     */
    public function create(string $name, array $config): SymlinkResult
    {
        $entry = $this->resolver->resolveEntry($name, $config);
        if ($entry === null) {
            return SymlinkResult::Skipped;
        }

        try {
            return $entry->kind === ImportmapEntry::KIND_FILE
                ? $this->linkFile($entry)
                : $this->linkDirectory($entry);
        } catch (\Exception $e) {
            $this->logger()->error('Failed to create symlink for "{name}": {message}', [
                'name' => $entry->name,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            return SymlinkResult::Error;
        }
    }

    private function linkFile(ImportmapEntry $entry): SymlinkResult
    {
        // Single-file packages are wrapped in <pkg>/index.js + package.json
        // so Node's legacy CommonJS-style resolver can find them.
        $targetDir = $this->nodeModulesDir . '/' . $this->packageDirName($entry->name);

        $this->ensureParentDir($targetDir);

        if (!$this->filesystem->exists($targetDir)) {
            $this->filesystem->mkdir($targetDir);
        }

        $linkTarget = $targetDir . '/index.js';
        if ($this->filesystem->exists($linkTarget)) {
            return SymlinkResult::Skipped;
        }

        $this->filesystem->symlink($entry->path, $linkTarget);

        $packageJson = $targetDir . '/package.json';
        if (!$this->filesystem->exists($packageJson)) {
            $this->filesystem->dumpFile($packageJson, json_encode([
                'name' => basename($targetDir),
                'type' => 'module',
                'main' => 'index.js',
            ], JSON_PRETTY_PRINT));
        }

        return SymlinkResult::Created;
    }

    private function linkDirectory(ImportmapEntry $entry): SymlinkResult
    {
        $targetDir = $this->nodeModulesDir . '/' . $entry->name;

        $this->ensureParentDir($targetDir);

        if ($this->filesystem->exists($targetDir)) {
            return SymlinkResult::Skipped;
        }

        $this->filesystem->symlink($entry->path, $targetDir);

        return SymlinkResult::Created;
    }

    /**
     * Scoped packages keep the "@scope/name" layout; flat names only need the
     * bare package name (sub-path parts are dropped by design — see old logic).
     */
    private function packageDirName(string $name): string
    {
        if (str_starts_with($name, '@')) {
            return $name;
        }
        return explode('/', $name)[0];
    }

    private function ensureParentDir(string $targetDir): void
    {
        $parentDir = \dirname($targetDir);
        if (!$this->filesystem->exists($parentDir)) {
            $this->filesystem->mkdir($parentDir);
        }
    }
}
