# Architecture

A deep dive into how the bundle is wired internally. Useful when extending
the bundle, debugging, or reviewing PRs.

## Design principles

1. **Single source of truth for resolution.** Both the symlink path and the
   JSON export share `ImportmapResolver` — there is exactly one place that
   decides "what does `happy-dom` in `importmap.php` actually point to?".
2. **Pure functions where possible.** `ImportmapResolver` only reads the
   filesystem; it does not mutate it. Mutations live in
   `SymlinkCreator` / `ImportmapJsonExporter`.
3. **No hidden globals.** Configuration flows through the DI container; the
   JS loader reads exactly one JSON file and one env var.

## Class diagram

```
               ┌──────────────────────┐
               │   importmap.php      │
               └──────────┬───────────┘
                          │
             ┌────────────▼───────────────┐
             │   ImportmapResolver        │
             │   (pure, fs-read only)     │
             │   resolve(array) → [...]   │
             └────┬────────────────┬──────┘
                  │                │
    ┌─────────────▼──────┐   ┌─────▼────────────────────┐
    │  SymlinkCreator    │   │  ImportmapJsonExporter   │
    │  linkFile/Dir      │   │  writes importmap.json   │
    └──────────┬─────────┘   └────────────┬─────────────┘
               │                          │
    ┌──────────▼────────────┐   ┌─────────▼────────────────┐
    │ NodeModulesSetup      │   │ ExportImportmapCommand   │
    │ (orchestrator)        │   │ (Symfony console cmd)    │
    └──────────┬────────────┘   └──────────────────────────┘
               │
    ┌──────────▼─────────────┐
    │ SetupNodeModulesCommand│
    │ (Symfony console cmd)  │
    └────────────────────────┘
```

## Data flow — symlink variant

```
importmap.php ─┐
               │ require()
               ▼
      NodeModulesSetup::run()
               │
               │ iterate entries
               ▼
      SymlinkCreator::create(name, config)
               │
               │ delegates the resolution to…
               ▼
      ImportmapResolver::resolveEntry()
               │
               │ ImportmapEntry { name, kind, path }
               ▼
      Symfony Filesystem ::symlink(…)
               │
               ▼
      node_modules/<name>  →  assets/vendor/<name>
```

## Data flow — loader variant

```
importmap.php ─┐                                          (PHP side)
               │ require()
               ▼
      ImportmapJsonExporter::export()
               │
               ▼
      ImportmapResolver::resolve()  → [ImportmapEntry, …]
               │
               ▼
      var/asset-mapper-test/importmap.json              ◄────── written by PHP
─────────────────────────────────────────────────────────────────────────
                                                          (Node.js side)
      register.mjs  ─── register('./loader.mjs', …)
               │
               ▼
      loader.mjs   (module.register hooks)
               │
               │ reads importmap.json once at startup
               ▼
      resolve(specifier) → file:// URL in assets/vendor/…
               │
               ▼
      Node ESM loader loads and executes
```

## The PHP classes

### `Setup/ImportmapEntry`

A tiny value object:

```php
final class ImportmapEntry {
    public const KIND_DIR  = 'dir';
    public const KIND_FILE = 'file';

    public function __construct(
        public readonly string $name,
        public readonly string $kind,
        public readonly string $path,
    ) {}
}
```

Used as the currency between resolver and any consumer.

### `Setup/ImportmapResolver`

Pure resolver. Given `['happy-dom' => ['version' => '…']]` it inspects
`assets/vendor/happy-dom/`, classifies it as dir/file, and returns an
`ImportmapEntry`. See the [resolution rules](./symlink-variant.md#resolution-rules-exact-behavior).

### `Setup/SymlinkCreator`

Consumes one `ImportmapEntry` at a time and creates the corresponding
symlink (plus a stub `package.json` for single-file packages). Uses Symfony
`Filesystem`.

### `Setup/NodeModulesSetup`

Top-level orchestrator for the symlink path. Reads `importmap.php`, ensures
`node_modules/package.json` exists with `{ "type": "module" }`, then loops
through entries calling `SymlinkCreator`.

### `Setup/ImportmapJsonExporter`

Top-level orchestrator for the loader path. Builds the JSON payload and
writes it atomically via Symfony `Filesystem::dumpFile`.

### Commands

- `Command/SetupNodeModulesCommand` → `asset-mapper-test:setup`
- `Command/ExportImportmapCommand`  → `asset-mapper-test:export`

Both are thin adapters: parse input, delegate to their service, render the
result via `SymfonyStyle`.

### `DependencyInjection/AssetMapperTestExtension`

- Implements `PrependExtensionInterface` to read
  `framework.asset_mapper.importmap_path` / `vendor_dir` and expose them as
  parameters.
- Registers all services and tags the two commands with `console.command`.
- Wires `monolog.logger.asset_mapper_test` as an optional dependency
  (`NULL_ON_INVALID_REFERENCE`) so the bundle works without Monolog.

## The JavaScript loader

Two files under `src/Resources/loader/`:

### `register.mjs`

```js
import { register } from 'node:module';
register('./loader.mjs', import.meta.url);
```

Thin entry point, meant to be passed to `node --import`.

### `loader.mjs`

- Reads `importmap.json` once at startup.
- Exposes `resolve(specifier, context, nextResolve)` and
  `load(url, context, nextLoad)` — the standard Module Customization Hooks
  contract defined in the [Node.js docs](https://nodejs.org/api/module.html#customization-hooks).
- `splitPkg()` helper is scope-aware (`@scope/pkg` stays together).
- Unknown specifiers are delegated to Node's default resolver — the loader
  never swallows errors silently.

## Test strategy

| Layer               | What we test                                                 | Runner                    |
|---------------------|--------------------------------------------------------------|---------------------------|
| `ImportmapResolver` | All five resolution branches with fs fixtures in `tmp/`       | PHPUnit (`tests/Setup/…`) |
| `SymlinkCreator`    | Actual symlink creation, scoped packages, idempotency         | PHPUnit                   |
| `NodeModulesSetup`  | End-to-end from `importmap.php` array to `node_modules/` tree | PHPUnit                   |
| `ImportmapJsonExporter` | JSON shape, output path, error on missing importmap       | PHPUnit                   |
| `SetupNodeModulesCommand` | Exit codes, stderr on failure                           | PHPUnit                   |
| `loader.mjs`        | Exact/subpath/scoped/file resolution, error messages          | `node --test tests/js/…`  |

Run everything:

```bash
vendor/bin/phpunit
node --test tests/js/*.test.mjs
```

## Extension points

- Custom **output path** for the exporter: construct
  `ImportmapJsonExporter` yourself and pass `$outputPath`.
- Custom **resolution**: decorate or replace `ImportmapResolver` in the
  container. Both `SymlinkCreator` and `ImportmapJsonExporter` honour the
  service (via the DI compiler, since they instantiate a default one if no
  resolver is injected).
- **Pre-export / post-export hooks**: wrap `ExportImportmapCommand` or listen
  to Symfony console events.
