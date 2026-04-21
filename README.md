# AssetMapper Test Bundle

A Symfony bundle that bridges the gap between **Symfony AssetMapper** and **Node.js test runners** by automatically creating a symlink-based `node_modules` structure from your `importmap.php`.

## The Problem

Symfony AssetMapper manages JavaScript dependencies without `node_modules`. However, when you want to write and run JavaScript unit tests using Node.js (e.g. the native `node --test` runner), Node.js expects packages to be available in `node_modules`.

This bundle solves that by reading your `importmap.php` and creating the necessary symlinks in `node_modules` — pointing directly to the files already downloaded by AssetMapper in `assets/vendor/`.

## Installation

```bash
composer require tito10047/asset-mapper-test-bundle
```

The bundle is auto-discovered by Symfony Flex. No additional configuration is required.

## Usage

### Testing Stimulus Controllers

For testing Stimulus controllers, it is highly recommended to use the [@tito10047/stimulus-test-utils](https://tito10047.github.io/stimulus-test-utils) package. It provides a `render()` function and Testing-Library-like helpers (`getByRole`, `user.click`, etc.) specifically for Stimulus.

Since you are using this bundle, you can manage your test dependencies entirely through AssetMapper (without `npm install` for your app dependencies):

1. Add the test utilities and a DOM polyfill to your `importmap.php`:

```bash
php bin/console importmap:require @tito10047/stimulus-test-utils happy-dom
```

2. Configure your tests to use these packages. Because this bundle symlinks them into `node_modules`, Node's native test runner can find them.

See the [full documentation](https://tito10047.github.io/stimulus-test-utils/guide/asset-mapper.html) for more details.

### Running JavaScript Tests

Once installed, simply run your Node.js tests using the script defined in `package.json`:

```bash
npm test
```

That's it. The `pretest` hook automatically runs `php bin/console asset-mapper-test:setup` before your tests, ensuring `node_modules` symlinks are up to date.

### package.json Setup

Add the following scripts to your `package.json`:

```json
{
  "type": "module",
  "scripts": {
    "test": "node --test tests/js/*.test.mjs",
    "test:watch": "node --watch --test tests/js/*.test.mjs",
    "pretest": "php bin/console asset-mapper-test:setup",
    "setup-js": "php bin/console asset-mapper-test:setup"
  }
}
```

### Running the Setup Manually

If you need to set up symlinks without running tests:

```bash
php bin/console asset-mapper-test:setup
```

## How It Works

1. The command reads your `importmap.php` to get the list of all JavaScript packages.
2. For each package it creates a symlink inside `node_modules/` pointing to the corresponding file in `assets/vendor/`.
3. For packages defined with a local `path` (e.g. your own `app.js`), it symlinks directly to that file.
4. Scoped packages (e.g. `@tonejs/midi`) are handled correctly — the parent scope directory is created automatically.
5. A `node_modules/package.json` with `"type": "module"` is created if it does not exist, ensuring ESM compatibility.

## Configuration

The bundle reads configuration automatically from Symfony's `framework.asset_mapper` config. No extra configuration is needed.

If you have a custom `importmap_path` or `vendor_dir` set in `config/packages/asset_mapper.yaml`, the bundle will pick those up automatically.

## Logging

By default the bundle uses a `NullLogger`. If you want to capture logs, define a dedicated Monolog channel named `asset_mapper_test` in your `config/packages/monolog.yaml`:

```yaml
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

## Requirements

- PHP >= 8.2
- Symfony >= 6.4
- Symfony AssetMapper component

## License

MIT — see [LICENSE](LICENSE) for details.
