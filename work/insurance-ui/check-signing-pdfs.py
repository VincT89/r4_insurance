from pathlib import Path
import subprocess
import json
import pdfplumber
from PIL import Image, ImageOps, ImageDraw

root = Path(__file__).resolve().parent
renderer = Path('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/native/poppler/Library/bin/pdftoppm.exe')
paths = sorted(root.glob('signing-source-*.pdf')) + [root / name for name in ['signing-preview.pdf', 'signing-client.pdf', 'signing-policy.pdf', 'signing-coverage.pdf']]
report, images = [], []
for source in paths:
    if not source.is_file():
        raise RuntimeError(f'Missing PDF: {source.name}')
    with pdfplumber.open(source) as document:
        pages = document.pages
        text = '\n'.join(page.extract_text() or '' for page in pages)
        assert len(text) > 40, source.name
        assert 'SPADONE IVAN' not in text and '215797176' not in text, source.name
        if 'source-' in source.name or source.name == 'signing-preview.pdf':
            assert 'BOZZA DA REVISIONARE' in text, source.name
        if source.name in ['signing-client.pdf', 'signing-policy.pdf']:
            assert 'Alessandro Varesi' in text and '{{' not in text, source.name
        outside = sum(1 for page in pages for char in page.chars if char['x0'] < -1 or char['x1'] > page.width + 1 or char['top'] < -1 or char['bottom'] > page.height + 1)
        assert outside == 0, (source.name, outside)
        report.append({'file': source.name, 'pages': len(pages), 'characters': len(text), 'outside_page': outside})
    prefix = root / source.stem
    subprocess.run([str(renderer), '-scale-to', '1200', '-png', str(source), str(prefix)], check=True, creationflags=subprocess.CREATE_NO_WINDOW)
    images.extend(sorted(root.glob(source.stem + '-[0-9]*.png')))
for offset in range(0, len(images), 6):
    batch = images[offset:offset + 6]
    sheet = Image.new('RGB', (1200, 1740), '#d6d9df')
    draw = ImageDraw.Draw(sheet)
    for index, image_path in enumerate(batch):
        thumb = ImageOps.contain(Image.open(image_path).convert('RGB'), (385, 820))
        x, y = (index % 3) * 400 + 7, (index // 3) * 870 + 28
        sheet.paste(thumb, (x, y))
        draw.text((x, y - 20), image_path.name, fill='#172033')
    sheet.save(root / f'signing-pdf-overview-{offset // 6 + 1}.png')
(root / 'signing-pdf-report.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
print(json.dumps({'files': len(report), 'pages': sum(item['pages'] for item in report), 'overviews': (len(images) + 5) // 6, 'ok': True}))
