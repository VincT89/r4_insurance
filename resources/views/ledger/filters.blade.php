<form id="ledger-filters" method="GET" class="client-search-form" data-list-filters aria-label="Filtra {{ $isCollections ? 'incassi' : 'titoli' }}" x-data="{branch:@js($filters['category']??''),subbranches:@js($branches->mapWithKeys(fn($b)=>[$b->name=>$b->subbranches->pluck('name')->all()])),from:@js($filters['from']??''),to:@js($filters['to']??''),month:@js($filters['month']??'')}">
@foreach(['client','policy'] as $key)@if($filters[$key]??null)<input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">@endif @endforeach
<x-form-group label="Cliente o numero polizza" name="search"><input class="form-in" name="search" value="{{ $filters['search']??'' }}"></x-form-group>
@if($isCollections)
@foreach(['from'=>'Pagamento dal','to'=>'Al'] as $key=>$label)<x-form-group :label="$label" :name="$key"><input class="form-in" type="date" name="{{ $key }}" x-model="{{ $key }}" @change="month=''" value="{{ $filters[$key]??'' }}"></x-form-group>@endforeach
@else
<x-form-group label="Mese scadenza" name="month"><input class="form-in" type="month" name="month" x-model="month" @change="from='';to=''" value="{{ $filters['month']??'' }}"></x-form-group>
@endif
<x-form-group label="Compagnia" name="company"><select class="form-sel" name="company"><option value="">Tutte</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(($filters['company']??'')==$company->id)>{{ $company->name }}</option>@endforeach</select></x-form-group>
<x-form-group label="Stato" name="state"><select class="form-sel" name="state"><option value="">Tutti</option>@foreach($isCollections ? ['recorded'=>'Registrato','voided'=>'Stornato'] : \Illuminate\Support\Arr::except(\App\Models\InsuranceTitle::STATES, ['partial']) as $key=>$label)<option value="{{ $key }}" @selected(($filters['state']??'')===$key)>{{ ($filters['direction']??'') === 'refund' ? ($key === 'paid' ? 'Rimborsati' : ($key === 'open' ? 'Da rimborsare' : $label)) : $label }}</option>@endforeach</select></x-form-group>
<div class="search-form-actions"><button type="submit" class="btn btn-p">Applica filtri</button><a class="btn btn-g" href="{{ route($isCollections ? 'collections.index' : 'titles.index', $contextParameters) }}">Azzera filtri</a></div>
<details class="search-more" @if(collect($filters)->only(['agency','direction','payment_method','category','subcategory','consultant','document_type','recipient','kind','payment_frequency',...($isCollections ? ['month'] : ['from','to'])])->filter()->isNotEmpty()) open @endif>
<summary class="btn btn-g">Altri filtri</summary><div class="search-more-fields">
<x-form-group label="Agenzia" name="agency"><select class="form-sel" name="agency"><option value="">Tutte</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected(($filters['agency']??'')==$agency->id)>{{ $agency->name }}</option>@endforeach</select></x-form-group>
<x-form-group label="Movimento" name="direction"><select class="form-sel" name="direction"><option value="">Incassi e rimborsi</option><option value="receipt" @selected(($filters['direction']??'')==='receipt')>Incassi</option><option value="refund" @selected(($filters['direction']??'')==='refund')>Rimborsi</option></select></x-form-group>
@if($isCollections)
<x-form-group label="Oppure scegli un mese" name="month"><input class="form-in" type="month" name="month" x-model="month" @change="from='';to=''" value="{{ $filters['month']??'' }}"></x-form-group>
@else
@foreach(['from'=>'Dal','to'=>'Al'] as $key=>$label)<x-form-group :label="$label" :name="$key"><input class="form-in" type="date" name="{{ $key }}" x-model="{{ $key }}" @change="month=''" value="{{ $filters[$key]??'' }}"></x-form-group>@endforeach
@endif
<x-form-group label="Metodo di pagamento" name="payment_method"><select class="form-sel" name="payment_method"><option value="">Tutti</option>@foreach([...\App\Models\InsurancePolicy::PAYMENT_METHODS,...\App\Models\InsuranceCollection::REFUND_METHODS] as $method)<option @selected(($filters['payment_method']??'')===$method)>{{ $method }}</option>@endforeach</select></x-form-group>
<x-form-group label="Ramo" name="category"><select name="category" class="form-sel" x-model="branch"><option value="">Tutti</option>@foreach($branches as $branch)<option>{{ $branch->name }}</option>@endforeach</select></x-form-group>
<x-form-group label="Sottoramo" name="subcategory"><select name="subcategory" class="form-sel"><option value="">Tutti</option><template x-for="sub in (subbranches[branch] || [])" :key="sub"><option :value="sub" x-text="sub" :selected="sub===@js($filters['subcategory']??'')"></option></template></select></x-form-group>
@if($isCollections)
<x-form-group label="Consulente" name="consultant"><select class="form-sel" name="consultant"><option value="">Tutti</option>@foreach($consultants as $user)<option value="{{ $user->id }}" @selected(($filters['consultant']??'')==$user->id)>{{ $user->name }}</option>@endforeach</select></x-form-group>
@foreach(['document_type'=>['Tipo documento',\App\Models\InsuranceCollection::DOCUMENT_TYPES],'recipient'=>['Destinatario pagamento',\App\Models\InsuranceCollection::RECIPIENTS]] as $key=>[$label,$items])<x-form-group :label="$label" :name="$key"><select class="form-sel" name="{{ $key }}"><option value="">Tutti</option>@foreach($items as $value=>$text)<option value="{{ $value }}" @selected(($filters[$key]??'')===$value)>{{ $text }}</option>@endforeach</select></x-form-group>@endforeach
@else
<x-form-group label="Tipo titolo" name="kind"><select class="form-sel" name="kind"><option value="">Tutti</option>@foreach(\App\Models\InsuranceTitle::KINDS as $key=>$label)<option value="{{ $key }}" @selected(($filters['kind']??'')===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
<x-form-group label="Frazionamento" name="payment_frequency"><select class="form-sel" name="payment_frequency"><option value="">Tutti</option>@foreach(\App\Models\InsurancePolicy::PAYMENT_FREQUENCIES as $frequency)<option @selected(($filters['payment_frequency']??'')===$frequency)>{{ $frequency }}</option>@endforeach</select></x-form-group>
@endif</div><div data-column-filters></div></details>
@if($isCollections)
@php $periodFilters = \Illuminate\Support\Arr::except($filters,['month','from','to']); @endphp
<div class="search-quick-filters" aria-label="Periodo dei pagamenti"><span>Periodo</span>
@foreach([1=>'Oggi',10=>'Ultimi 10 giorni',15=>'Ultimi 15 giorni'] as $days=>$label)<a class="btn btn-g btn-sm" data-keep-table-filters href="{{ route('collections.index',$periodFilters+['from'=>today()->subDays($days-1)->toDateString(),'to'=>today()->toDateString()]) }}">{{ $label }}</a>@endforeach
<a class="btn btn-g btn-sm" data-keep-table-filters href="{{ route('collections.index',$periodFilters+['month'=>'']) }}">Tutte le date</a>
</div>
@else
@php($quickTitleFilters = \Illuminate\Support\Arr::except($filters,['state','month','from','to']))
<nav class="search-quick-filters" aria-label="Viste rapide dei titoli"><a class="btn btn-g btn-sm" data-keep-table-filters href="{{ route('titles.index',$quickTitleFilters+['state'=>'overdue']) }}">Sospese di cassa</a><a class="btn btn-g btn-sm" data-keep-table-filters href="{{ route('titles.index',$quickTitleFilters+['state'=>'due_soon']) }}">In giacenza entro 15 giorni</a></nav>
@endif
</form>
