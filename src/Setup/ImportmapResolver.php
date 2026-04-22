<?php

namespace Tito10047\AssetMapperTestBundle\Setup;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Pure data class. Takes an importmap (`importmap.php` return value) and
 * resolves each entry to a concrete {@see ImportmapEntry} that describes what
 * file/directory should be used at runtime.
 *
 * The decision logic mirrors the legacy {@see SymlinkCreator::create()} so both
 * the symlink path and the JSON export share the same behavior.
 */
class ImportmapResolver
{
    public function __construct(
        private readonly string $vendorDir,
        private readonly string $projectDir,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    private function logger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }

    /**
     * @param array<string, array<string, mixed>> $importmap
     * @return ImportmapEntry[]
     */
    public function resolve(array $importmap): array
    {
        $entries = [];
        foreach ($importmap as $name => $config) {
            $entry = $this->resolveEntry((string) $name, is_array($config) ? $config : []);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function resolveEntry(string $name, array $config): ?ImportmapEntry
    {
        // 1) Local path entry (e.g. { path: './assets/app.js' })
        if (isset($config['path'])) {
            $file = $this->projectDir . '/' . ltrim((string) $config['path'], './');
            if (!file_exists($file)) {
                $this->logger()->debug('Skipping "{name}": local path not found ({path})', [
                    'name' => $name,
                    'path' => $file,
                ]);
                return null;
            }
            return new ImportmapEntry($name, ImportmapEntry::KIND_FILE, $file);
        }

        $sourcePath = $this->vendorDir . '/' . $name;
        if (!file_exists($sourcePath)) {
            $this->logger()->debug('Skipping "{name}": not present in vendor dir ({path})', [
                'name' => $name,
                'path' => $sourcePath,
            ]);
            return null;
        }

        // 2) Single file in vendor directly
        if (is_file($sourcePath)) {
            return new ImportmapEntry($name, ImportmapEntry::KIND_FILE, $sourcePath);
        }

        if (is_dir($sourcePath)) {
            // 3) Directory with index.js → directory entry
            if (file_exists($sourcePath . '/index.js')) {
                return new ImportmapEntry($name, ImportmapEntry::KIND_DIR, $sourcePath);
            }

            // 4) Directory with a single *.js file → promote that file
            $files = glob($sourcePath . '/*.js');
            if ($files !== false && count($files) === 1) {
                return new ImportmapEntry($name, ImportmapEntry::KIND_FILE, $files[0]);
            }

            // 5) Fallback: treat as directory anyway (consistent with SymlinkCreator directory symlink)
            return new ImportmapEntry($name, ImportmapEntry::KIND_DIR, $sourcePath);
        }

        return null;
    }
}
