import { readFileSync, existsSync } from 'node:fs';
import { join, isAbsolute } from 'node:path';

const DEFAULT_MAP_PATH = 'var/asset-mapper-test/importmap.json';

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

export function assetMapperVitePlugin() {
    let entries = {};
    return {
        name: 'asset-mapper-vite-plugin',
        enforce: 'pre',
        configResolved() {
            const mapPath = join(process.cwd(), DEFAULT_MAP_PATH);
            if (existsSync(mapPath)) {
                const data = JSON.parse(readFileSync(mapPath, 'utf8'));
                entries = data.entries ?? {};
            }
        },
        resolveId(source) {
            if (entries[source]) {
                const exact = entries[source];
                return exact.kind === 'dir' ? join(exact.path, 'index.js') : exact.path;
            }
            
            const [pkg, sub] = splitPkg(source);
            if (sub && entries[pkg]?.kind === 'dir') {
                let filePath = join(entries[pkg].path, sub);
                if (!existsSync(filePath) && existsSync(filePath + '.js')) {
                    filePath += '.js';
                }
                return filePath;
            }
            
            return null;
        }
    };
}
