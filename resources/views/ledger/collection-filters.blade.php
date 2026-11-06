@php
    $today = today();
    $day = $today->toDateString();
    $yesterday = $today->copy()->subDay()->toDateString();
    $ranges = [
        'today'=>[$day, $day], 'yesterday'=>[$yesterday, $yesterday],
        'last10'=>[$today->copy()->subDays(9)->toDateString(), $day],
        'last15'=>[$today->copy()->subDays(14)->toDateString(), $day], 'all'=>['', ''],
    ];
    $dates = [$filters['from'] ?? '', $filters['to'] ?? ''];
    $period = $dates === ['', ''] && filled($filters['month'] ?? null) ? 'month' : (array_search($dates, $ranges, true) ?: 'custom');
    $extraFilters = filled(request('_table_filters')) || collect($filters)->only(['payment_method','direction','state','category','subcategory','consultant','document_type','recipient'])->filter()->isNotEmpty();
@endphp
<form id="ledger-filters" method="GET" class="client-search-form collection-filters" aria-label="Filtra contabilità" data-compact-filter-results
    x-data="{branch:@js($filters['category']??''), subbranches:@js($branches->mapWithKeys(fn($b)=>[$b->name=>$b->subbranches->pluck('name')->all()])),
        from:@js($dates[0]), to:@js($dates[1]), month:@js($filters['month']??''), period:@js($period), ranges:@js($ranges),
        company:@js((string)($filters['company']??'')), agency:@js((string)($filters['agency']??'')), agencyCompanies:@js($agencyCompanies),
        choosePeriod(value) {
            this.period = value;
            if (value === 'month') {
                this.from = ''; this.to = ''; this.month ||= @js($today->format('Y-m')); return;
            }
            this.month = '';
            if (this.ranges[value]) [this.from, this.to] = this.ranges[value];
        }}">
    @foreach(['client','policy'] as $key)@if($filters[$key]??null)<input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">@endif @endforeach
    <x-form-group label="Periodo" name="collection_period">
        <select class="form-sel" id="field-collection-period" x-model="period" @change="choosePeriod($event.target.value)">
            @foreach(['today'=>'Oggi','yesterday'=>'Ieri','last10'=>'Ultimi 10 giorni','last15'=>'Ultimi 15 giorni','month'=>'Mese','custom'=>'Intervallo personalizzato','all'=>'Tutte le date'] as $value=>$label)
                <option value="{{ $value }}" @selected($period===$value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-form-group>
    <x-form-group label="Cliente o numero polizza" name="search"><input class="form-in" type="search" name="search" maxlength="255" value="{{ $filters['search']??'' }}"></x-form-group>
    <x-form-group label="Compagnia" name="company">
        <select class="form-sel" name="company" x-model="company" @change="if (company && agency !== 'direct' && !(agencyCompanies[agency] || []).includes(company)) agency=''">
            <option value="">Tutte</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(($filters['company']??'')==$company->id)>{{ $company->name }}</option>@endforeach
        </select>
    </x-form-group>
    <x-form-group label="Agenzia" name="agency">
        <select class="form-sel" name="agency" x-model="agency">
            <option value="">Tutte</option><option value="direct" @selected(($filters['agency']??'')==='direct')>Rapporto diretto con la compagnia</option>
            @foreach($agencies as $agency)<option value="{{ $agency->id }}" :hidden="Boolean(company) && !(agencyCompanies[{{ $agency->id }}] || []).includes(company)" :disabled="Boolean(company) && !(agencyCompanies[{{ $agency->id }}] || []).includes(company)" @selected(($filters['agency']??'')==$agency->id)>{{ $agency->name }}</option>@endforeach
        </select>
    </x-form-group>
    <div class="collection-period-fields" x-show="period==='custom'" x-cloak>
        @foreach(['from'=>'Pagamento dal','to'=>'Al'] as $key=>$label)
            <x-form-group :label="$label" :name="$key"><input class="form-in" type="date" name="{{ $key }}" x-model="{{ $key }}" value="{{ $filters[$key]??'' }}"></x-form-group>
        @endforeach
    </div>
    <div class="collection-period-fields" x-show="period==='month'" x-cloak>
        <x-form-group label="Mese del pagamento" name="month"><input class="form-in" type="month" name="month" x-model="month" :required="period==='month'" value="{{ $filters['month']??'' }}"></x-form-group>
    </div>
    <div class="collection-filter-controls">
    <details class="search-more">
        <summary class="btn btn-g">Altri filtri{{ $extraFilters ? ' (attivi)' : '' }}</summary>
        <div class="search-more-fields">
            <x-form-group label="Modalità di pagamento" name="payment_method">
                <select class="form-sel" name="payment_method"><option value="">Tutte</option>@foreach([...\App\Models\InsurancePolicy::PAYMENT_METHODS,...\App\Models\InsuranceCollection::REFUND_METHODS] as $method)<option @selected(($filters['payment_method']??'')===$method)>{{ $method }}</option>@endforeach</select>
            </x-form-group>
            @foreach(['direction'=>['Movimento',['receipt'=>'Incassi','refund'=>'Rimborsi']], 'state'=>['Stato',['recorded'=>'Registrato','voided'=>'Stornato']], 'document_type'=>['Tipo documento',\App\Models\InsuranceCollection::DOCUMENT_TYPES], 'recipient'=>['Gestito da',\App\Models\InsuranceCollection::RECIPIENTS]] as $key=>[$label,$items])
                <x-form-group :label="$label" :name="$key"><select class="form-sel" name="{{ $key }}"><option value="">Tutti</option>@foreach($items as $value=>$text)<option value="{{ $value }}" @selected(($filters[$key]??'')===$value)>{{ $text }}</option>@endforeach</select></x-form-group>
            @endforeach
            <x-form-group label="Consulente" name="consultant"><select class="form-sel" name="consultant"><option value="">Tutti</option>@foreach($consultants as $user)<option value="{{ $user->id }}" @selected(($filters['consultant']??'')==$user->id)>{{ $user->name }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Ramo" name="category"><select name="category" class="form-sel" x-model="branch" @change="$refs.accountingSubbranch.value='' "><option value="">Tutti</option>@foreach($branches as $branch)<option>{{ $branch->name }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Sottoramo" name="subcategory"><select name="subcategory" class="form-sel" x-ref="accountingSubbranch"><option value="">Tutti</option><template x-for="sub in (subbranches[branch] || [])" :key="sub"><option :value="sub" x-text="sub" :selected="sub===@js($filters['subcategory']??'')"></option></template></select></x-form-group>
        </div>
        <div data-column-filters></div>
    </details>
    <div class="search-form-actions">
        <button type="submit" class="btn btn-p">Applica filtri</button>
        @if(request()->except(['page','client','policy','_table_all']))
            <a class="btn btn-g" href="{{ route('collections.index',$contextParameters) }}">Azzera filtri</a>
        @endif
    </div>
    </div>
</form>
