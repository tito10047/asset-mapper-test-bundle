// Node.js Module Customization Hooks — resolves bare specifiers listed in
// importmap.json (exported by `php bin/console asset-mapper-test:export`)
// directly to files under assets/vendor/ (or project-local paths), without
// creating any symlinks in node_modules.
//
// Requires Node.js >= 20.6 (or >= 18.19) for the stable `module.register` API.

import { readFileSync, existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { join, isAbsolute } from 'node:path';

const DEFAULT_MAP_PATH = 'var/asset-mapper-test/importmap.json';

function loadMap() {
    const envPath = process.env.ASSET_MAPPER_IMPORTMAP;
    const mapPath = envPath && envPath.length > 0
        ? (isAbsolute(envPath) ? envPath : join(process.cwd(), envPath))
        : join(process.cwd(), DEFAULT_MAP_PATH);

    if (!existsSync(mapPath)) {
        throw new Error(
            `[asset-mapper-test] importmap.json not found at "${mapPath}". ` +
            `Run: php bin/console asset-mapper-test:export`
        );
    }

    try {
        return JSON.parse(readFileSync(mapPath, 'utf8'));
    } catch (e) {
        throw new Error(`[asset-mapper-test] Failed to parse ${mapPath}: ${e.message}`);
    }
}

const data = loadMap();
const entries = data.entries ?? {};

// Split a bare specifier into [packageName, subpath|null].
// Scope-aware: "@scope/pkg/sub/file.js" -> ["@scope/pkg", "sub/file.js"].
function splitPkg(spec) {
    if (spec.startsWith('@')) {
        const firstSlash = spec.indexOf('/');
        if (firstSlash === -1) return [spec, null];
        const secondSlash = spec.indexOf('/', firstSlash + 1);
        return secondSlash === -1
            ? [spec, null]
            : [spec.slice(0, secondSlash), spec.slice(secondSlash + 1)];
    }
    const i = spec.indexOf('/');
    return i === -1 ? [spec, null] : [spec.slice(0, i), spec.slice(i + 1)];
}

function toFileUrl(absPath) {
    return pathToFileURL(absPath).href;
}

const RELATIVE_OR_URL = /^(\.\.?\/|\/|file:|node:|data:|https?:)/;

export async function resolve(specifier, context, nextResolve) {
    if (RELATIVE_OR_URL.test(specifier)) {
        return nextResolve(specifier, context);
    }

    // 1) Exact match — package name directly present in importmap
    const exact = entries[specifier];
    if (exact) {
        const file = exact.kind === 'dir' ? join(exact.path, 'index.js') : exact.path;
        return { url: toFileUrl(file), format: 'module', shortCircuit: true };
    }

    // 2) Subpath (scope-aware): "happy-dom/lib/Foo.js" or "@scope/pkg/sub/file.js"
    const [pkg, sub] = splitPkg(specifier);
    if (sub && entries[pkg]?.kind === 'dir') {
        return {
            url: toFileUrl(join(entries[pkg].path, sub)),
            format: 'module',
            shortCircuit: true,
        };
    }

    // 3) Not ours — delegate to Node. If it fails the user will see a clear
    //    ERR_MODULE_NOT_FOUND; encourage `importmap:require`.
    return nextResolve(specifier, context);
}

export async function load(url, context, nextLoad) {
    // Ensure .js files we resolved are treated as ESM even without an adjacent
    // package.json (assets/vendor generally has none). Do not override an
    // already-determined format (e.g. 'commonjs').
    if (context.format) {
        return nextLoad(url, context);
    }
    if (url.startsWith('file:') && url.endsWith('.js')) {
        return nextLoad(url, { ...context, format: 'module' });
    }
    return nextLoad(url, context);
}
