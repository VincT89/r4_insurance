@php
    $hasSignatures = collect($signatures)->contains(fn ($group) => $group->isNotEmpty());
    $highlight = (int)(old('signature_document') ?: session('signature_document'));
    $toSignLabel = $signatures['toSign']->every(fn ($doc) => !$doc->latestSubmission) ? 'Da firmare'
        : ($signatures['toSign']->every(fn ($doc) => $doc->latestSubmission) ? 'Da correggere' : 'Da firmare o correggere');
    $signatureSummary = [];
    foreach (['toSign'=>mb_strtolower($toSignLabel),'toReview'=>'da controllare','previous'=>'dei periodi precedenti da completare'] as $group=>$label) {
        $count = $signatures[$group]->count();
        if ($count) $signatureSummary[] = $count.' '.($count === 1 ? 'modulo' : 'moduli').' '.$label;
    }
    $nextSignature = $signatures['toReview']->first() ?? $signatures['toSign']->first() ?? $signatures['previous']->first();
@endphp
<section id="policy-signatures" class="policy-signatures" data-policy-signatures data-has-documents="{{ (int)$hasSignatures }}"
         aria-label="Firme dei moduli" @if($policyTab !== 'forms' && ($policyTab !== 'overview' || !$hasSignatures)) hidden @endif>
    <div class="policy-forms-reminder signature-overview" data-signature-overview @if($policyTab !== 'overview') hidden @endif>
        <span><strong>{{ $signatureSummary ? 'Moduli da completare' : 'Moduli e firme' }}</strong><small>{{ $signatureSummary ? implode(' · ',$signatureSummary) : 'Le firme del periodo corrente sono state controllate.' }}</small></span>
        <div class="signature-overview-actions">
            @can('updateRegistry',$client)
                @if($nextSignature && $signatures['toReview']->isEmpty() && $signatures['toSign']->count() === 1 && $signatures['toSign']->contains('id',$nextSignature->id) && $nextSignature->generated)
                    <a class="btn btn-p" href="{{ route('station-signing.create',$nextSignature->generated) }}">Firma modulo</a>
                @endif
            @endcan
            <a class="btn btn-g" data-policy-section-link href="{{ route('clients.policies.show',[$client,$policy,'tab'=>'forms']).'#policy-signatures' }}">Apri moduli e firme</a>
        </div>
    </div>
    <div data-signature-tasks @if($policyTab !== 'forms') hidden @endif>
    <x-panel title="Firme dei moduli" padded>
        @foreach(['toSign'=>$toSignLabel,'toReview'=>'Copie da controllare'] as $group=>$label)
            @if($signatures[$group]->isNotEmpty())
                <div class="signature-group"><h3>{{ $label }}</h3>
                    @foreach($signatures[$group]->take(3) as $document) @include('insurance-forms.policy-signature-row',['previous'=>false]) @endforeach
                    @if($signatures[$group]->count()>3)
                        <details class="signature-group-more" @if($signatures[$group]->slice(3)->contains('id',$highlight)) open @endif><summary>{{ $signatures[$group]->count() === 4 ? 'Mostra un altro modulo' : 'Mostra altri '.($signatures[$group]->count()-3).' moduli' }}</summary>
                            @foreach($signatures[$group]->slice(3) as $document) @include('insurance-forms.policy-signature-row',['previous'=>false]) @endforeach
                        </details>
                    @endif
                </div>
            @endif
        @endforeach
        @if($signatures['toSign']->isEmpty() && $signatures['toReview']->isEmpty())<p class="signature-empty">{{ $signatures['completed']->isEmpty() ? 'Nessun modulo da firmare nel periodo corrente.' : 'Tutte le firme del periodo corrente sono state controllate.' }}</p>@endif
        @foreach(['completed'=>'Firme controllate','previous'=>'Firme da completare dei periodi precedenti'] as $group=>$label)
            @if($signatures[$group]->isNotEmpty())
                <details class="signature-group-more" @if($signatures[$group]->contains('id',$highlight)) open @endif><summary>{{ $label }} ({{ $signatures[$group]->count() }})</summary>
                    @foreach($signatures[$group] as $document) @include('insurance-forms.policy-signature-row',['previous'=>$group==='previous']) @endforeach
                </details>
            @endif
        @endforeach
    </x-panel>
    </div>
</section>
