# `asset-mapper-test:init` — package.json scaffolder

Interactive console command that generates a starter `package.json` wired for
either of the two bundle strategies (symlink / loader) and either of the two
supported JS test runners (Node's built-in `node --test` or [Vitest]).

## Usage

```bash
php bin/console asset-mapper-test:init
```

The command asks two questions:

1. **Which workflow variant?**
   - `symlink` — uses `asset-mapper-test:setup` in `pretest`; best IDE autocomplete.
   - `loader` — uses `asset-mapper-test:export` in `pretest` + Node.js
     Customization Hooks at runtime; zero `node_modules`, cross-platform.
2. **Which JS test runner?**
   - `node` — Node's built-in test runner (`node --test`). Requires Node ≥ 20.
   - `vitest` — [Vitest] (`vitest run`).
     *Note: Using Vitest with test dependencies managed via AssetMapper (`--deps=asset_mapper`) is not supported because Vitest has complex internal dependencies that cannot be reliably bundled.*

### Non-interactive (CI)

```bash
php bin/console asset-mapper-test:init \
    --variant=loader \
    --runner=node \
    --force         # optional, overwrites existing package.json
```

Both options are required when running non-interactively.

## What gets written

### `package.json` (always)

| Variant + runner        | `scripts.pretest`                            | `scripts.test`                                                                     |
|-------------------------|----------------------------------------------|------------------------------------------------------------------------------------|
| `symlink` + `node`      | `php bin/console asset-mapper-test:setup`    | `node --import ./tests/js/setup.mjs --test 'tests/js/**/*.test.mjs'`               |
| `symlink` + `vitest`    | `php bin/console asset-mapper-test:setup`    | `vitest run`                                                                       |
| `loader`  + `node`      | `php bin/console asset-mapper-test:export`   | `node --import <register.mjs> --import ./tests/js/setup.mjs --test 'tests/js/**'`  |
| `loader`  + `vitest`    | `php bin/console asset-mapper-test:export`   | `vitest run`                                                                       |

`devDependencies` always includes `happy-dom`; Vitest variants add `vitest`.

### `tests/js/setup.mjs` (only when runner = `node`)

```js
import { Window } from 'happy-dom'

const window = new Window()
globalThis.window = window
globalThis.document = window.document
globalThis.HTMLElement = window.HTMLElement
globalThis.Event = window.Event
```

This bootstraps a minimal browser-like environment for Stimulus / DOM tests.
Vitest users typically configure happy-dom via `vitest.config.js` instead, so
no `setup.mjs` is generated for them.

## Safety

- Existing `package.json` is **never overwritten** unless you pass `--force`.
- Existing `tests/js/setup.mjs` is **never overwritten** (no force flag —
  hand-tuned test setups stay intact).

## Next steps after running the command

```bash
npm install
npm test
```

[Vitest]: https://vitest.dev/
