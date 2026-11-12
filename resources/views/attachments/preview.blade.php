<x-app-layout :title="$attachment->original_name">
    <x-page-header>
        <x-slot:title>{{ $attachment->original_name }}</x-slot:title>
        <x-slot:actions>
            @if(in_array($attachment->document_category, ['quote','quote_approval'], true) && $attachment->insuranceQuote && $attachment->attachable_type === (new \App\Models\Client)->getMorphClass() && (int)$attachment->attachable_id === (int)$attachment->insuranceQuote->quoteCase->client_id)
                <a class="btn btn-g" href="{{ route('quotes.show',$attachment->insuranceQuote->insurance_quote_case_id) }}#confronto-proposte">Torna al confronto</a>
            @endif
            @if($formPackage = $attachment->formDocument?->package)
                <a class="btn btn-g" href="{{ route('clients.forms.show', [$formPackage->client_id, $formPackage]) }}">Torna al fascicolo moduli</a>
            @endif
            @if($attachment->insurancePolicy)
                @if($attachment->formDocument?->signature_required)
                    <a class="btn btn-g" href="{{ route('clients.policies.show', [$attachment->insurancePolicy->client_id, $attachment->insurancePolicy, 'tab'=>'forms']).'#policy-signatures' }}">Torna alle firme della polizza</a>
                @else
                    <a class="btn btn-g" href="{{ route('clients.policies.show', [$attachment->insurancePolicy->client_id, $attachment->insurancePolicy, 'tab'=>'documents']).'#policy-documents' }}">Torna alla polizza</a>
                @endif
            @elseif($attachment->attachable_type === (new \App\Models\Client)->getMorphClass())
                <a class="btn btn-g" href="{{ route('clients.show', [$attachment->attachable_id, 'tab'=>'documents']) }}">Documenti del cliente</a>
            @endif
            <a class="btn btn-p" href="{{ $attachment->downloadUrl() }}">Scarica PDF</a>
        </x-slot:actions>
    </x-page-header>
    <x-pdf-reader :source="$attachment->downloadUrl()" :page="max(1,min(1000,(int)request('page',1)))" />
</x-app-layout>
