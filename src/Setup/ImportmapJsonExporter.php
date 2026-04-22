<?php

namespace Tito10047\AssetMapperTestBundle\Setup;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Exports the resolved importmap into a JSON file consumed by the Node.js
 * loader (`src/Resources/loader/loader.mjs`). See `nodejs_loader_plan.md` for
 * the full data-flow and format description.
 */
class ImportmapJsonExporter
{
    public const DEFAULT_OUTPUT = 'var/asset-mapper-test/importmap.json';

    public function __construct(
        private readonly string $importmapPath,
        private readonly string $vendorDir,
        private readonly string $projectDir,
        private readonly Filesystem $filesystem,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?string $outputPath = null,
    ) {
    }

    private function logger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }

    public function export(): ExportResult
    {
        if (!file_exists($this->importmapPath)) {
            throw new \RuntimeException(sprintf('importmap.php not found at "%s"', $this->importmapPath));
        }

        $importmap = require $this->importmapPath;
        if (!is_array($importmap)) {
            throw new \RuntimeException('importmap.php must return an array');
        }

        $resolver = new ImportmapResolver($this->vendorDir, $this->projectDir, $this->logger());
        $resolved = $resolver->resolve($importmap);

        $entries = [];
        foreach ($resolved as $entry) {
            $entries[$entry->name] = $entry->toArray();
        }

        $skipped = count($importmap) - count($resolved);

        $payload = [
            'projectDir' => $this->projectDir,
            'vendorDir' => $this->vendorDir,
            'entries' => $entries,
        ];

        $output = $this->outputPath ?? ($this->projectDir . '/' . self::DEFAULT_OUTPUT);

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode importmap JSON: ' . json_last_error_msg());
        }

        $this->filesystem->dumpFile($output, $json);

        $this->logger()->info('Exported {count} importmap entries to {path}', [
            'count' => count($entries),
            'path' => $output,
        ]);

        return new ExportResult($output, count($entries), max(0, $skipped));
    }
}
