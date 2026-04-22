<?php

namespace Tito10047\AssetMapperTestBundle\Setup\PackageJsonGenerator;

final class GenerateResult
{
    public function __construct(
        public readonly string $packageJsonPath,
        public readonly bool $packageJsonCreated,
        public readonly ?string $setupMjsPath,
        public readonly bool $setupMjsCreated,
    ) {
    }
}
