<section class="model-editor" aria-labelledby="editor-heading">
    <div class="model-editor-heading">
        <h2 id="editor-heading">Contenuto del modello</h2>
        <label data-editor-section-label hidden>Modifica
            <select class="form-sel" data-editor-section aria-label="Parte del documento"><option value="ops">Testo</option><option value="header">Intestazione</option><option value="footer">Piè di pagina</option></select>
        </label>
    </div>
    <div class="model-toolbar" role="group" aria-label="Formattazione del documento" hidden>
        <select aria-label="Stile del paragrafo" data-format="header"><option value="">Testo normale</option><option value="1">Titolo</option><option value="2">Sottotitolo</option><option value="3">Titolo minore</option></select>
        @foreach(['bold'=>['Grassetto','G'],'italic'=>['Corsivo','C'],'underline'=>['Sottolineato','S']] as $key=>[$label,$short])
            <button type="button" data-format="{{ $key }}" class="mark-{{ $key }}" title="{{ $label }}" aria-label="{{ $label }}" aria-pressed="false">{{ $short }}</button>
        @endforeach
        <select aria-label="Allineamento" data-format="align"><option value="">A sinistra</option><option value="center">Centrato</option><option value="right">A destra</option><option value="justify">Giustificato</option></select>
        <select aria-label="Elenco" data-format="list"><option value="">Senza elenco</option><option value="bullet">Elenco puntato</option><option value="ordered">Elenco numerato</option></select>
        <button type="button" data-history="undo" title="Annulla l’ultima modifica">Annulla</button><button type="button" data-history="redo" title="Ripeti l’ultima modifica">Ripeti</button>
        <select aria-label="Inserisci campo automatico" data-insert-field><option value="">Inserisci campo…</option>@foreach($fields as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
        <details class="model-more"><summary>Altri strumenti</summary><div>
            <select aria-label="Carattere" data-format="font"><option value="">Sans serif</option><option value="serif">Serif</option><option value="monospace">Monospazio</option></select>
            <select aria-label="Dimensione testo" data-format="size"><option value="">11 pt</option>@foreach([8,9,10,12,14,16,18,20,24,28,32,36] as $size)<option value="{{ $size }}pt">{{ $size }} pt</option>@endforeach</select>
            <label>Colore <input type="color" data-format="color" aria-label="Colore del testo" value="#172033"></label>
            <label>Evidenzia <input type="color" data-format="background" aria-label="Colore di evidenziazione" value="#fff2a8"></label>
            <select aria-label="Tabella" data-table><option value="">Tabella…</option><option value="insertTable">Inserisci tabella 2 × 2</option><option value="insertRowBelow">Aggiungi riga</option><option value="insertColumnRight">Aggiungi colonna</option><option value="deleteRow">Elimina riga</option><option value="deleteColumn">Elimina colonna</option><option value="deleteTable">Elimina tabella</option></select>
            <button type="button" data-image-open>Immagine o logo</button>
            <button type="button" data-page-break>Interruzione di pagina</button>
            <button type="button" data-clean>Rimuovi formattazione</button>
        </div></details>
    </div>
    <div class="model-image-options" data-image-panel hidden>
        <div class="form-row">
            <x-form-group label="Immagine PNG o JPEG" name="model_image"><input class="form-in" type="file" id="field-model_image" accept="image/png,image/jpeg" data-image-file aria-label="Immagine PNG o JPEG"></x-form-group>
            <x-form-group label="Descrizione dell’immagine" name="model_image_description"><input class="form-in" id="field-model_image_description" maxlength="160" data-image-alt aria-label="Descrizione dell’immagine" placeholder="Per esempio, logo dell’agenzia"></x-form-group>
        </div>
        <div class="form-row">
            <x-form-group label="Larghezza dell’immagine" name="model_image_width"><select class="form-sel" data-image-width aria-label="Larghezza dell’immagine">@foreach([25,50,75,100] as $width)<option value="{{ $width }}">{{ $width }}% della riga</option>@endforeach</select></x-form-group>
            <x-form-group label="Allineamento dell’immagine" name="model_image_align"><select class="form-sel" data-image-align aria-label="Allineamento dell’immagine"><option value="left">A sinistra</option><option value="center">Al centro</option><option value="right">A destra</option></select></x-form-group>
        </div>
        <div class="insurance-form-actions"><button type="button" class="btn btn-p" data-image-insert>Inserisci immagine</button><button type="button" class="btn btn-g" data-image-close>Chiudi</button></div>
    </div>
    @foreach(['ops'=>'Testo del documento','header'=>'Intestazione del documento','footer'=>'Piè di pagina del documento'] as $part=>$label)
        <div data-editor-sheet data-editor-part="{{ $part }}" data-editor-label="{{ $label }}" hidden></div>
    @endforeach
    <label data-fallback-label for="template-body">Testo del modello</label>
    <textarea class="form-ta" id="template-body" data-fallback name="body" rows="12" maxlength="50000" @readonly($rich)>{{ $safeOld('body',$template->plain_body) }}</textarea>
    <input type="hidden" data-editor-content>
    <script type="application/json" data-editor-initial>@json($initialEditorData)</script>
    <p class="u-text-meta" data-editor-status role="status"></p>
    <details class="template-options model-page-options" data-page-options hidden>
        <summary>Impostazioni della pagina</summary>
        <div class="model-page-fields">
            <label>Formato carta<select class="form-sel" data-layout="paper"><option value="a4" @selected($initialEditorData['layout']['paper']==='a4')>A4</option><option value="letter" @selected($initialEditorData['layout']['paper']==='letter')>Letter</option></select></label>
            <label>Orientamento<select class="form-sel" data-layout="orientation"><option value="portrait" @selected($initialEditorData['layout']['orientation']==='portrait')>Verticale</option><option value="landscape" @selected($initialEditorData['layout']['orientation']==='landscape')>Orizzontale</option></select></label>
            @foreach(['margin_top'=>'Margine superiore','margin_bottom'=>'Margine inferiore','margin_left'=>'Margine sinistro','margin_right'=>'Margine destro'] as $key=>$label)
                <label>{{ $label }} in mm<input class="form-in" type="number" min="5" max="40" step="1" data-layout="{{ $key }}" value="{{ $initialEditorData['layout'][$key] }}"></label>
            @endforeach
        </div>
        <label class="insurance-checkbox"><input type="checkbox" data-layout="repeat_header" @checked($initialEditorData['layout']['repeat_header'])> Ripeti l’intestazione su ogni pagina</label>
        <label class="model-page-space" data-layout-space="repeat_header" @if(!$initialEditorData['layout']['repeat_header']) hidden @endif>Spazio minimo per l’intestazione in mm<input class="form-in" type="number" min="5" max="60" step="1" data-layout="header_space" value="{{ $initialEditorData['layout']['header_space'] }}"></label>
        <label class="insurance-checkbox"><input type="checkbox" data-layout="repeat_footer" @checked($initialEditorData['layout']['repeat_footer'])> Ripeti il piè di pagina su ogni pagina</label>
        <label class="model-page-space" data-layout-space="repeat_footer" @if(!$initialEditorData['layout']['repeat_footer']) hidden @endif>Spazio minimo per il piè di pagina in mm<input class="form-in" type="number" min="5" max="60" step="1" data-layout="footer_space" value="{{ $initialEditorData['layout']['footer_space'] }}"></label>
        <p class="u-text-meta">L’anteprima PDF mostra l’impaginazione di stampa. Lo spazio di intestazione e piè di pagina si adatta al contenuto.</p>
    </details>
    @error('body')<p class="invalid-feedback" role="alert">{{ $message }}</p>@enderror
    @error('editor_content')<p class="invalid-feedback" role="alert">{{ $message }}</p>@enderror
</section>
