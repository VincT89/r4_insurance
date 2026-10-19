import { build } from 'esbuild';
import { createRequire, builtinModules } from 'node:module';
import { mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const directory = new URL('../node_modules/.cache/r4-insurance/', import.meta.url);
const excelRequire = createRequire(require.resolve('exceljs/package.json'));
if (excelRequire('uuid/package.json').version !== '11.1.1') throw new Error('La dipendenza UUID di Excel non corrisponde alla versione verificata.');
await mkdir(directory, { recursive: true });
/** Rebuild upstream sources so the browser receives patched UUID code instead of the old embedded dependency. */
await build({
    entryPoints: [require.resolve('exceljs/lib/exceljs.bare.js')],
    outfile: fileURLToPath(new URL('exceljs.cjs', directory)),
    bundle: true, platform: 'browser', format: 'cjs', target: 'es2020',
    inject: [fileURLToPath(new URL('excel-browser-globals.mjs', import.meta.url))],
    plugins: [{ name: 'excel-browser-builtins', setup(build) {
        const replacements = { buffer: 'buffer/', stream: 'stream-browserify', events: 'events/', util: 'util/', assert: 'assert/', string_decoder: 'string_decoder/' };
        build.onResolve({ filter: /^(node:)?[a-z_]+$/ }, args => {
            const name = args.path.replace(/^node:/, '');
            if (replacements[name]) return { path: require.resolve(replacements[name]) };
            if (name === 'crypto' || name === 'fs') return { path: fileURLToPath(new URL(`excel-browser-${name}.cjs`, import.meta.url)) };
            if (builtinModules.includes(name)) throw new Error(`Modulo Node non previsto nell'esportazione: ${name}`);
        });
    } }],
});
console.log('Esportazione Excel ricompilata con le dipendenze aggiornate.');
