<?php

namespace Tito10047\AssetMapperTestBundle\Setup;

class ExportResult
{
    public function __construct(
        public readonly string $outputPath,
        public readonly int $exported,
        public readonly int $skipped,
    ) {
    }
}
