import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import postcss from 'postcss';
import scopePdfViewer from '../../scripts/scope-pdf-viewer.mjs';

const source = path.resolve('node_modules/pdfjs-dist/web/pdf_viewer.css');

test('PDF styles isolate sidebar and variables while preserving nested rules and animations', async () => {
    const css = ':root { --scale-factor: 1; } .sidebar, .dialog { position: relative; &:focus { color: red; } } @media (max-width: 600px) { .sidebar { width: 200px; } } @keyframes spin { from { opacity: 0; } to { opacity: 1; } }';
    const result = await postcss([scopePdfViewer()]).process(css, { from: source });
    assert.match(result.css, /\.document-preview \{ --scale-factor/);
    assert.match(result.css, /\.document-preview \.sidebar, \.document-preview \.dialog/);
    assert.match(result.css, /&:focus/);
    assert.match(result.css, /@media[^}]+\.document-preview \.sidebar/);
    assert.match(result.css, /@keyframes spin \{ from/);
    const unchanged = await postcss([scopePdfViewer()]).process(css, { from: 'resources/css/app.css' });
    assert.equal(unchanged.css, css);
});

test('the installed PDF viewer has no unscoped top-level selectors after processing', async () => {
    const css = await readFile(source, 'utf8');
    const result = await postcss([scopePdfViewer()]).process(css, { from: source });
    result.root.walkRules(rule => {
        for (let parent = rule.parent; parent && parent !== result.root; parent = parent.parent) {
            if (parent.type === 'rule' || (parent.type === 'atrule' && /keyframes$/i.test(parent.name))) return;
        }
        for (const selector of rule.selectors) assert.ok(selector.startsWith('.document-preview'), selector);
    });
    assert.match(result.css, /\.document-preview \.sidebar\s*\{/);
});
