<x-app-layout :title="$policy->exists ? 'Modifica polizza' : 'Nuova polizza'">
    <x-page-header><x-slot:title>{{ $policy->exists ? 'Modifica polizza' : 'Nuova polizza' }}</x-slot:title><x-slot:actions>@if($client->exists)<a href="{{ route('clients.show', $client) }}" class="btn btn-g">{{ $client->name }}</a>@else<a href="{{ route('policies.index') }}" class="btn btn-g">Polizze</a>@endif</x-slot:actions></x-page-header>
    <x-panel padded>
        <form method="POST" enctype="multipart/form-data" action="{{ $policy->exists ? route('clients.policies.update', [$client, $policy]) : ($globalCreate ? route('policies.store') : route('clients.policies.store', $client)) }}" x-data="{ branch: @js(old('category', $policy->category) ?? ''), subbranch: @js(old('subcategory', $policy->subcategory) ?? ''), subbranches: @js($subcategories), company: @js(old('company', $policy->company) ?? ''), agency: @js((string)(old('supplier_agency_id', $policy->supplier_agency_id) ?? '')), agencies: @js($agenciesByCompany) }">
            @csrf
            @if($policy->exists)
                @method('PATCH')
                <input type="hidden" name="policy_revision" value="{{ old('policy_revision', $policy->editFingerprint()) }}">
                @error('policy_revision')
                    <x-form-section title="Controlla gli aggiornamenti prima di salvare">
                        <p class="invalid-feedback" role="alert">{{ $message }}</p>
                        <p>I dati che hai inserito sono ancora in questo modulo. Confrontali con la polizza aggiornata; poi riapri il modulo e riporta le modifiche che vuoi mantenere.</p>
                        <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('clients.policies.show', [$client, $policy]) }}" target="_blank" rel="noopener">Apri polizza aggiornata in un’altra scheda</a><a class="btn btn-p" href="{{ route('clients.policies.edit', [$client, $policy]) }}">Riapri modulo aggiornato</a></div>
                    </x-form-section>
                @enderror
            @endif
            <p class="u-text-meta u-mb-md">I campi con * sono obbligatori.</p>
            @if(!$policy->exists)
            <x-form-section title="Origine della polizza">
                @if($selectedQuote)<input type="hidden" name="entry_mode" value="new"><input type="hidden" name="insurance_quote_id" value="{{ $selectedQuote->id }}"><p>Proposta {{ $selectedQuote->reference }} approvata da {{ $client->name }}. Premio: {{ \App\Support\Money::format($selectedQuote->premium_cents) }}.</p><a href="{{ route('quotes.show',$selectedQuote->insurance_quote_case_id) }}">Consulta preventivo e prova di approvazione</a>
                @else
                <input type="hidden" name="entry_mode" value="existing">
                <p class="u-mb-md">Stai registrando una polizza già stipulata. Per una nuova stipula, <a href="{{ route('policies.create', $client->exists ? ['client_id'=>$client->id] : []) }}">parti dal preventivo approvato</a>.</p>
                <x-form-group label="Motivo della registrazione senza preventivo" name="existing_reason" required><input id="field-existing-reason" class="form-in" name="existing_reason" minlength="5" maxlength="1000" value="{{ old('existing_reason') }}" placeholder="Indica perché stai registrando una polizza già stipulata" required></x-form-group>
                @endif
            </x-form-section>
            @endif
            @if($selectedQuote)
                <p>Contraente: {{ $client->name }}. Ramo, prodotto, compagnia, date, frazionamento e premio provengono dalla proposta approvata.</p>
                @if($globalCreate)<input type="hidden" name="client_id" value="{{ $client->id }}">@endif
                @php($quoteFixedValues = \Illuminate\Support\Arr::except(app(\App\Services\InsuranceQuoteWorkflow::class)->policyValues($selectedQuote), ['merit_class_cu','merit_class_internal']))
                @foreach($quoteFixedValues as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                <div x-init="$nextTick(() => { for (const name of @js(array_keys($quoteFixedValues))) { for (const el of $el.closest('form').querySelectorAll('[name=&quot;'+name+'&quot;]:not([type=hidden])')) el.disabled = true; } })"></div>
            @elseif($globalCreate) @include('policies.client-picker') @endif
            <x-form-section title="Polizza e intermediari">
            <div class="form-row">
                <x-form-group label="Ramo" name="category" required>
                    @if($usesCatalog)
                    <select id="field-category" name="category" class="form-sel" x-model="branch" @change="subbranch = ''" required><option value="">Seleziona il ramo</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(old('category', $policy->category)===$category)>{{ $category }}</option>@endforeach</select>
                    @can('create', \App\Models\SupplierCompany::class)<p class="u-text-meta"><a href="{{ route('insurance-catalog.index') }}">Gestisci rami e sottorami</a></p>@endcan
                    @else
                    <input id="field-category" name="category" class="form-in" list="insurance-categories" value="{{ old('category', $policy->category) }}" x-model="branch" maxlength="100" required>
                    <datalist id="insurance-categories">@foreach($categories as $category)<option value="{{ $category }}"></option>@endforeach</datalist>
                    <p class="u-text-meta">Seleziona un suggerimento oppure scrivi una nuova tipologia.</p>
                    @endif
                </x-form-group>
                <x-form-group label="Sottoramo" name="subcategory">
                    @if($usesCatalog)
                    <select id="field-subcategory" name="subcategory" class="form-sel" x-model="subbranch"><option value="">Non specificato</option><template x-for="item in (subbranches[branch] || [])" :key="item"><option :value="item" x-text="item" :selected="item === subbranch"></option></template></select>
                    <p class="u-text-meta">Sono disponibili soltanto i sottorami del ramo selezionato.</p>
                    @else
                    <input id="field-subcategory" name="subcategory" class="form-in" list="insurance-subcategories" value="{{ old('subcategory', $policy->subcategory) }}" maxlength="100">
                    <datalist id="insurance-subcategories"><template x-for="subbranch in (subbranches[branch] || [])" :key="subbranch"><option :value="subbranch"></option></template></datalist>
                    <p class="u-text-meta">Indica la suddivisione usata dall’agenzia per questo ramo. Il prodotto della compagnia ha un campo separato.</p>
                    @endif
                </x-form-group>
                <x-form-group label="Nome del prodotto" name="product"><input id="field-product" name="product" class="form-in" value="{{ old('product', $policy->product) }}" maxlength="255"><p class="u-text-meta">Nome commerciale indicato dalla compagnia. Lascialo vuoto se non è noto.</p></x-form-group>
                <x-form-group label="Compagnia" name="company" required><select id="field-company" name="company" class="form-sel" x-model="company" @change="agency = ''" required><option value="">Seleziona la compagnia</option>@foreach($companies as $companyOption)<option value="{{ $companyOption->name }}" @selected(old('company', $policy->company)===$companyOption->name)>{{ $companyOption->name }}</option>@endforeach @if($policy->company && !$companies->contains('name', $policy->company))<option value="{{ $policy->company }}">{{ $policy->company }}</option>@endif</select></x-form-group>
                <x-form-group label="Agenzia" name="supplier_agency_id"><select id="field-supplier-agency-id" name="supplier_agency_id" class="form-sel" x-model="agency"><option value="">Nessuna agenzia collegata</option><template x-for="item in (agencies[company.trim().toUpperCase().replace(/\s+/g, ' ')] || [])" :key="item.id"><option :value="String(item.id)" x-text="item.name" :selected="String(item.id) === agency"></option></template></select><p class="u-text-meta">Le agenzie disponibili sono quelle collegate alla compagnia indicata.</p></x-form-group>
                <x-form-group label="Numero polizza" name="number" required><input id="field-number" name="number" class="form-in" value="{{ old('number', $policy->number) }}" maxlength="100" required></x-form-group>
            </div></x-form-section>
            <x-form-section title="Veicolo, bene o soggetto assicurato"><div class="form-row">
                <x-form-group label="Veicolo / targa" name="vehicle_id">
                    <select id="field-vehicle-id" name="vehicle_id" class="form-sel"><option value="">Nessun veicolo collegato</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id', $policy->vehicle_id) === (string) $vehicle->id)>{{ $vehicle->plate }} / {{ trim($vehicle->make.' '.$vehicle->model) }}</option>@endforeach</select>
                    <p class="u-text-meta">Le targhe si registrano nella sezione Veicoli e targhe del cliente.</p>
                </x-form-group>
                <x-form-group label="Bene o soggetto assicurato" name="insured_subject"><input id="field-insured-subject" name="insured_subject" class="form-in" value="{{ old('insured_subject', $policy->insured_subject) }}" maxlength="255"></x-form-group>
                <x-form-group label="Immobile o altro bene collegato" name="client_asset_id"><select id="field-client-asset-id" name="client_asset_id" class="form-sel"><option value="">Nessun bene collegato</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected((string)old('client_asset_id', $policy->client_asset_id) === (string)$asset->id)>{{ $asset->name }} / {{ $asset->category }}</option>@endforeach</select><p class="u-text-meta">Scegli un bene oppure un veicolo. Registra i beni nella sezione Immobili e beni del cliente.</p></x-form-group>
            </div></x-form-section>
            <x-form-section title="Classi di merito RC Auto">@if($selectedQuote)<p class="u-text-meta u-mb-md">Controlla le classi riportate nel contratto. La classe interna è specifica della compagnia scelta.</p>@endif<div class="form-row">
                <x-form-group label="Classe universale CU" name="merit_class_cu"><select id="field-merit-class-cu" name="merit_class_cu" class="form-sel"><option value="">Non indicata / non applicabile</option>@foreach(range(1,18) as $class)<option value="{{ $class }}" @selected((string) old('merit_class_cu', $policy->merit_class_cu) === (string) $class)>{{ $class }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Classe interna della compagnia" name="merit_class_internal"><input id="field-merit-class-internal" name="merit_class_internal" class="form-in" value="{{ old('merit_class_internal', $policy->merit_class_internal) }}" maxlength="30"></x-form-group>
            </div></x-form-section>
            <x-form-section title="Date, premio e stato"><div class="form-row">
                <x-form-group label="Decorrenza" name="starts_on" required><input id="field-starts-on" type="date" name="starts_on" class="form-in" value="{{ old('starts_on', $policy->starts_on?->toDateString()) }}" required></x-form-group>
                <x-form-group label="Scadenza" name="expires_on" required><input id="field-expires-on" type="date" name="expires_on" class="form-in" value="{{ old('expires_on', $policy->expires_on?->toDateString()) }}" required></x-form-group>
                <x-form-group label="Premio di polizza (€)" name="premium"><input id="field-premium" type="number" name="premium" class="form-in" value="{{ old('premium', $policy->premium) }}" min="0" step="0.01" max="9999999999.99"></x-form-group>
                <x-form-group label="Stato polizza" name="status" required><select id="field-status" name="status" class="form-sel" required>@foreach($selectedQuote ? collect(\App\Models\InsurancePolicy::STATUSES)->only(['active','renewed']) : \App\Models\InsurancePolicy::STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('status', $policy->status) === $value)>{{ $label }}</option>@endforeach</select></x-form-group>
            </div>
            </x-form-section>
            <x-form-section title="Rinnovo e frazionamento">
            <div class="form-row">
                <x-form-group label="Tacito rinnovo" name="tacit_renewal"><select id="field-tacit-renewal" class="form-sel" name="tacit_renewal"><option value="">Non indicato</option><option value="1" @selected((string)old('tacit_renewal',$policy->tacit_renewal)==='1')>Sì</option><option value="0" @selected(old('tacit_renewal',$policy->tacit_renewal) !== null && !(bool)old('tacit_renewal',$policy->tacit_renewal))>No</option></select></x-form-group>
                <x-form-group label="Metodo di pagamento abituale" name="payment_method"><select id="field-payment-method" class="form-sel" name="payment_method"><option value="">Non indicato</option>@foreach(collect(\App\Models\InsurancePolicy::PAYMENT_METHODS)->merge([$policy->payment_method])->filter()->unique() as $method)<option value="{{ $method }}" @selected(old('payment_method',$policy->payment_method)===$method)>{{ $method }}</option>@endforeach</select><p class="u-text-meta">Preferenza per i titoli. Non registra un incasso.</p></x-form-group>
                <x-form-group label="Frazionamento" name="payment_frequency"><select id="field-payment-frequency" class="form-sel" name="payment_frequency"><option value="">Non indicato</option>@foreach(\App\Models\InsurancePolicy::PAYMENT_FREQUENCIES as $frequency)<option value="{{ $frequency }}" @selected(old('payment_frequency',$policy->payment_frequency)===$frequency)>{{ $frequency }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Premio rata (€)" name="installment_premium"><input id="field-installment-premium" class="form-in" type="number" name="installment_premium" value="{{ old('installment_premium',$policy->installment_premium) }}" min="0" step="0.01" max="9999999999.99">@if($selectedQuote && $policy->installment_premium === null)<p class="u-text-meta">Non specificato nella proposta. Dopo il salvataggio, controlla gli importi calcolati nell’anteprima delle rate.</p>@endif</x-form-group>
            </div>
            <input type="hidden" name="ad_horas" value="0"><label class="u-flex-center u-gap-sm u-mb-md"><input type="checkbox" name="ad_horas" value="1" @checked(old('ad_horas',$policy->ad_horas))> Ad horas: comunicazione urgente all’agenzia</label>
            </x-form-section>
            <x-form-section title="Note">
            <x-form-group label="Note sulla polizza" name="notes"><textarea id="field-notes" name="notes" class="form-ta" rows="4" maxlength="10000">{{ old('notes', $policy->notes) }}</textarea></x-form-group>
            </x-form-section>
            @if($policy->exists && $policy->work_closed_at && auth()->user()->canManageSystem())
                <x-form-section title="Correzione dopo la fine lavorazione">
                    <x-form-group label="Motivo della correzione" name="correction_reason" required><textarea id="field-correction-reason" name="correction_reason" class="form-ta" rows="3" minlength="10" maxlength="3000" required>{{ old('correction_reason') }}</textarea></x-form-group>
                    <p class="u-text-meta">Il motivo e le modifiche vengono registrati. La lavorazione rimane chiusa.</p>
                </x-form-section>
            @endif
            @unless($policy->exists)<x-form-section title="Documenti allegati">
                <x-form-group label="Allega documenti PDF" name="documents"><input id="field-documents" class="form-in" type="file" name="documents[]" accept="application/pdf,.pdf" multiple><p class="u-text-meta">Fino a 10 file, massimo 10 MB ciascuno. Saranno disponibili nella polizza e nella scheda cliente.</p></x-form-group>
                @foreach($errors->get('documents.*') as $messages)@foreach($messages as $message)<p class="invalid-feedback">{{ $message }}</p>@endforeach @endforeach
            </x-form-section>@endunless
            <div class="insurance-form-actions"><a href="{{ $policy->exists ? route('clients.policies.show', [$client, $policy]) : ($client->exists ? route('clients.show', $client) : route('policies.index')) }}" class="btn btn-g">Annulla</a><button type="submit" class="btn btn-p">Salva polizza</button></div>
        </form>
    </x-panel>
</x-app-layout>
