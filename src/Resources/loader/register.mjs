// Registers the asset-mapper-test Node.js loader.
// Use with: node --import <path-to-this-file> ...
import { register } from 'node:module';

register('./loader.mjs', import.meta.url);
