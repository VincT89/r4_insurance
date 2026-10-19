const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root = path.resolve(__dirname, '..');

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    try {
        const page = await browser.newPage();
        await page.route('**/*', route => route.abort());
        const source = fs.readFileSync(path.join(root, 'public/favicon.svg'), 'utf8');
        const svg = source
            .replace(/<circle[^>]*data-favicon-background[^>]*\/>/g, '')
            .replace(/(<svg[^>]*viewBox="0 0 128 128"[^>]*>)/, '$1<circle data-favicon-background="white" cx="64" cy="64" r="64" fill="#ffffff"/>')
            .replace(/<svg x="[^"]+" y="[^"]+" width="[^"]+" height="[^"]+" viewBox="235 0 [^"]+">/, '<svg x="8" y="28" width="112" height="72" viewBox="235 0 92 50">');
        fs.writeFileSync(path.join(root, 'public/favicon.svg'), svg);
        const icons = [];
        for (const size of [16, 32, 48, 180]) {
            const result = await page.evaluate(async ({ svg, size }) => {
                const img = new Image();
                img.src = 'data:image/svg+xml;base64,' + btoa(svg);
                await img.decode();
                const canvas = document.createElement('canvas');
                canvas.width = canvas.height = size;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, size, size);
                const rgba = ctx.getImageData(0, 0, size, size).data;
                let ink = 0;
                for (let i = 3; i < rgba.length; i += 4) if (rgba[i] > 20) ink++;
                return { png: canvas.toDataURL('image/png').split(',')[1], ink };
            }, { svg, size });
            if (!result.ink) throw new Error(`Empty favicon ${size}`);
            const png = Buffer.from(result.png, 'base64');
            const name = size === 180 ? 'apple-touch-icon.png' : `favicon-${size}x${size}.png`;
            fs.writeFileSync(path.join(root, 'public', name), png);
            if (size !== 180) icons.push({ size, png });
        }
        const header = Buffer.alloc(6 + icons.length * 16);
        header.writeUInt16LE(1, 2);
        header.writeUInt16LE(icons.length, 4);
        let offset = header.length;
        icons.forEach(({ size, png }, i) => {
            const at = 6 + i * 16;
            header[at] = header[at + 1] = size;
            header.writeUInt16LE(1, at + 4);
            header.writeUInt16LE(32, at + 6);
            header.writeUInt32LE(png.length, at + 8);
            header.writeUInt32LE(offset, at + 12);
            offset += png.length;
        });
        fs.writeFileSync(path.join(root, 'public/favicon.ico'), Buffer.concat([header, ...icons.map(i => i.png)]));
        console.log('Favicon GS ingrandita su cerchio bianco: PNG 16/32/48/180 e ICO aggiornati.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
