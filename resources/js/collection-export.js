import { collectionMoney } from './collection-totals.js';
import { createTablePdf, saveExport } from './table-export.js';

export async function exportCollectionReport({format,report,filters,searchFilters}) {
    const stamp = new Date().toLocaleString('it-IT',{timeZone:'Europe/Rome'});
    const date = new Date().toLocaleDateString('sv-SE',{timeZone:'Europe/Rome'});
    const name = report.title.normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-zA-Z0-9]+/g,'-').slice(0,100)+'-'+date;
    if (format === 'xlsx') {
        const {tableWorkbook} = await import('./table-workbook.js');
        const workbook = tableWorkbook({...report,title:report.title,filters,searchFilters,stamp});
        saveExport(await workbook.xlsx.writeBuffer(),'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',name+'.xlsx');
        return;
    }
    const {doc,autoTable} = await createTablePdf();
    const width = doc.internal.pageSize.getWidth(), height = doc.internal.pageSize.getHeight();
    const style = {margin:{top:15,left:12,right:12,bottom:17},styles:{font:'Ubuntu',fontStyle:'normal',fontSize:10,cellPadding:2.5,overflow:'linebreak'},
        headStyles:{fillColor:[11,73,140],textColor:255,fontStyle:'normal'},footStyles:{fillColor:[230,236,243],textColor:[20,35,50],fontStyle:'normal'},
        alternateRowStyles:{fillColor:[244,246,249]},showFoot:'lastPage',rowPageBreak:'avoid'};
    const write = (value,y,size=10) => {
        doc.setFontSize(size);
        for (const line of doc.splitTextToSize(value,width-24)) {
            if (y > height-22) {doc.addPage();y=16;}
            doc.text(line,12,y);y+=size*.5;
        }
        return y;
    };
    let y=write(report.title,16,15);
    y=write(`Preparato il ${stamp}`,y+2);
    y=write(searchFilters || 'Tutti i movimenti',y+1);
    if(filters) y=write(`Altri filtri: ${filters}`,y+1);
    y=write('Data effettiva del pagamento. Gli storni restano visibili e sono esclusi dai totali.',y+2);
    autoTable(doc,{...style,startY:y+4,head:[report.summary.headers],body:report.summary.rows,foot:report.summary.footers,
        columnStyles:{2:{halign:'right'},3:{halign:'right'},4:{halign:'right'},5:{halign:'right'},6:{halign:'right'}}});
    for (const group of report.groups) {
        if(report.groups.length>1) {doc.addPage();y=16;} else y=doc.lastAutoTable.finalY+12;
        if(y>height-45) {doc.addPage();y=16;}
        y=write(`${group.company} / ${group.agency}`,y,12);
        const rows=group.details.map(row=>[
            row[0], [row[1],row[2] !== 'Non indicato' ? `Consulente: ${row[2]}` : ''].filter(Boolean).join('\n'),row[5],row[6],row[7],row[8],
            [row[9],row[10],row[12] !== 'Non indicato' ? `Rif. ${row[12]}` : ''].filter(Boolean).join('\n'),
        ]);
        autoTable(doc,{...style,startY:y+3,head:[['Data','Contraente','Polizza / titolo','Incasso','Rimborso','Tipo pagamento','Gestito da / riferimenti']],body:rows,
            columnStyles:{0:{cellWidth:26},1:{cellWidth:49},2:{cellWidth:43},3:{cellWidth:25,halign:'right'},4:{cellWidth:25,halign:'right'},5:{cellWidth:50},6:{cellWidth:55}},
            foot:[[{content:'Totale, esclusi storni',colSpan:3},collectionMoney(group.receipts),collectionMoney(group.refunds),'Saldo',collectionMoney(group.total)]]});
    }
    for(let page=1;page<=doc.getNumberOfPages();page++) {
        doc.setPage(page);doc.setFontSize(9);doc.text(`Contabilità / Pagina ${page} di ${doc.getNumberOfPages()}`,12,height-7);
    }
    saveExport(doc.output('arraybuffer'),'application/pdf',name+'.pdf');
}
