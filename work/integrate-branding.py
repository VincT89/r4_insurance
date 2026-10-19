from pathlib import Path
import base64
import re

root = Path(__file__).resolve().parent.parent
source = root.parent / 'logo.png'
image = source.read_bytes()
data = base64.b64encode(image).decode('ascii')
# SVG viewports use the supplied PNG without redrawing or modifying its pixels.
# The original contains transparent lateral padding: the visible logo is x=225..625.
image_element = f'<image width="849" height="50" href="data:image/png;base64,{data}"/>'
(root / 'public/images/agency-logo-original.png').write_bytes(image)
(root / 'public/images/agency-logo.svg').write_text(f'<svg xmlns="http://www.w3.org/2000/svg" width="401" height="50" viewBox="225 0 401 50">{image_element}</svg>\n', encoding='utf-8')
(root / 'public/favicon.svg').write_text(f'<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 96 96"><rect width="96" height="96" fill="#ffffff"/><svg x="4" y="20" width="88" height="56" viewBox="235 0 83 50" overflow="hidden">{image_element}</svg></svg>\n', encoding='utf-8')

for name in ['login', 'first-access']:
    path = root / f'resources/views/auth/{name}.blade.php'
    text = path.read_text(encoding='utf-8')
    text = text.replace('Sodano Consulting S.r.l.', 'R4 Insurance').replace('Sodano Consulting', 'R4 Insurance')
    text = text.replace("asset('images/logo.png')", "asset('images/agency-logo.svg')")
    text = text.replace('alt="R4 Insurance"', 'alt="{{ config(\'branding.logo_alt\') }}"')
    text = re.sub(r'\s*<span class="login-logo-name">.*?</span>', '', text)
    text = re.sub(r'\s*<div class="login-stat-strip">.*?</div>\s*</div>\s*<div class="login-copy">', '\n        <div class="login-copy">', text, flags=re.S)
    text = text.replace("© 2015–{{ date('Y') }}", "© {{ date('Y') }}")
    text = text.replace('nome@sodanoconsulting.it', 'La tua email')
    text = text.replace('Entra →', 'Entra').replace('Salva e Accedi →', 'Salva e accedi')
    text = text.replace('<div class="login-right">', '<div class="login-right">\n      <img class="login-mobile-logo" src="{{ asset(\'images/agency-logo.svg\') }}" alt="{{ config(\'branding.logo_alt\') }}" width="401" height="50">')
    if name == 'login':
        text = text.replace('Strategia,<br>Innovazione,<br>\n          <strong>Performance.</strong>', 'Clienti, polizze<br>e documenti.')
        text = text.replace('        </div>\n      </div>\n\n      <div class="login-left-bottom">', '        </div>\n        <p class="login-description">Accedi al gestionale per consultare le anagrafiche e seguire le scadenze assicurative.</p>\n      </div>\n\n      <div class="login-left-bottom">', 1)
    path.write_text(text, encoding='utf-8')

path = root / 'resources/views/errors/404.blade.php'
path.write_text(path.read_text(encoding='utf-8').replace('Sodano Consulting', 'R4 Insurance'), encoding='utf-8')
path = root / 'resources/views/components/legal-document.blade.php'
text = path.read_text(encoding='utf-8')
text = text.replace("$title.' - Agency Core'", "$title.' - '.config('branding.name')")
text = text.replace('<img src="{{ asset(\'images/logo.png\') }}" alt="">\n                <span>Sodano Consulting</span>', '<img class="brand-full" src="{{ asset(\'images/agency-logo.svg\') }}" alt="{{ config(\'branding.logo_alt\') }}">')
text = text.replace('<p class="legal-kicker">Agency Core</p>', '<p class="legal-kicker">{{ config(\'branding.name\') }}</p>')
# Legal identities and addresses require verified replacement texts, not a visual rename.
path.write_text(text, encoding='utf-8')
print('Logo originale integrato, favicon SVG e riferimenti visivi aggiornati.')
