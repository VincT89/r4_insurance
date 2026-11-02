<form id="ledger-filters" method="GET" class="client-search-form" aria-label="Filtra contabilità"
    x-data="{branch:@js($filters['category']??''),subbranches:@js($branches->mapWithKeys(fn($b)=>[$b->name=>$b->subbranches->pluck('name')->all()])),from:@js($filters['from']??''),to:@js($filters['to']??''),month:@js($filters['month']??''),company:@js((string)($filters['company']??'')),agency:@js((string)($filters['agency']??'')),agencyCompanies:@js($agencyCompanies)}">
    @foreach(['client','policy'] as $key)@if($filters[$key]??null)<input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">@endif @endforeach
    <x-form-group label="Cliente o numero polizza" name="search"><input class="form-in" name="search" value="{{ $filters['search']??'' }}"></x-form-group>
    @foreach(['from'=>'Pagamento dal','to'=>'Al'] as $key=>$label)
        <x-form-group :label="$label" :name="$key"><input class="form-in" type="date" name="{{ $key }}" x-model="{{ $key }}" @change="month=''" value="{{ $filters[$key]??'' }}"></x-form-group>
    @endforeach
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
    <x-form-group label="Modalità di pagamento" name="payment_method">
        <select class="form-sel" name="payment_method"><option value="">Tutte</option>@foreach([...\App\Models\InsurancePolicy::PAYMENT_METHODS,...\App\Models\InsuranceCollection::REFUND_METHODS] as $method)<option @selected(($filters['payment_method']??'')===$method)>{{ $method }}</option>@endforeach</select>
    </x-form-group>
    <div class="search-form-actions"><button type="submit" class="btn btn-p">Applica filtri</button><a class="btn btn-g" href="{{ route('collections.index',$contextParameters) }}">Azzera filtri</a></div>
    <details class="search-more" @if(collect($filters)->only(['direction','state','category','subcategory','consultant','document_type','recipient','month'])->filter()->isNotEmpty()) open @endif>
        <summary class="btn btn-g">Altri filtri</summary>
        <div class="search-more-fields">
            <x-form-group label="Oppure scegli un mese" name="month"><input class="form-in" type="month" name="month" x-model="month" @change="from='';to=''" value="{{ $filters['month']??'' }}"></x-form-group>
            @foreach(['direction'=>['Movimento',['receipt'=>'Incassi','refund'=>'Rimborsi']], 'state'=>['Stato',['recorded'=>'Registrato','voided'=>'Stornato']], 'document_type'=>['Tipo documento',\App\Models\InsuranceCollection::DOCUMENT_TYPES], 'recipient'=>['Gestito da',\App\Models\InsuranceCollection::RECIPIENTS]] as $key=>[$label,$items])
                <x-form-group :label="$label" :name="$key"><select class="form-sel" name="{{ $key }}"><option value="">Tutti</option>@foreach($items as $value=>$text)<option value="{{ $value }}" @selected(($filters[$key]??'')===$value)>{{ $text }}</option>@endforeach</select></x-form-group>
            @endforeach
            <x-form-group label="Consulente" name="consultant"><select class="form-sel" name="consultant"><option value="">Tutti</option>@foreach($consultants as $user)<option value="{{ $user->id }}" @selected(($filters['consultant']??'')==$user->id)>{{ $user->name }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Ramo" name="category"><select name="category" class="form-sel" x-model="branch" @change="$refs.accountingSubbranch.value='' "><option value="">Tutti</option>@foreach($branches as $branch)<option>{{ $branch->name }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Sottoramo" name="subcategory"><select name="subcategory" class="form-sel" x-ref="accountingSubbranch"><option value="">Tutti</option><template x-for="sub in (subbranches[branch] || [])" :key="sub"><option :value="sub" x-text="sub" :selected="sub===@js($filters['subcategory']??'')"></option></template></select></x-form-group>
        </div>
        <div data-column-filters></div>
    </details>
    @php($periodFilters = \Illuminate\Support\Arr::except($filters,['month','from','to']))
    <div class="search-quick-filters" aria-label="Periodo dei pagamenti"><span>Periodo</span>
        @foreach([1=>'Oggi',10=>'Ultimi 10 giorni',15=>'Ultimi 15 giorni'] as $days=>$label)<a class="btn btn-g btn-sm" data-keep-table-filters href="{{ route('collections.index',$periodFilters+['from'=>today()->subDays($days-1)->toDateString(),'to'=>today()->toDateString()]) }}">{{ $label }}</a>@endforeach
        <a class="btn btn-g btn-sm" data-keep-table-filters href="{{ route('collections.index',$periodFilters+['month'=>'']) }}">Tutte le date</a>
    </div>
</form>
