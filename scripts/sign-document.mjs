import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { PDFDocument, PDFDict, PDFName, rgb } from 'pdf-lib';

export function pagePoint(page, x, y) {
    const box = page.getCropBox();
    const rotation = ((page.getRotation().angle % 360) + 360) % 360;
    if (rotation === 90) return { x: box.x + y * box.width, y: box.y + x * box.height };
    if (rotation === 180) return { x: box.x + (1 - x) * box.width, y: box.y + y * box.height };
    if (rotation === 270) return { x: box.x + (1 - y) * box.width, y: box.y + (1 - x) * box.height };
    return { x: box.x + x * box.width, y: box.y + (1 - y) * box.height };
}

export async function signPdf(source, receipt, input) {
    let document;
    try { document = await PDFDocument.load(source, { updateMetadata: false }); }
    catch (error) { throw new Error(error.message?.includes('encrypted') ? 'encrypted' : 'invalid'); }
    if (document.getPageCount() > input.maxPages) throw new Error('pages');
    /** Rewriting an existing cryptographic signature would invalidate it, even if the visible page looked unchanged. */
    for (const [, object] of document.context.enumerateIndirectObjects()) {
        if (!(object instanceof PDFDict)) continue;
        if (object.has(PDFName.of('ByteRange')) || object.get(PDFName.of('Type'))?.toString() === '/Sig') throw new Error('signed');
    }
    const form = document.getForm();
    if (form.getFields().length) form.flatten();
    for (const placement of input.placements) {
        if (!Number.isInteger(placement.page) || placement.page < 1 || placement.page > document.getPageCount()) throw new Error('placement');
        const page = document.getPage(placement.page - 1);
        for (const stroke of input.strokes) {
            for (let index = 1; index < stroke.length; index++) {
                const point = value => pagePoint(page, placement.x + value[0] * placement.width, placement.y + value[1] * placement.height);
                page.drawLine({ start: point(stroke[index - 1]), end: point(stroke[index]), thickness: 1.4, color: rgb(0.06, 0.10, 0.15) });
            }
        }
    }
    const proof = await PDFDocument.load(receipt);
    const pages = await document.copyPages(proof, proof.getPageIndices());
    pages.forEach(page => document.addPage(page));
    return document.save({ updateFieldAppearances: false });
}

if (process.argv[1] && path.basename(process.argv[1]) === 'sign-document.mjs') {
    try {
        let body = '';
        for await (const chunk of process.stdin) { body += chunk; if (body.length > 300000) throw new Error('invalid'); }
        const directory = process.argv[2];
        const result = await signPdf(await readFile(path.join(directory, 'source.pdf')), await readFile(path.join(directory, 'receipt.pdf')), JSON.parse(body));
        await writeFile(path.join(directory, 'signed.pdf'), result, { mode: 0o600 });
        process.stdout.write(JSON.stringify({ ok: true }));
    } catch (error) {
        process.stdout.write(JSON.stringify({ ok: false, error: ['signed', 'encrypted', 'pages', 'placement'].includes(error.message) ? error.message : 'invalid' }));
        process.exitCode = 1;
    }
}
