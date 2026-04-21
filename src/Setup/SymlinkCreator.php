<?php

namespace Tito10047\AssetMapperTestBundle\Setup;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

class SymlinkCreator
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $vendorDir,
        private readonly string $nodeModulesDir,
        private readonly string $projectDir,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    private function logger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }

    /**
     * Creates a symlink for a single importmap entry.
     */
    public function create(string $name, array $config): SymlinkResult
    {
        $targetDir = $this->nodeModulesDir . '/' . $name;
        $sourcePath = $this->vendorDir . '/' . $name;
        $file = null;

        if (isset($config['path'])) {
            $file = $this->projectDir . '/' . ltrim($config['path'], './');

            if (!file_exists($file)) {
                return SymlinkResult::Skipped;
            }
        } else {
            if (!$this->filesystem->exists($sourcePath)) {
                return SymlinkResult::Skipped;
            }

            if (is_file($sourcePath)) {
                // Single file in vendor (e.g. some-package/some-package.js stored flat)
                $packageName = explode('/', $name)[0];
                $targetDir = $this->nodeModulesDir . '/' . $packageName;
                $file = $sourcePath;
            } elseif (is_dir($sourcePath)) {
                // Prefer directory symlink; only fall back to single-file if no index.js
                if (!file_exists($sourcePath . '/index.js')) {
                    $files = glob($sourcePath . '/*.js');
                    if ($files !== false && count($files) === 1) {
                        $file = $files[0];
                    }
                }
                // if $file stays null, we'll do a directory symlink below
            }
        }

        // Ensure parent directory exists (for scoped packages like @scope/name)
        $parentDir = dirname($targetDir);
        if (!$this->filesystem->exists($parentDir)) {
            $this->filesystem->mkdir($parentDir);
        }

        try {
            if ($file !== null) {
                if (!$this->filesystem->exists($targetDir)) {
                    $this->filesystem->mkdir($targetDir);
                }

                $linkTarget = $targetDir . '/index.js';
                if ($this->filesystem->exists($linkTarget)) {
                    return SymlinkResult::Skipped;
                }

                $this->filesystem->symlink($file, $linkTarget);

                if (!$this->filesystem->exists($targetDir . '/package.json')) {
                    $packageName = basename($targetDir);
                    $this->filesystem->dumpFile($targetDir . '/package.json', json_encode([
                        'name' => $packageName,
                        'type' => 'module',
                        'main' => 'index.js',
                    ], JSON_PRETTY_PRINT));
                }

                return SymlinkResult::Created;
            } elseif (is_dir($sourcePath)) {
                if ($this->filesystem->exists($targetDir)) {
                    return SymlinkResult::Skipped;
                }

                $this->filesystem->symlink($sourcePath, $targetDir);

                return SymlinkResult::Created;
            }

            return SymlinkResult::Skipped;
        } catch (\Exception $e) {
            $this->logger()->error('Failed to create symlink for "{name}": {message}', [
                'name' => $name,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            return SymlinkResult::Error;
        }
    }
}
