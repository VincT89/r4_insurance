const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const image = fs.readFileSync(path.resolve(root, '../logo.png')).toString('base64');
// The SVG viewport and mask isolate the existing signature, retaining its strokes.
// The small step in the clip excludes the printed text below the signature's flourish.
const definitions = `<defs><filter id="ink" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB"><feColorMatrix type="matrix" values="0 0 0 0 1 0 0 0 0 1 0 0 0 0 1 -1 0 0 0 1"/></filter><clipPath id="signature"><path d="M235 0H350V16H318V50H235Z"/></clipPath><mask id="signature-ink" maskUnits="userSpaceOnUse" x="235" y="0" width="115" height="50" style="mask-type:alpha"><image width="849" height="50" href="data:image/png;base64,${image}" filter="url(#ink)"/></mask></defs>`;
const signature = `${definitions}<g clip-path="url(#signature)"><rect x="235" width="115" height="50" fill="#0B498C" mask="url(#signature-ink)"/></g>`;
fs.writeFileSync(path.join(root, 'public/images/agency-signature.svg'), `<svg xmlns="http://www.w3.org/2000/svg" width="115" height="50" viewBox="235 0 115 50">${signature}</svg>\n`);
fs.writeFileSync(path.join(root, 'public/favicon.svg'), `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 128 128"><svg x="4" y="36" width="120" height="56" viewBox="235 0 115 50">${signature}</svg></svg>\n`);
for (const name of ['login', 'first-access']) {
    const file = path.join(root, `resources/views/auth/${name}.blade.php`);
    let text = fs.readFileSync(file, 'utf8');
    text = text.replace(`<img src="{{ asset('images/agency-logo.svg') }}" alt="{{ config('branding.logo_alt') }}">`, `<img src="{{ asset('images/agency-signature.svg') }}" alt="" width="115" height="50"><span class="login-logo-name">{{ config('branding.wordmark') }}</span>`);
    text = text.replace(`<img class="login-mobile-logo" src="{{ asset('images/agency-logo.svg') }}" alt="{{ config('branding.logo_alt') }}" width="401" height="50">`, `<div class="login-mobile-brand"><img src="{{ asset('images/agency-signature.svg') }}" alt="" width="115" height="50"><span>{{ config('branding.wordmark') }}</span></div>`);
    fs.writeFileSync(file, text);
}
const legal = path.join(root, 'resources/views/components/legal-document.blade.php');
fs.writeFileSync(legal, fs.readFileSync(legal, 'utf8').replace(`<img class="brand-full" src="{{ asset('images/agency-logo.svg') }}" alt="{{ config('branding.logo_alt') }}">`, `<img class="legal-signature" src="{{ asset('images/agency-signature.svg') }}" alt="" width="115" height="50"><span>{{ config('branding.wordmark') }}</span>`));
console.log('Firma gs isolata in SVG e collegata a logo e favicon.');
