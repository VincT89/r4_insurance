import { cp, mkdir, readdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const target = path.join(root, 'public/vendor/ocr');
await mkdir(path.join(target, 'core'), { recursive: true });
await mkdir(path.join(target, 'lang'), { recursive: true });
await cp(path.join(root, 'node_modules/tesseract.js/dist/worker.min.js'), path.join(target, 'worker.min.js'));
for (const file of await readdir(path.join(root, 'node_modules/tesseract.js-core'))) {
    if (/\.wasm(?:\.js)?$/.test(file) || file === 'LICENSE') await cp(path.join(root, 'node_modules/tesseract.js-core', file), path.join(target, 'core', file));
}
await cp(path.join(root, 'node_modules/@tesseract.js-data/ita/4.0.0_best_int/ita.traineddata.gz'), path.join(target, 'lang/ita.traineddata.gz'));
