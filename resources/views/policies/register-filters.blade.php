@php
    $advancedFilters = collect($filters)->except(['search'])->filter(fn ($value,$key) => filled($value) && !($key==='period' && $value==='all'));
    $quickBase = request()->except(['page','status','period','from','to','_table_all','_table_filters','_table_mode']);
    $quickViews = [
        ['label'=>'Tutte','values'=>[],'active'=>!request('status') && (!request('period') || request('period')==='all') && !request('from') && !request('to')],
        ['label'=>'In vigore','values'=>['status'=>'active'],'active'=>request('status')==='active' && (!request('period') || request('period')==='all')],
        ['label'=>'In scadenza','values'=>['status'=>'active','period'=>'15'],'active'=>request('status')==='active' && request('period')==='15'],
        ['label'=>'Da verificare','values'=>['status'=>'review'],'active'=>request('status')==='review'],
    ];
@endphp
<nav class="policy-register-views" aria-label="Viste delle polizze">
    @foreach($quickViews as $quickView)<a href="{{ route('policies.index',array_merge($quickBase,$quickView['values'])) }}" @if($quickView['active']) aria-current="page" @endif>{{ $quickView['label'] }}</a>@endforeach
</nav>
<form method="GET" action="{{ route('policies.index') }}" id="policies-filters" class="policy-register-search" aria-label="Filtra polizze">
    <div class="policy-search-line">
        <div class="policy-search-field"><label for="field-search">Cerca polizza</label><input id="field-search" class="form-in" type="search" name="search" value="{{ request('search') }}" maxlength="255" placeholder="Cliente, numero polizza, targa o prodotto"></div>
        <button type="submit" class="btn btn-p">Cerca</button>
        @if(request()->except(['page','_table_all']))<a href="{{ route('policies.index') }}" class="btn btn-g">Azzera filtri</a>@endif
    </div>
    <details class="search-more policy-register-filter-options">
        <summary>Filtri{{ $advancedFilters->isNotEmpty() ? ' attivi' : '' }}</summary>
        <div class="search-more-fields">
            <x-form-group label="Stato polizza" name="status"><select id="field-status" class="form-sel" name="status"><option value="">Tutti</option>@foreach(\App\Domain\Core\Queries\PolicyPortfolioQuery::STATES as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Pagamento" name="payment_status"><select id="field-payment-status" class="form-sel" name="payment_status"><option value="">Tutti</option>@foreach(['unpaid'=>'Da saldare','paid'=>'Saldata','unspecified'=>'Non indicato'] as $value=>$label)<option value="{{ $value }}" @selected(request('payment_status')===$value)>{{ $label }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Compagnia" name="company"><select id="field-company" class="form-sel" name="company"><option value="">Tutte</option>@foreach($companies as $company)<option value="{{ $company }}" @selected(request('company')===$company)>{{ $company }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Agenzia" name="agency"><select id="field-agency" class="form-sel" name="agency"><option value="">Tutte</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((string)request('agency')===(string)$agency->id)>{{ $agency->name }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Ramo" name="category"><select id="field-category" class="form-sel" name="category"><option value="">Tutti</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category')===$category)>{{ $category }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Sottoramo" name="subcategory"><select id="field-subcategory" class="form-sel" name="subcategory"><option value="">Tutti</option>@foreach($subcategories as $subcategory)<option value="{{ $subcategory }}" @selected(request('subcategory')===$subcategory)>{{ $subcategory }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Responsabile cliente" name="responsible"><select id="field-responsible" class="form-sel" name="responsible"><option value="">Tutti</option>@foreach($responsibles as $person)<option value="{{ $person->id }}" @selected((string)request('responsible')===(string)$person->id)>{{ $person->name }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Frazionamento" name="payment_frequency"><select id="field-payment-frequency" class="form-sel" name="payment_frequency"><option value="">Tutti</option>@foreach(\App\Models\InsurancePolicy::PAYMENT_FREQUENCIES as $frequency)<option value="{{ $frequency }}" @selected(request('payment_frequency')===$frequency)>{{ $frequency }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Metodo di pagamento" name="payment_method"><select id="field-payment-method" class="form-sel" name="payment_method"><option value="">Tutti</option>@foreach($paymentMethods as $method)<option value="{{ $method }}" @selected(request('payment_method')===$method)>{{ $method }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Scadenza" name="period"><select id="field-period" class="form-sel" name="period">@foreach(['all'=>'Qualsiasi data','overdue'=>'Data già trascorsa','7'=>'Entro 7 giorni','15'=>'Entro 15 giorni','30'=>'Entro 30 giorni','45'=>'Entro 45 giorni','60'=>'Entro 60 giorni','custom'=>'Intervallo personalizzato'] as $value=>$label)<option value="{{ $value }}" @selected((string)request('period','all')===(string)$value)>{{ $label }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Scadenza dal" name="from"><input id="field-from" class="form-in" type="date" name="from" value="{{ request('from') }}"></x-form-group>
            <x-form-group label="Scadenza al" name="to"><input id="field-to" class="form-in" type="date" name="to" value="{{ request('to') }}"></x-form-group>
        </div>
        <div data-column-filters></div>
        <button type="submit" class="btn btn-p">Applica filtri</button>
    </details>
</form>
