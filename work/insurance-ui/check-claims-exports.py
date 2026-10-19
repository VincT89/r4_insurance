from pathlib import Path
import json
import subprocess
import pdfplumber
import openpyxl

root=Path(__file__).resolve().parent
renderer=Path('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/native/poppler/Library/bin/pdftoppm.exe')
report=[]
for name in ['claims-list','claims-history']:
    workbook=openpyxl.load_workbook(root/(name+'.xlsx'),read_only=True,data_only=False)
    sheet=workbook['Dati']
    values=list(sheet.values)
    rows=values[5:]
    assert rows and len(values[4])==(10 if name.endswith('list') else 4)
    text=' '.join(str(v or '') for row in rows for v in row)
    assert 'SN-2026-1042' in text
    assert 'Mostra dettagli' not in text and '1042Data' not in text
    assert all(cell.data_type!='f' for row in sheet.iter_rows() for cell in row)
    with pdfplumber.open(root/(name+'.pdf')) as pdf:
        text='\n'.join(page.extract_text() or '' for page in pdf.pages)
        assert 'SN-2026-' in text
        outside=sum(1 for p in pdf.pages for c in p.chars if c['x0'] < -1 or c['x1'] > p.width+1 or c['top'] < -1 or c['bottom'] > p.height+1)
        assert outside==0
        report.append({'file':name,'xlsx_rows':len(rows),'pdf_pages':len(pdf.pages),'outside_page':outside})
    subprocess.run([str(renderer),'-scale-to','1200','-png',str(root/(name+'.pdf')),str(root/(name+'-render'))],check=True,creationflags=subprocess.CREATE_NO_WINDOW)
(root/'claims-exports-report.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
print(json.dumps(report))
