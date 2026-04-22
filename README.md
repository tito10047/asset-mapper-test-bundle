# AssetMapper Test Bundle

[![CI](https://github.com/tito10047/asset-mapper-test-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/tito10047/asset-mapper-test-bundle/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](./LICENSE)

Run **Node.js tests** against packages managed by **Symfony AssetMapper** —
no `npm install`, no duplicated dependencies.

Two interchangeable strategies are provided:

| Strategy                | Command                                       | How it works                                                 |
|-------------------------|-----------------------------------------------|--------------------------------------------------------------|
| **Symlinks** (default)  | `php bin/console asset-mapper-test:setup`     | Creates symlinks in `node_modules/` from `importmap.php`     |
| **Symlink-free loader** | `php bin/console asset-mapper-test:export`    | Exports `importmap.json`, a Node.js loader maps imports live |

Pick **symlinks** for best IDE autocomplete.
Pick the **loader** when you want zero `node_modules`, cross-platform cleanliness
(Windows without developer mode), or reproducible CI without filesystem artifacts.

## Install

```bash
composer require --dev tito10047/asset-mapper-test-bundle
```

Auto-discovered by Symfony Flex — no additional wiring needed.

## Scaffold `package.json` (interactive)

```bash
php bin/console asset-mapper-test:init
```

Asks which variant (`symlink` / `loader`) and which JS runner (`node` / `vitest`)
you want, then writes a matching `package.json`. For `node --test` it also
drops `tests/js/setup.mjs` with a ready-to-use `happy-dom` window bootstrap
(`globalThis.window`, `document`, `HTMLElement`, `Event`).

Non-interactive (CI) form:

```bash
php bin/console asset-mapper-test:init --variant=loader --runner=node
```

## Quick start — Symlink variant

```json
{
  "type": "module",
  "scripts": {
    "pretest": "php bin/console asset-mapper-test:setup",
    "test":    "node --import ./tests/js/setup.mjs --test 'tests/js/**/*.test.mjs'"
  }
}
```

```bash
npm test
```

## Quick start — Loader variant (no `node_modules`)

```json
{
  "type": "module",
  "scripts": {
    "pretest": "php bin/console asset-mapper-test:export",
    "test":    "node --import ./vendor/tito10047/asset-mapper-test-bundle/src/Resources/loader/register.mjs --import ./tests/js/setup.mjs --test 'tests/js/**/*.test.mjs'"
  }
}
```

Requires Node.js ≥ 20.6 (or ≥ 18.19) for the stable `module.register` API.

## Documentation

Full documentation lives in [`docs/`](./docs):

- [`docs/index.md`](./docs/index.md) — overview & table of contents
- [`docs/init-command.md`](./docs/init-command.md) — interactive `package.json` scaffolder
- [`docs/symlink-variant.md`](./docs/symlink-variant.md) — symlink workflow in depth
- [`docs/loader-variant.md`](./docs/loader-variant.md) — Node.js loader in depth
- [`docs/configuration.md`](./docs/configuration.md) — paths, env vars, logging
- [`docs/troubleshooting.md`](./docs/troubleshooting.md) — common problems & fixes
- [`docs/architecture.md`](./docs/architecture.md) — internal design, classes, data flow

## Requirements

- PHP ≥ 8.2
- Symfony ≥ 6.4 (also works on 7.x and 8.x)
- Symfony AssetMapper
- Node.js ≥ 20.6 for the loader variant (any version for symlinks)

## License

MIT — see [LICENSE](./LICENSE).
