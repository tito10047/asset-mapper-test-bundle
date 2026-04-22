<?php

namespace Tito10047\AssetMapperTestBundle\Setup;

/**
 * DTO describing a single resolved importmap entry.
 *
 * Kind semantics:
 *  - 'dir'  → {@see $path} is an existing directory containing `index.js`
 *             (or resolved at runtime via subpath joins by the Node.js loader).
 *  - 'file' → {@see $path} is a concrete JS file on disk.
 */
final class ImportmapEntry
{
    public const KIND_DIR = 'dir';
    public const KIND_FILE = 'file';

    public function __construct(
        public readonly string $name,
        public readonly string $kind,
        public readonly string $path,
    ) {
    }

    /**
     * @return array{kind: string, path: string}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'path' => $this->path,
        ];
    }
}
