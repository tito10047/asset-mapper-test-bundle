<?php

namespace Tito10047\AssetMapperTestBundle\Setup;

class SetupResult
{
    public function __construct(
        public readonly int $created,
        public readonly int $skipped,
        public readonly array $errors,
    ) {}

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
