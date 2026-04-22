# Documentation — AssetMapper Test Bundle

This bundle bridges **Symfony AssetMapper** with **Node.js test runners**
(e.g. `node --test`, Vitest, Mocha). AssetMapper does not use `node_modules`;
Node.js does. This bundle offers two ways to close the gap without forcing you
to `npm install` your AssetMapper dependencies a second time.

## The problem in one picture

```
importmap.php                     assets/vendor/          node_modules/
  'happy-dom' => '…'              happy-dom/                (empty)
  '@tonejs/midi' => '…'           @tonejs/midi/             (empty)
  'app' => path:./assets/app.js   ·                         ·

  ← AssetMapper lives here    |  ← Node expects packages here →
```

Without a bridge, `import 'happy-dom'` in a Node test fails with
`ERR_MODULE_NOT_FOUND`.

## Two strategies

### 1. Symlink strategy (classic)

`asset-mapper-test:setup` creates symlinks inside `node_modules/` that point
into `assets/vendor/`. Node then resolves imports the usual way.

- ✅ IDE autocomplete works out of the box
- ✅ Works with every Node.js version
- ⚠️  Requires the OS to support symlinks (Windows needs developer mode
      or admin privileges)
- ⚠️  Leaves a `node_modules/` tree on disk

➡️  See [`symlink-variant.md`](./symlink-variant.md).

### 2. Loader strategy (symlink-free)

`asset-mapper-test:export` writes a `var/asset-mapper-test/importmap.json`
file. A tiny Node.js loader registered with `node --import` uses it to map
bare specifiers to files inside `assets/vendor/` at runtime — no filesystem
symlinks, no `node_modules/` tree.

- ✅ No filesystem artifacts, no symlinks
- ✅ Works identically on Linux / macOS / Windows
- ✅ Uses Node's stable **Module Customization Hooks** API
- ⚠️  Requires Node.js ≥ 20.6 (or ≥ 18.19)
- ⚠️  IDE autocomplete for bare specifiers needs extra setup
      (see [`troubleshooting.md`](./troubleshooting.md))

➡️  See [`loader-variant.md`](./loader-variant.md).

## Which should I use?

| Situation                                             | Recommended    |
|-------------------------------------------------------|----------------|
| Linux / macOS developer with IDE autocomplete         | Symlink        |
| Windows developer without "developer mode"            | Loader         |
| CI that should not create filesystem artifacts        | Loader         |
| Mixed team that wants one command for everybody       | Loader         |
| You want zero moving parts and your OS likes symlinks | Symlink        |

Both strategies can coexist in one project — run `setup` for IDE DX, then use
`export` + loader for the actual test run.

## Table of contents

- [init-command.md](./init-command.md) — interactive `package.json` scaffolder
- [symlink-variant.md](./symlink-variant.md) — symlink workflow, internals
- [loader-variant.md](./loader-variant.md) — Node.js loader, `module.register` API
- [configuration.md](./configuration.md) — paths, environment variables, logging
- [troubleshooting.md](./troubleshooting.md) — common problems & fixes
- [architecture.md](./architecture.md) — classes, data flow, design decisions
