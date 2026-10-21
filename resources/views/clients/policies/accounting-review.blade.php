@if($accountingReview)
    <x-panel title="Verifica contabile" padded>
        <p>La polizza è {{ mb_strtolower($policy->status_label) }}. Rate, incassi e provvigioni restano nello storico.</p>
        @if($accountingReview->open_titles > 0)
            <p class="u-mt-sm"><x-status-text :alert="true">{{ $accountingReview->open_titles }} {{ (int)$accountingReview->open_titles === 1 ? 'rata con residuo' : 'rate con residuo' }}: {{ \App\Support\Money::format((int)$accountingReview->balance_cents) }}.</x-status-text></p>
        @else
            <p class="u-mt-sm">Nessuna rata con residuo registrato.</p>
        @endif
        <p class="u-text-meta u-mt-sm">Verifica con la compagnia quali importi siano ancora dovuti e gli eventuali rimborsi. Il cambio di stato non annulla rate o incassi e non calcola rimborsi. Lo storno corregge una registrazione errata: non registra la restituzione di denaro al cliente.</p>
        <div class="insurance-form-actions">
            <a class="btn btn-g" href="{{ route('titles.index',['policy'=>$policy->id]) }}">Controlla rate e quietanze</a>
            <a class="btn btn-g" href="{{ route('collections.index',['policy'=>$policy->id,'month'=>'']) }}">Controlla incassi</a>
        </div>
    </x-panel>
@endif
