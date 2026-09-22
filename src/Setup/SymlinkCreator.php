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
        // Subpath entries (e.g. 'jquery-ui/ui/widgets/sortable') would compete
        // with the package's own entry for node_modules/<pkg>/index.js, and the
        // resulting stub never resolves the subpath specifier anyway (that
        // would require an "exports" map). Skip them instead of clobbering.
        if ($this->hasSubpath($entry->name)) {
            $this->logger()->info('Skipping "{name}": subpath entries are not supported by the symlink variant', [
                'name' => $entry->name,
            ]);
            return SymlinkResult::Skipped;
        }

        // Single-file packages are wrapped in <pkg>/index.js + package.json
        // so Node's legacy CommonJS-style resolver can find them.
        $targetDir = $this->nodeModulesDir . '/' . $entry->name;

        if ($this->isInsideSymlink($targetDir)) {
            return $this->refuseSymlinkedTarget($entry, $targetDir);
        }

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

        if ($this->isInsideSymlink(\dirname($targetDir))) {
            return $this->refuseSymlinkedTarget($entry, $targetDir);
        }

        $this->ensureParentDir($targetDir);

        if ($this->filesystem->exists($targetDir)) {
            return SymlinkResult::Skipped;
        }

        $this->filesystem->symlink($entry->path, $targetDir);

        return SymlinkResult::Created;
    }

    /**
     * True when the entry name addresses something inside a package
     * ('pkg/sub/file', '@scope/pkg/sub') rather than the package itself.
     */
    private function hasSubpath(string $name): bool
    {
        return substr_count($name, '/') > (str_starts_with($name, '@') ? 1 : 0);
    }

    /**
     * True when $path, or any of its ancestors below node_modules/, is a
     * symlink. Writing "through" such a symlink would land outside
     * node_modules/ — typically inside assets/vendor/, permanently
     * contaminating the project's source assets.
     */
    private function isInsideSymlink(string $path): bool
    {
        for ($dir = $path; $dir !== $this->nodeModulesDir && $dir !== \dirname($dir); $dir = \dirname($dir)) {
            if (is_link($dir)) {
                return true;
            }
        }
        return false;
    }

    private function refuseSymlinkedTarget(ImportmapEntry $entry, string $targetDir): SymlinkResult
    {
        $this->logger()->warning('Skipping "{name}": refusing to write through existing symlink at "{target}"', [
            'name' => $entry->name,
            'target' => $targetDir,
        ]);
        return SymlinkResult::Skipped;
    }

    private function ensureParentDir(string $targetDir): void
    {
        $parentDir = \dirname($targetDir);
        if (!$this->filesystem->exists($parentDir)) {
            $this->filesystem->mkdir($parentDir);
        }
    }
}
