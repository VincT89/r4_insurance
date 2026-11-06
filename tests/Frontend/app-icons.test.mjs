import test from 'node:test';
import assert from 'node:assert/strict';
import { readdir, readFile } from 'node:fs/promises';
import { appIcons } from '../../resources/js/app-icons.js';

async function bladeFiles(directory) {
    const entries = await readdir(directory, { withFileTypes: true });
    const files = await Promise.all(entries.map(entry => entry.isDirectory()
        ? bladeFiles(new URL(`${entry.name}/`, directory))
        : entry.name.endsWith('.blade.php') ? [new URL(entry.name, directory)] : []));
    return files.flat();
}

test('the small icon bundle covers every literal view icon and dynamic shared state', async () => {
    const names = new Set(['alert-circle', 'alert-triangle', 'check-circle', 'x-circle', 'info', 'inbox', 'loader', 'bell', 'credit-card', 'mail']);
    for (const file of await bladeFiles(new URL('../../resources/views/', import.meta.url))) {
        const source = await readFile(file, 'utf8');
        for (const match of source.matchAll(/\b(?:data-lucide|icon)="([a-z][a-z0-9-]*)"/g)) names.add(match[1]);
    }
    for (const name of names) {
        const key = name.split('-').map(part => part[0].toUpperCase() + part.slice(1)).join('');
        assert.ok(appIcons[key], `Missing icon: ${name}`);
    }
    assert.ok(Object.keys(appIcons).length < 50, 'Keep the main bundle limited to the icons used by the app.');
});
