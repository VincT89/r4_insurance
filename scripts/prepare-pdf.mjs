import { cp, mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = path.join(root, 'node_modules/pdfjs-dist');
const target = path.join(root, 'public/vendor/pdfjs');
await mkdir(target, { recursive: true });
for (const folder of ['cmaps', 'standard_fonts', 'wasm']) await cp(path.join(source, folder), path.join(target, folder), { recursive: true });
await cp(path.join(source, 'web/images'), path.join(target, 'images'), { recursive: true });
await cp(path.join(source, 'LICENSE'), path.join(target, 'LICENSE'));
