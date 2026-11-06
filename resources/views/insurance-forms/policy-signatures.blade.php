@php
    $hasSignatures = collect($signatures)->contains(fn ($group) => $group->isNotEmpty());
    $highlight = (int)(old('signature_document') ?: session('signature_document'));
    $toSignLabel = $signatures['toSign']->every(fn ($doc) => !$doc->latestSubmission) ? 'Da firmare'
        : ($signatures['toSign']->every(fn ($doc) => $doc->latestSubmission) ? 'Da correggere' : 'Da firmare o correggere');
@endphp
<section id="policy-signatures" class="policy-signatures" data-policy-signatures data-has-documents="{{ (int)$hasSignatures }}"
         aria-label="Firme dei moduli" @if($policyTab !== 'forms' && ($policyTab !== 'overview' || !$hasSignatures)) hidden @endif>
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
</section>
