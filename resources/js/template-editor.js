import Quill from 'quill';
import 'quill/dist/quill.core.css';

const Embed = Quill.import('blots/embed');
const BlockEmbed = Quill.import('blots/block/embed');
const Delta = Quill.import('delta');
const SizeStyle = Quill.import('attributors/style/size');
SizeStyle.whitelist = ['8pt','9pt','10pt','11pt','12pt','14pt','16pt','18pt','20pt','24pt','28pt','32pt','36pt'];
Quill.register(SizeStyle,true);

class TemplateField extends Embed {
    static blotName = 'field';
    static tagName = 'SPAN';
    static className = 'model-field';
    static create(key) {
        const node = super.create();
        node.dataset.field = key;
        node.setAttribute('contenteditable','false');
        node.textContent = this.labels[key] || key;
        return node;
    }
    static value(node) { return node.dataset.field; }
}
class TemplateImage extends BlockEmbed {
    static blotName = 'image';
    static tagName = 'FIGURE';
    static className = 'model-image';
    static create(value) {
        const node = super.create();
        node.setAttribute('contenteditable','false');
        const img = document.createElement('img');
        if (/^data:image\/(?:png|jpeg);base64,[A-Za-z0-9+/=]+$/.test(value.data || '')) img.src = value.data;
        img.alt = value.alt || '';
        node.dataset.width = String(value.width || 25);
        node.dataset.align = value.align || 'left';
        node.style.width = node.dataset.width + '%';
        node.style.margin = {left:'0 auto 0 0',center:'0 auto',right:'0 0 0 auto'}[node.dataset.align];
        node.append(img);
        return node;
    }
    static value(node) {
        const img = node.querySelector('img');
        return {data:img?.getAttribute('src') || '',alt:img?.alt || '',width:Number(node.dataset.width || 25),align:node.dataset.align || 'left'};
    }
}
class TemplatePageBreak extends BlockEmbed {
    static blotName = 'page_break';
    static tagName = 'DIV';
    static className = 'model-page-break';
    static create() {
        const node = super.create();
        node.setAttribute('aria-label','Interruzione di pagina');
        return node;
    }
    static value() { return true; }
}
Quill.register(TemplateField,true);
Quill.register(TemplateImage,true);
Quill.register(TemplatePageBreak,true);

function plain(ops) {
    return ops.map(op => {
        if (typeof op.insert === 'string') return op.insert;
        if (op.insert.field) return '{{'+op.insert.field+'}}';
        if (op.insert.image) return '[Immagine: '+op.insert.image.alt+']';
        return '\n';
    }).join('');
}
const clipboardTags = new Set('P BR H1 H2 H3 OL UL LI TABLE TBODY TR TD STRONG B EM I U S SPAN FIGURE IMG DIV'.split(' '));
const clipboardClass = /^(?:ql-(?:align-(?:right|center|justify)|font-(?:serif|monospace)|size-(?:small|large|huge))|model-field|model-image|model-page-break)$/;
function copyNode(node) {
    if (node.nodeType === Node.TEXT_NODE) return document.createTextNode(node.textContent.replaceAll('\uFEFF',''));
    const tag = node.nodeType === Node.ELEMENT_NODE && clipboardTags.has(node.tagName) ? node.tagName.toLowerCase() : null;
    const copy = tag ? document.createElement(tag) : document.createDocumentFragment();
    if (tag) {
        const classes = [...node.classList].filter(name => clipboardClass.test(name));
        if (classes.length) copy.className = classes.join(' ');
        if (tag === 'img') {
            const src = node.getAttribute('src') || '';
            if (!/^data:image\/(?:png|jpeg);base64,[A-Za-z0-9+/=]+$/.test(src)) return document.createDocumentFragment();
            copy.src = src;
            copy.alt = node.alt;
        }
        if (tag === 'figure' && ['25','50','75','100'].includes(node.dataset.width)) {
            copy.dataset.width = node.dataset.width;
            copy.dataset.align = ['left','center','right'].includes(node.dataset.align) ? node.dataset.align : 'left';
        }
        if (tag === 'span' && Object.hasOwn(TemplateField.labels,node.dataset.field)) {
            copy.dataset.field = node.dataset.field;
            copy.textContent = TemplateField.labels[node.dataset.field];
            return copy;
        }
        if (tag === 'li' && ['ordered','bullet'].includes(node.dataset.list)) copy.dataset.list = node.dataset.list;
        if (tag === 'td' && /^[a-z\d_-]{1,40}$/i.test(node.dataset.row || '')) copy.dataset.row = node.dataset.row;
        if (/^(?:#[a-f\d]{6}|rgb\(\d{1,3},\s*\d{1,3},\s*\d{1,3}\))$/i.test(node.style.color)) copy.style.color = node.style.color;
        if (/^(?:#[a-f\d]{6}|rgb\(\d{1,3},\s*\d{1,3},\s*\d{1,3}\))$/i.test(node.style.backgroundColor)) copy.style.backgroundColor = node.style.backgroundColor;
        if (SizeStyle.whitelist.includes(node.style.fontSize)) copy.style.fontSize = node.style.fontSize;
    }
    for (const child of node.childNodes) copy.append(copyNode(child));
    return copy;
}
const Clipboard = Quill.import('modules/clipboard');
class TemplateClipboard extends Clipboard {
    /** Copia, Taglia e Incolla ricostruiscono solo i formati ammessi, senza esportazione HTML libera. */
    onCopy(range) {
        const text = plain(this.quill.getContents(range.index,range.length).ops);
        const selection = document.getSelection();
        if (!selection?.rangeCount) return {text,html:''};
        const selected = selection.getRangeAt(0);
        if (!this.quill.root.contains(selected.commonAncestorContainer)) return {text,html:''};
        let fragment = selected.cloneContents();
        let ancestor = selected.commonAncestorContainer;
        if (ancestor.nodeType !== Node.ELEMENT_NODE) ancestor = ancestor.parentElement;
        while (ancestor && ancestor !== this.quill.root) {
            const wrapper = ancestor.cloneNode(false);
            wrapper.append(fragment);
            fragment = wrapper;
            ancestor = ancestor.parentElement;
        }
        const output = document.createElement('div');
        output.append(copyNode(fragment));
        return {text,html:output.innerHTML};
    }
}
Quill.register('modules/clipboard',TemplateClipboard,true);

async function imageData(file) {
    if (!['image/png','image/jpeg'].includes(file.type) || file.size > 5*1024*1024) throw new Error('Scegli un’immagine PNG o JPEG entro 5 MB.');
    const bitmap = await createImageBitmap(file);
    try {
        let ratio = Math.min(1,1400/bitmap.width,1400/bitmap.height);
        const canvas = document.createElement('canvas');
        for (let attempt=0;attempt<5;attempt++) {
            canvas.width = Math.max(1,Math.round(bitmap.width*ratio));
            canvas.height = Math.max(1,Math.round(bitmap.height*ratio));
            canvas.getContext('2d').drawImage(bitmap,0,0,canvas.width,canvas.height);
            const data = canvas.toDataURL(file.type,0.85);
            if (data.length < 680000) return data;
            ratio *= 0.7;
        }
        throw new Error('Riduci le dimensioni dell’immagine e riprova.');
    } finally { bitmap.close(); }
}

export function mount(form) {
    const toolbar = form.querySelector('.model-toolbar');
    const fallback = form.querySelector('[data-fallback]');
    const value = form.querySelector('[data-editor-content]');
    const status = form.querySelector('[data-editor-status]');
    const initial = JSON.parse(form.querySelector('[data-editor-initial]').textContent);
    const sectionSelect = form.querySelector('[data-editor-section]');
    const imagePanel = form.querySelector('[data-image-panel]');
    const imageFile = form.querySelector('[data-image-file]');
    const imageAlt = form.querySelector('[data-image-alt]');
    const imageWidth = form.querySelector('[data-image-width]');
    const imageAlign = form.querySelector('[data-image-align]');
    const imageButton = form.querySelector('[data-image-insert]');
    const editors = {}, ranges = {};
    let section = 'ops', editingImage = null, imageBusy = false;
    TemplateField.labels = initial.fields;
    for (const host of form.querySelectorAll('[data-editor-part]')) {
        const part = host.dataset.editorPart;
        const editor = new Quill(host,{
            modules:{toolbar:false,history:{userOnly:true},table:true},
            formats:['bold','italic','underline','strike','color','background','font','size','align','list','header','field','table','image','page_break'],
            placeholder:part === 'ops' ? 'Scrivi il contenuto del modello…' : 'Scrivi qui oppure inserisci un’immagine o un campo…',
        });
        editor.root.setAttribute('role','textbox');
        editor.root.setAttribute('aria-label',host.dataset.editorLabel);
        editor.root.setAttribute('aria-multiline','true');
        editor.root.setAttribute('lang','it');
        const ops = initial[part].map(op => {
            if (!op.attributes?.size) return op;
            return {...op,attributes:{...op.attributes,size:{small:'8pt',large:'14pt',huge:'20pt'}[op.attributes.size] || op.attributes.size}};
        });
        editor.setContents({ops},'silent');
        editor.history.clear();
        editor.clipboard.addMatcher('IMG',(node) => {
            /** Le immagini remote o incollate fuori da un'immagine del modello passano dal caricamento controllato. */
            const figure = node.closest('figure.model-image');
            return figure ? new Delta().insert({image:TemplateImage.value(figure)}) : new Delta();
        });
        editors[part] = editor;
        ranges[part] = {index:0,length:0};
        /** Anche la scrittura aggiorna il cursore con eventi silenziosi di Quill. */
        editor.on('editor-change',(event,selection) => {
            if (event === 'selection-change' && selection) { ranges[part] = {...selection}; if (part === section) refresh(); }
        });
        editor.on('text-change',() => { sync(); markDirty(); if (part === section) refresh(); status.textContent = ''; });
        editor.root.addEventListener('click',event => {
            const figure = event.target.closest('figure.model-image');
            if (!figure) return;
            const index = editor.getIndex(Quill.find(figure));
            editor.setSelection(index,1,'user');
            openImage({part,index,value:TemplateImage.value(figure)});
        });
    }
    const current = () => editors[section];
    const restoreRange = () => {
        const range = {...ranges[section]};
        current().focus({preventScroll:true});
        current().setSelection(range.index,range.length,'silent');
        ranges[section] = range;
    };
    const refresh = () => {
        const editor = current(), range = ranges[section];
        const formats = editor.getFormat(range.index,range.length);
        toolbar.querySelectorAll('button[data-format]').forEach(button => button.setAttribute('aria-pressed',Boolean(formats[button.dataset.format])));
        toolbar.querySelectorAll('select[data-format]').forEach(select => {select.value = String(formats[select.dataset.format] || '');});
        const inTable = !!editor.getModule('table').getTable(range)[0];
        for (const option of toolbar.querySelector('[data-table]').options) {
            if (option.value) option.disabled = option.value === 'insertTable' ? inTable : !inTable;
        }
        toolbar.querySelector('[data-page-break]').disabled = section !== 'ops' || inTable;
        toolbar.querySelector('[data-history="undo"]').disabled = !editor.history.stack.undo.length;
        toolbar.querySelector('[data-history="redo"]').disabled = !editor.history.stack.redo.length;
    };
    const layout = () => Object.fromEntries([...form.querySelectorAll('[data-layout]')].map(input => [
        input.dataset.layout,input.type === 'checkbox' ? input.checked : (input.type === 'number' ? input.valueAsNumber : input.value),
    ]));
    const sync = () => {
        const document = {ops:editors.ops.getContents().ops,header:editors.header.getContents().ops,footer:editors.footer.getContents().ops,layout:layout()};
        value.value = JSON.stringify(document);
        fallback.value = plain(document.ops).replace(/\n$/,'');
    };
    const markDirty = () => {
        const approve = document.querySelector('[data-approve-template]');
        if (approve) {approve.disabled = true;document.querySelector('[data-approval-hint]').hidden = false;}
    };
    const updateLayout = () => {
        const settings = layout();
        form.style.setProperty('--model-page-width',settings.orientation === 'landscape' ? '1122px' : '794px');
        for (const side of ['top','bottom','left','right']) form.style.setProperty('--model-margin-'+side,Math.max(5,Math.min(40,settings['margin_'+side] || 12))+'mm');
        for (const box of form.querySelectorAll('[data-layout-space]')) box.hidden = !settings[box.dataset.layoutSpace];
        sync();
    };
    const openImage = (selected = null) => {
        editingImage = selected;
        imageFile.value = '';
        imageAlt.value = selected?.value.alt || '';
        imageWidth.value = String(selected?.value.width || 25);
        imageAlign.value = selected?.value.align || 'left';
        imageButton.textContent = selected ? 'Salva immagine' : 'Inserisci immagine';
        imagePanel.hidden = false;
        imageAlt.focus();
    };
    sectionSelect.addEventListener('change',() => {
        section = sectionSelect.value;
        for (const [part,editor] of Object.entries(editors)) editor.container.hidden = part !== section;
        imagePanel.hidden = true;
        editingImage = null;
        status.textContent = '';
        refresh();
        restoreRange();
    });
    form.addEventListener('input',event => {if (!toolbar.contains(event.target)) markDirty();});
    for (const input of form.querySelectorAll('[data-layout]')) input.addEventListener('change',updateLayout);
    toolbar.addEventListener('mousedown',event => {if (event.target.closest('button')) event.preventDefault();});
    toolbar.addEventListener('click',event => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.hasAttribute('data-image-open')) {openImage();return;}
        restoreRange();
        const editor = current(), range = ranges[section];
        if (button.dataset.history) editor.history[button.dataset.history]();
        else if (button.hasAttribute('data-clean')) editor.removeFormat(range.index,range.length,'user');
        else if (button.hasAttribute('data-page-break') && section === 'ops') {
            editor.insertEmbed(range.index,'page_break',true,'user');editor.setSelection(range.index+1,0,'user');
        } else if (button.dataset.format) editor.format(button.dataset.format,!editor.getFormat()[button.dataset.format],'user');
        refresh();
    });
    toolbar.addEventListener('change',event => {
        const input = event.target, choice = input.value;
        restoreRange();
        const editor = current(), range = ranges[section];
        if (input.hasAttribute('data-insert-field') && Object.hasOwn(initial.fields,choice)) {
            if (range.length) editor.deleteText(range.index,range.length,'user');
            editor.insertEmbed(range.index,'field',choice,'user');
            editor.setSelection(range.index+1,0,'user');
            input.value = '';
        } else if (input.hasAttribute('data-table') && choice) {
            const table = editor.getModule('table');
            if (choice === 'insertTable') table.insertTable(2,2);
            else table[choice]();
            input.value = '';
        } else if (input.dataset.format) editor.format(input.dataset.format,input.dataset.format === 'header' && choice ? Number(choice) : choice || false,'user');
        refresh();
    });
    imageFile.addEventListener('change',() => {if (!imageAlt.value.trim() && imageFile.files[0]) imageAlt.value = imageFile.files[0].name.replace(/\.[^.]+$/,'').slice(0,160);});
    form.querySelector('[data-image-close]').addEventListener('click',() => {imagePanel.hidden = true;editingImage = null;});
    imageButton.addEventListener('click',async () => {
        if (imageBusy) return;
        if (!imageAlt.value.trim()) {status.textContent = 'Descrivi brevemente l’immagine.';imageAlt.focus();return;}
        if (!imageFile.files[0] && !editingImage) {status.textContent = 'Scegli l’immagine da inserire.';imageFile.focus();return;}
        imageBusy = imageButton.disabled = true;
        try {
            const data = imageFile.files[0] ? await imageData(imageFile.files[0]) : editingImage.value.data;
            const editor = current();
            restoreRange();
            const index = editingImage?.index ?? ranges[section].index;
            if (editingImage && (editingImage.part !== section || editor.getContents(index,1).ops[0]?.insert.image?.data !== editingImage.value.data)) throw new Error('L’immagine è cambiata. Selezionala di nuovo.');
            editor.history.cutoff();
            if (editingImage) editor.deleteText(index,1,'user');
            editor.insertEmbed(index,'image',{data,alt:imageAlt.value.trim(),width:Number(imageWidth.value),align:imageAlign.value},'user');
            editor.setSelection(index+1,0,'user');
            editor.history.cutoff();
            imagePanel.hidden = true;
            editingImage = null;
            sync();
        } catch (error) {status.textContent = error.message || 'Non è stato possibile leggere l’immagine.';}
        finally {imageBusy = imageButton.disabled = false;}
    });
    form.addEventListener('submit',event => {
        sync();
        if (imageBusy || !fallback.value.trim() || value.value.length > 3000000) {
            event.preventDefault();
            status.textContent = imageBusy ? 'Attendi il caricamento dell’immagine.' : (!fallback.value.trim() ? 'Scrivi il testo del modello o inserisci un’immagine.' : 'Il modello è troppo grande. Riduci le immagini.');
            current().focus();
        }
    });
    value.name = 'editor_content';
    updateLayout();
    fallback.hidden = true;
    form.querySelector('[data-fallback-label]').hidden = true;
    form.querySelector('[data-editor-section-label]').hidden = false;
    form.querySelector('[data-page-options]').hidden = false;
    editors.ops.container.hidden = false;
    toolbar.hidden = false;
    form.querySelector('[data-save-template]').disabled = false;
    refresh();
}
