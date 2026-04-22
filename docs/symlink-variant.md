# Symlink variant

Creates symlinks inside `node_modules/` that point into `assets/vendor/`, so
Node.js finds the packages via its standard CommonJS/ESM resolution algorithm.

## Setup

```json
{
  "type": "module",
  "scripts": {
    "pretest":    "php bin/console asset-mapper-test:setup",
    "test":       "node --test tests/js/*.test.mjs",
    "test:watch": "node --watch --test tests/js/*.test.mjs"
  }
}
```

Run tests:

```bash
npm test
```

The `pretest` hook ensures symlinks are always in sync with `importmap.php`.

## What the command does

Given this `importmap.php`:

```php
return [
    'happy-dom'    => ['version' => '12.0.0'],
    '@tonejs/midi' => ['version' => '2.0.28'],
    'pitchfinder'  => ['version' => '2.3.4'],
    'app'          => ['path'    => './assets/app.js'],
];
```

…`asset-mapper-test:setup` produces the following tree:

```
node_modules/
├── package.json                      # { "type": "module" }
├── happy-dom  →  assets/vendor/happy-dom          (directory symlink)
├── @tonejs/
│   └── midi   →  assets/vendor/@tonejs/midi       (directory symlink)
├── pitchfinder/
│   ├── index.js  →  assets/vendor/pitchfinder/pitchfinder.js  (file symlink)
│   └── package.json                               # { "type": "module", "main": "index.js" }
└── app/
    ├── index.js  →  assets/app.js                 (file symlink)
    └── package.json                               # { "type": "module", "main": "index.js" }
```

## Resolution rules (exact behavior)

For each `importmap.php` entry the bundle applies, in order:

1. **`path` is set** → a local file (e.g. `./assets/app.js`) is symlinked
   as `node_modules/<name>/index.js` alongside a generated `package.json`.
2. **`assets/vendor/<name>` is a file** → that file is symlinked as
   `node_modules/<name>/index.js`.
3. **`assets/vendor/<name>/index.js` exists** → the whole directory
   is symlinked (`node_modules/<name>` → `assets/vendor/<name>`).
4. **Directory with exactly one `*.js` file** (e.g. `pitchfinder.js`) →
   that single file is symlinked as `index.js`.
5. **Fallback** → directory symlink even if no `index.js` is present
   (Node will error later if needed, with a clear message).

Scoped packages (`@scope/name`) keep their two-level layout automatically.

## When to prefer this variant

- You run tests on Linux or macOS.
- You want IDE autocomplete (PhpStorm/WebStorm) for bare specifiers like
  `import 'happy-dom'` — the IDE indexes `node_modules/` directly.
- You don't mind having a `node_modules/` directory in your project root.

## When NOT to prefer it

- **Windows without developer mode.** Non-admin users cannot create symlinks.
  Use the [loader variant](./loader-variant.md) instead.
- You want a fully clean repository without any `node_modules/`.

## Cleaning up

The bundle never deletes symlinks. If `importmap.php` entries are removed, old
symlinks stay behind. To clean up:

```bash
rm -rf node_modules
php bin/console asset-mapper-test:setup
```

Safe to run — nothing but symlinks and `package.json` stubs lives there.
