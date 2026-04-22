// Integration tests for src/Resources/loader/loader.mjs
//
// Strategy: build a throw-away "project" in a tmp dir, write a fake
// importmap.json there, then spawn a child `node --import register.mjs`
// process whose CWD is that tmp dir. The child imports a test entry file
// that exercises one scenario per test and prints the result on stdout.
// We assert on stdout to verify the loader resolved correctly.
//
// Requires Node.js >= 20.6 / >= 18.19 (stable module.register).

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync, realpathSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname, resolve as pathResolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const __dirname = dirname(fileURLToPath(import.meta.url));
const LOADER_DIR = pathResolve(__dirname, '..', '..', 'src', 'Resources', 'loader');
const REGISTER_MJS = join(LOADER_DIR, 'register.mjs');

function makeProject() {
    const root = realpathSync(mkdtempSync(join(tmpdir(), 'amtb-loader-')));
    mkdirSync(join(root, 'assets', 'vendor'), { recursive: true });
    mkdirSync(join(root, 'var', 'asset-mapper-test'), { recursive: true });
    return root;
}

function writeImportmap(root, entries) {
    writeFileSync(
        join(root, 'var', 'asset-mapper-test', 'importmap.json'),
        JSON.stringify({ projectDir: root, vendorDir: join(root, 'assets', 'vendor'), entries }, null, 2),
    );
}

function runChild(root, entryRelPath) {
    const res = spawnSync(
        process.execPath,
        ['--import', REGISTER_MJS, join(root, entryRelPath)],
        { cwd: root, encoding: 'utf8' },
    );
    return { stdout: res.stdout, stderr: res.stderr, status: res.status };
}

test('resolves exact directory package via index.js', () => {
    const root = makeProject();
    try {
        const pkgDir = join(root, 'assets', 'vendor', 'happy-dom');
        mkdirSync(pkgDir, { recursive: true });
        writeFileSync(join(pkgDir, 'index.js'), 'export default "HAPPY_DOM_OK";\n');

        writeImportmap(root, { 'happy-dom': { kind: 'dir', path: pkgDir } });

        writeFileSync(
            join(root, 'entry.mjs'),
            'import v from "happy-dom"; console.log("RESULT:" + v);\n',
        );

        const r = runChild(root, 'entry.mjs');
        assert.equal(r.status, 0, r.stderr);
        assert.match(r.stdout, /RESULT:HAPPY_DOM_OK/);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});

test('resolves subpath import (pkg/sub/file.js)', () => {
    const root = makeProject();
    try {
        const pkgDir = join(root, 'assets', 'vendor', 'happy-dom');
        mkdirSync(join(pkgDir, 'lib'), { recursive: true });
        writeFileSync(join(pkgDir, 'index.js'), 'export default "root";\n');
        writeFileSync(join(pkgDir, 'lib', 'util.js'), 'export default "SUBPATH_OK";\n');

        writeImportmap(root, { 'happy-dom': { kind: 'dir', path: pkgDir } });

        writeFileSync(
            join(root, 'entry.mjs'),
            'import v from "happy-dom/lib/util.js"; console.log("RESULT:" + v);\n',
        );

        const r = runChild(root, 'entry.mjs');
        assert.equal(r.status, 0, r.stderr);
        assert.match(r.stdout, /RESULT:SUBPATH_OK/);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});

test('resolves scoped package with subpath (@scope/pkg/sub)', () => {
    const root = makeProject();
    try {
        const pkgDir = join(root, 'assets', 'vendor', '@acme', 'widget');
        mkdirSync(join(pkgDir, 'sub'), { recursive: true });
        writeFileSync(join(pkgDir, 'index.js'), 'export default "scoped-root";\n');
        writeFileSync(join(pkgDir, 'sub', 'x.js'), 'export default "SCOPED_SUB_OK";\n');

        writeImportmap(root, { '@acme/widget': { kind: 'dir', path: pkgDir } });

        writeFileSync(
            join(root, 'entry.mjs'),
            'import v from "@acme/widget/sub/x.js"; console.log("RESULT:" + v);\n',
        );

        const r = runChild(root, 'entry.mjs');
        assert.equal(r.status, 0, r.stderr);
        assert.match(r.stdout, /RESULT:SCOPED_SUB_OK/);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});

test('resolves file-kind entry (single-file package)', () => {
    const root = makeProject();
    try {
        const file = join(root, 'assets', 'app.js');
        writeFileSync(file, 'export default "APP_OK";\n');

        writeImportmap(root, { app: { kind: 'file', path: file } });

        writeFileSync(
            join(root, 'entry.mjs'),
            'import v from "app"; console.log("RESULT:" + v);\n',
        );

        const r = runChild(root, 'entry.mjs');
        assert.equal(r.status, 0, r.stderr);
        assert.match(r.stdout, /RESULT:APP_OK/);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});

test('delegates relative and node: specifiers to default resolver', () => {
    const root = makeProject();
    try {
        writeImportmap(root, {});

        writeFileSync(join(root, 'helper.mjs'), 'export default "REL_OK";\n');
        writeFileSync(
            join(root, 'entry.mjs'),
            'import path from "node:path";\n' +
            'import v from "./helper.mjs";\n' +
            'console.log("RESULT:" + v + ":" + typeof path.join);\n',
        );

        const r = runChild(root, 'entry.mjs');
        assert.equal(r.status, 0, r.stderr);
        assert.match(r.stdout, /RESULT:REL_OK:function/);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});

test('fails with clear error when importmap.json is missing', () => {
    const root = realpathSync(mkdtempSync(join(tmpdir(), 'amtb-loader-missing-')));
    try {
        writeFileSync(join(root, 'entry.mjs'), 'console.log("should not run");\n');
        const r = runChild(root, 'entry.mjs');
        assert.notEqual(r.status, 0);
        assert.match(r.stderr, /importmap\.json not found/);
        assert.match(r.stderr, /asset-mapper-test:export/);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});

test('unknown bare specifier falls through to Node (ERR_MODULE_NOT_FOUND)', () => {
    const root = makeProject();
    try {
        writeImportmap(root, {});
        writeFileSync(join(root, 'entry.mjs'), 'import "totally-unknown-pkg";\n');

        const r = runChild(root, 'entry.mjs');
        assert.notEqual(r.status, 0);
        assert.match(r.stderr, /Cannot find package|ERR_MODULE_NOT_FOUND/);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});
