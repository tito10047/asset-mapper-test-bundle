# Configuration

Almost nothing to configure — the bundle picks up defaults from Symfony's
`framework.asset_mapper` configuration and `kernel.project_dir`. This page
documents every knob that does exist.

## Auto-detected paths

At compile time the bundle reads `framework.asset_mapper` and sets three
container parameters:

| Parameter                          | Default                                 |
|------------------------------------|-----------------------------------------|
| `asset_mapper_test.importmap_path` | `%kernel.project_dir%/importmap.php`    |
| `asset_mapper_test.vendor_dir`     | `%kernel.project_dir%/assets/vendor`    |
| `asset_mapper_test.project_dir`    | `%kernel.project_dir%`                  |

If you override `framework.asset_mapper.importmap_path` or `vendor_dir`, the
bundle uses your values automatically — no extra config needed.

## Environment variables

| Variable                  | Purpose                                               | Default                                    |
|---------------------------|-------------------------------------------------------|--------------------------------------------|
| `ASSET_MAPPER_IMPORTMAP`  | Path to the JSON file read by the Node.js loader      | `<cwd>/var/asset-mapper-test/importmap.json` |

`ASSET_MAPPER_IMPORTMAP` accepts absolute or relative paths. Relative paths
are resolved against `process.cwd()`.

Example (monorepo with non-standard cwd):

```bash
ASSET_MAPPER_IMPORTMAP=../backend/var/asset-mapper-test/importmap.json \
  node --import ./vendor/.../register.mjs --test tests/js/*.test.mjs
```

## Logging

By default the bundle uses `NullLogger`. If you want logs from the setup /
export commands, declare a dedicated Monolog channel:

```yaml
# config/packages/monolog.yaml
monolog:
    channels:
        - asset_mapper_test

when@dev:
    monolog:
        handlers:
            asset_mapper_test:
                type: stream
                path: "%kernel.logs_dir%/asset_mapper_test.log"
                level: debug
                channels: [asset_mapper_test]
```

The bundle injects `monolog.logger.asset_mapper_test` automatically if the
service exists (via `NULL_ON_INVALID_REFERENCE`, so the bundle also works
without Monolog).

## Output path for the JSON export

Currently fixed at `<project_dir>/var/asset-mapper-test/importmap.json`.
The `ImportmapJsonExporter` class accepts a custom `outputPath` in its
constructor if you instantiate it yourself — this is the extension point for
advanced setups (e.g. monorepos).

## Ignoring generated artifacts

Add these lines to `.gitignore`:

```
/node_modules/
/var/asset-mapper-test/
```

Both are transient — regenerated on every test run via `pretest`.
