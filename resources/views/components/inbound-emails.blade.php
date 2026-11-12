@props(['client', 'quoteCase'=>null, 'policy'=>null])
@php
    $receivedEmails = collect();
    if (auth()->user()?->can('communications.manage') && auth()->user()->can('view',$client) && \Illuminate\Support\Facades\Schema::hasTable('inbound_emails')) {
        $receivedEmails = \App\Models\InboundEmail::where('client_id',$client->id)->where('status','assigned')
            ->when($quoteCase, fn($q)=>$q->where('insurance_quote_case_id',$quoteCase->id))
            ->when($policy, fn($q)=>$q->where('insurance_policy_id',$policy->id))
            ->withCount('files')->orderByDesc('received_at')->orderByDesc('id')->limit(5)->get();
    }
@endphp
@if($receivedEmails->isNotEmpty())
    <section class="inbound-email-section" aria-label="Email e allegati ricevuti">
        <h2>Email e allegati ricevuti</h2>
        <ul>@foreach($receivedEmails as $receivedEmail)<li><a href="{{ route('inbound-emails.show',$receivedEmail) }}">{{ $receivedEmail->payload['subject'] ?: 'Senza oggetto' }}</a> · {{ $receivedEmail->received_at->format('d/m/Y') }} · {{ $receivedEmail->files_count }} {{ $receivedEmail->files_count===1 ? 'allegato' : 'allegati' }}</li>@endforeach</ul>
        <a href="{{ route('inbound-emails.index',array_filter(['status'=>'all','client'=>$client->id,'case'=>$quoteCase?->id,'policy'=>$policy?->id])) }}">Tutte le email ricevute</a>
    </section>
@endif
