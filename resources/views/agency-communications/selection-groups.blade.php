<div class="communication-selection-groups" data-selection-groups hidden>
    @foreach($reviewAgencies as $reviewAgency)
        <form method="POST" action="{{ route($reviewRoute,$reviewAgency) }}" data-review-agency="{{ $reviewAgency->id }}" hidden>
            @csrf
            @foreach($filters as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <div><strong>{{ $reviewAgency->name }}</strong><p class="form-help">{{ $reviewAgency->email ?: 'Email da completare prima dell’invio.' }}</p></div>
            <span data-agency-count></span><div data-selection-inputs></div>
            <button class="btn btn-p" type="submit" aria-label="Prepara selezione per {{ $reviewAgency->name }}" @disabled($reviewAgency->status!=='active')>Prepara selezione</button>
        </form>
    @endforeach
</div>
