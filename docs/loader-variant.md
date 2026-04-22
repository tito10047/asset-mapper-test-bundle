# Loader variant (symlink-free)

Uses **Node.js Module Customization Hooks** (`node:module` → `register()`) to
resolve bare specifiers directly to files under `assets/vendor/`. No symlinks,
no `node_modules/` tree.

## Setup

```json
{
  "type": "module",
  "scripts": {
    "pretest": "php bin/console asset-mapper-test:export",
    "test":    "node --import ./vendor/tito10047/asset-mapper-test-bundle/src/Resources/loader/register.mjs --import ./tests/js/setup.mjs --test 'tests/js/**/*.test.mjs'"
  }
}
```

Run tests:

```bash
npm test
```

## Requirements

- **Node.js ≥ 20.6** (or **≥ 18.19**). Earlier versions expose only the
  experimental `--experimental-loader` flag with a different API.
- ESM tests (`.mjs` or `"type": "module"` + `.js`). The loader focuses on
  ESM; CJS `require()` hooks are out of scope.

## What the command does

`asset-mapper-test:export` reads `importmap.php`, resolves every entry
(reusing the same logic as the symlink variant — see
[`architecture.md`](./architecture.md)) and writes one JSON file:

```json
{
  "projectDir": "/abs/path/to/project",
  "vendorDir":  "/abs/path/to/project/assets/vendor",
  "entries": {
    "happy-dom":    { "kind": "dir",  "path": "/abs/.../assets/vendor/happy-dom" },
    "@tonejs/midi": { "kind": "dir",  "path": "/abs/.../assets/vendor/@tonejs/midi" },
    "pitchfinder":  { "kind": "file", "path": "/abs/.../assets/vendor/pitchfinder/pitchfinder.js" },
    "app":          { "kind": "file", "path": "/abs/.../assets/app.js" }
  }
}
```

Default output path: `var/asset-mapper-test/importmap.json`.
Override with the `ASSET_MAPPER_IMPORTMAP` environment variable if your project
layout demands it.

## How the loader resolves imports

The loader exposes the two standard hooks defined by Node's `module.register`
API:

```
resolve(specifier, context, nextResolve)
load   (url,       context, nextLoad)
```

### `resolve` algorithm

1. Relative imports (`./x`, `../x`, `/abs`), `file:`, `node:`, `data:`, `http(s):`
   → delegated to `nextResolve` unchanged.
2. **Exact match** in `entries` → returned immediately.
   - `kind: "dir"`  → `<path>/index.js`
   - `kind: "file"` → `<path>`
3. **Subpath match** (scope-aware): for `happy-dom/lib/util.js` the loader
   splits into `["happy-dom", "lib/util.js"]`; for `@scope/pkg/sub/file.js`
   into `["@scope/pkg", "sub/file.js"]`. If the package part is a directory
   entry, it joins path + subpath.
4. Otherwise → `nextResolve`, giving Node's default resolver a chance.

### `load` algorithm

- If a format was already determined (e.g. by an earlier hook) → passthrough.
- For `.js` files resolved by us → force `format: 'module'` so ESM works even
  when `assets/vendor/<pkg>/` has no `package.json`.
- Otherwise → passthrough.

## Diagnostics

### Missing `importmap.json`

```
[asset-mapper-test] importmap.json not found at "/…/var/asset-mapper-test/importmap.json".
Run: php bin/console asset-mapper-test:export
```

→ add `pretest` hook (see above) or run the command manually.

### Unknown bare specifier

The loader does NOT alias names it doesn't know. Node will then raise a
standard `ERR_MODULE_NOT_FOUND`. Fix: add the package to `importmap.php` with
`php bin/console importmap:require <pkg>`, then rerun `:export`.

## Dev loop

```bash
# Normal TDD flow — any change in importmap.php is picked up automatically
# by the pretest hook. If you want to skip the pretest (e.g. --watch mode),
# remember to rerun asset-mapper-test:export whenever importmap.php changes.
npm test
```

For `--watch`: leave `npm run test:watch` running and rerun `:export` only
when `importmap.php` changes:

```bash
php bin/console asset-mapper-test:export
```

## Limits & trade-offs

- **IDE autocomplete** sees nothing — no `node_modules/`. Workarounds:
  - Run `asset-mapper-test:setup` once locally for IDE DX (both strategies
    can coexist; the loader ignores `node_modules/`).
  - Add a `jsconfig.json` with `paths` mapping.
- **`package.json#exports`** conditional exports are bypassed for packages
  in the importmap — we short-circuit with a direct file URL. AssetMapper
  already fetches the right entry file, so in practice this is fine.
- **CJS-only packages** inside `assets/vendor/` are rare. The loader does not
  force `format: 'module'` if an earlier hook already decided. For edge cases
  with mixed CJS/ESM, prefer the symlink variant.
