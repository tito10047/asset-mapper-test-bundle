# Troubleshooting

## Symlink variant

### `EPERM: operation not permitted, symlink` on Windows

Non-admin Windows users can't create symlinks unless **Developer Mode** is
enabled (Settings → Privacy & security → For developers).

**Fix:** either enable Developer Mode, or switch to the
[loader variant](./loader-variant.md) which needs no symlinks at all.

### Stale symlinks after removing a package

The `asset-mapper-test:setup` command never deletes symlinks. If you
`importmap:remove <pkg>` the old symlink stays around.

**Fix:** `rm -rf node_modules && npm test`.

### IDE autocomplete still doesn't work

Make sure your IDE indexes `node_modules/`:

- **PhpStorm / WebStorm**: Settings → Directories → verify `node_modules` is
  not marked as "Excluded".
- **VS Code**: restart the TypeScript language server after the first
  `asset-mapper-test:setup` run.

## Loader variant

### `importmap.json not found`

```
[asset-mapper-test] importmap.json not found at "/…/var/asset-mapper-test/importmap.json"
```

Cause: you ran `node --import …/register.mjs` without running
`asset-mapper-test:export` first.

**Fix:** add the `pretest` hook to your `package.json`:

```json
"scripts": { "pretest": "php bin/console asset-mapper-test:export" }
```

Or run the command manually once.

### `ERR_MODULE_NOT_FOUND: Cannot find package 'X'`

`X` is not in your `importmap.php`. AssetMapper usually flattens transitive
deps, but occasionally a package pulls in something new.

**Fix:**

```bash
php bin/console importmap:require X
php bin/console asset-mapper-test:export
npm test
```

### `SyntaxError: Unexpected token 'export'` on a `.js` file

Your package file is ESM but Node is treating it as CJS. This happens when
the vendor directory has no `package.json` at all.

The loader already forces `format: 'module'` for `.js` files it resolved. If
this still pops up, the file probably lives outside the importmap (e.g. a
relative import inside a package points to a sibling file). File an issue
with the failing stack trace — the resolver likely needs a tweak.

### `module.register is not a function`

Your Node.js is older than 20.6 / 18.19.

**Fix:** upgrade Node, or use the symlink variant.

### IDE autocomplete for bare specifiers in the loader variant

The loader does not create `node_modules/`, so IDEs can't find packages by
name. Two workarounds:

1. **Run both strategies.** Add a one-time `asset-mapper-test:setup` to your
   local dev setup (not to `pretest`) so `node_modules/` exists for IDE
   indexing. The loader ignores it.
2. **Use a `jsconfig.json` / `tsconfig.json` with `paths`:**

   ```json
   {
     "compilerOptions": {
       "baseUrl": ".",
       "paths": {
         "happy-dom":    ["assets/vendor/happy-dom/index.js"],
         "@tonejs/midi": ["assets/vendor/@tonejs/midi/index.js"]
       }
     }
   }
   ```

## Both variants

### `importmap.php not found at "…"`

The bundle looked at the default path
(`%kernel.project_dir%/importmap.php`). If your project uses a custom path,
set it in `config/packages/asset_mapper.yaml`:

```yaml
framework:
    asset_mapper:
        importmap_path: "%kernel.project_dir%/config/importmap.php"
```

The bundle re-reads this automatically — no change needed on its side.

### Tests still fail after upgrading

```bash
composer update tito10047/asset-mapper-test-bundle
rm -rf node_modules var/asset-mapper-test
npm test
```

A clean slate eliminates 99% of "works on my machine" bugs.
