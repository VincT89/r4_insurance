<form method="POST" action="{{ route('quotes.comparison.update',[$offer,'correct']) }}" class="quote-correction-form">
    @csrf<input type="hidden" name="revision" value="{{ $offer->comparisonReview?->revision ?? 0 }}"><input type="hidden" name="field" value="{{ $cell['key'] }}"><input type="hidden" name="_comparison_quote_id" value="{{ $offer->id }}">@foreach($ids as $id)<input type="hidden" name="comparison_ids[]" value="{{ $id }}">@endforeach
    @php($currentValue=(int)old('_comparison_quote_id')===$offer->id && old('field')===$cell['key'] ? old('value') : $cell['value'])
    <label>{{ $cell['label'] }}
        @if(in_array($cell['type'],['coverage_status','frequency'],true))<select class="form-sel" name="value"><option value="">Non indicata</option>@foreach($cell['type']==='coverage_status' ? \App\Services\QuoteComparison::STATUSES : array_combine(\App\Models\InsurancePolicy::PAYMENT_FREQUENCIES,\App\Models\InsurancePolicy::PAYMENT_FREQUENCIES) as $value=>$label)<option value="{{ $value }}" @selected($currentValue===$value)>{{ $label }}</option>@endforeach</select>
        @elseif($cell['type']==='date')<input class="form-in" name="value" type="date" value="{{ $currentValue }}">
        @elseif($cell['type']==='decimal')<input class="form-in" name="value" type="number" min="0" max="9999999999.99" step="0.01" value="{{ $currentValue }}">
        @else<textarea class="form-ta" name="value" rows="2" maxlength="1000">{{ $currentValue }}</textarea>@endif
    </label><button class="btn btn-g btn-sm" type="submit">Conferma dato</button>
</form>
