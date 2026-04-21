<?php

namespace Tito10047\AssetMapperTestBundle\Setup;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

class NodeModulesSetup
{
    private readonly string $nodeModulesDir;

    public function __construct(
        private readonly string $importmapPath,
        private readonly string $vendorDir,
        private readonly string $projectDir,
        private readonly Filesystem $filesystem,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->nodeModulesDir = $projectDir . '/node_modules';
    }

    private function logger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }

    public function run(): SetupResult
    {
        if (!file_exists($this->importmapPath)) {
            $this->logger()->critical('importmap.php not found at "{path}"', ['path' => $this->importmapPath]);
            throw new \RuntimeException(sprintf('importmap.php not found at "%s"', $this->importmapPath));
        }

        $importmap = require $this->importmapPath;

        if (!is_array($importmap)) {
            $this->logger()->critical('importmap.php must return an array, got {type}', ['type' => gettype($importmap)]);
            throw new \RuntimeException('importmap.php must return an array');
        }

        $this->ensureNodeModulesDir();

        $creator = new SymlinkCreator(
            $this->filesystem,
            $this->vendorDir,
            $this->nodeModulesDir,
            $this->projectDir,
            $this->logger(),
        );

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($importmap as $name => $config) {
            $result = $creator->create($name, $config);

            match ($result) {
                SymlinkResult::Created => $created++,
                SymlinkResult::Skipped => $skipped++,
                SymlinkResult::Error => $errors[] = $name,
            };
        }

        return new SetupResult($created, $skipped, $errors);
    }

    private function ensureNodeModulesDir(): void
    {
        if (!$this->filesystem->exists($this->nodeModulesDir)) {
            $this->filesystem->mkdir($this->nodeModulesDir);
        }

        $packageJson = $this->nodeModulesDir . '/package.json';
        if (!$this->filesystem->exists($packageJson)) {
            $this->filesystem->dumpFile(
                $packageJson,
                json_encode(['type' => 'module'], JSON_PRETTY_PRINT)
            );
        }
    }
}
