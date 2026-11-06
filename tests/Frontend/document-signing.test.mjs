import test from 'node:test';
import assert from 'node:assert/strict';
import { PDFDocument, PDFName, degrees } from 'pdf-lib';
import { hasInk } from '../../resources/js/signature-ink.js';
import { signPdf, pagePoint } from '../../scripts/sign-document.mjs';

const strokes = [[[.1,.7],[.2,.2],[.25,.6],[.35,.3],[.4,.7],[.5,.4],[.6,.6]]];
test('an empty pad, a click and a horizontal placeholder do not count as a signature', () => {
    assert.equal(hasInk([]), false);
    assert.equal(hasInk([[[.2,.2]]]), false);
    assert.equal(hasInk([[[.1,.2],[.2,.2],[.3,.2],[.4,.2],[.5,.2]]]), false);
    assert.equal(hasInk(strokes), true);
});
test('page coordinates account for rotation and crop offset', async () => {
    const doc = await PDFDocument.create(); const page = doc.addPage([600,800]); page.setCropBox(20,30,500,700);
    for (const [rotation, expected] of [[0,{x:120,y:520}],[90,{x:170,y:170}],[180,{x:420,y:240}],[270,{x:370,y:590}]]) {
        page.setRotation(degrees(rotation)); assert.deepEqual(pagePoint(page,.2,.3),expected);
    }
});
test('signing retains the original pages and appends the separate registration', async () => {
    const original = await PDFDocument.create(); original.addPage([600,800]).drawText('Contratto originale'); original.addPage([800,600]).setRotation(degrees(90));
    const receipt = await PDFDocument.create(); receipt.addPage().drawText('Registrazione firma');
    const source=await original.save(), copy=source.slice();
    const result=await signPdf(source, await receipt.save(), {maxPages:100,strokes,placements:[{page:2,x:.4,y:.7,width:.3,height:.1}]});
    assert.deepEqual(source,copy); const signed=await PDFDocument.load(result);
    assert.equal(signed.getPageCount(),3); assert.equal(signed.getPage(1).getRotation().angle,90);
    assert.deepEqual(signed.getPage(0).getSize(),{width:600,height:800});
});
test('an existing cryptographic signature is never rewritten', async () => {
    const original=await PDFDocument.create(); original.addPage();
    original.context.register(original.context.obj({Type:PDFName.of('Sig'),ByteRange:[0,10,20,30]}));
    const receipt=await PDFDocument.create(); receipt.addPage();
    await assert.rejects(signPdf(await original.save(),await receipt.save(),{maxPages:100,strokes,placements:[]}),/signed/);
});
test('nonexistent pages and oversized documents are rejected', async () => {
    const original=await PDFDocument.create(); original.addPage(); const bytes=await original.save();
    await assert.rejects(signPdf(bytes,bytes,{maxPages:0,strokes,placements:[]}),/pages/);
    await assert.rejects(signPdf(bytes,bytes,{maxPages:100,strokes,placements:[{page:2}]}),/placement/);
});
