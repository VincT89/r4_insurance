<?php

namespace App\Http\Controllers;

use App\Models\{Client, InboundEmail};
use App\Services\InboundEmailAssociation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Schema, Storage};
use Illuminate\Validation\Rule;

class InboundEmailController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('communications.manage');
        $ready = Schema::hasTable('inbound_emails') && Schema::hasTable('inbound_email_files');
        if (!$ready) return view('inbound-emails.index', ['ready'=>false]);
        $filters = $request->validate(['status'=>['nullable','in:all,unassigned,assigned'], 'sender'=>['nullable','email:rfc','max:254'],
            'client'=>['nullable','integer'], 'case'=>['nullable','integer'], 'policy'=>['nullable','integer'],
            'from'=>['nullable','date_format:Y-m-d'], 'to'=>array_merge(['nullable','date_format:Y-m-d'], $request->filled('from') ? ['after_or_equal:from'] : [])]);
        $filters['status'] ??= $request->hasAny(['client','case','policy']) ? 'all' : 'unassigned';
        $query = InboundEmail::with(['client','quoteCase','policy'])->withCount('files');
        if ($filters['status'] !== 'all') $query->where('status', $filters['status']);
        if (filled($filters['sender'] ?? null)) $query->where('sender_hash', hash('sha256', mb_strtolower($filters['sender'])));
        foreach (['client'=>'client_id', 'case'=>'insurance_quote_case_id', 'policy'=>'insurance_policy_id'] as $input=>$column) {
            if (filled($filters[$input] ?? null)) $query->where($column, $filters[$input]);
        }
        if (filled($filters['from'] ?? null)) $query->where('received_at', '>=', $filters['from'].' 00:00:00');
        if (filled($filters['to'] ?? null)) $query->where('received_at', '<', \Carbon\Carbon::parse($filters['to'])->addDay()->startOfDay());
        $emails = $query->orderByDesc('received_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $connected = (bool) config('inbound-email.enabled') && strlen((string) config('inbound-email.token_sha256')) === 64;
        return view('inbound-emails.index', compact('ready','filters','emails','connected'));
    }

    private function email(string $publicId): InboundEmail
    {
        abort_unless(Schema::hasTable('inbound_emails') && Schema::hasTable('inbound_email_files'), 503, 'Ricezione email non ancora attivata nell’archivio.');
        return InboundEmail::where('public_id',$publicId)->firstOrFail();
    }

    public function show(Request $request, string $inboundEmail)
    {
        $this->authorize('communications.manage');
        $filters = $request->validate(['client'=>['nullable','integer','min:1'], 'client_search'=>['nullable','string','max:150']]);
        $email = $this->email($inboundEmail)->load(['files','client','quoteCase','policy','operator']);
        $oldClient = old('_inbound_email') === $email->public_id ? old('client_id') : null;
        $clientId = $filters['client'] ?? $oldClient ?? $email->client_id;
        $selectedClient = $clientId ? Client::current()->visibleTo($request->user())->findOrFail($clientId) : null;
        $clients = collect();
        if (filled($filters['client_search'] ?? null)) {
            $term = '%'.$filters['client_search'].'%';
            $clients = Client::current()->visibleTo($request->user())->where(fn ($q) => $q->where('name','like',$term)->orWhere('email','like',$term)->orWhere('tax_code','like',$term))
                ->orderBy('name')->limit(20)->get(['id','name','email']);
        }
        $cases = $selectedClient?->quoteCases()->orderByDesc('id')->get(['id','subject']) ?? collect();
        $policies = $selectedClient?->policies()->orderByDesc('id')->get(['id','number']) ?? collect();
        $history = $email->auditLogs()->with('user')->orderByDesc('id')->limit(20)->get();
        return view('inbound-emails.show', compact('email','selectedClient','clients','cases','policies','filters','history'));
    }

    public function associate(Request $request, string $inboundEmail, InboundEmailAssociation $association)
    {
        $this->authorize('communications.manage');
        $email = $this->email($inboundEmail);
        $values = $request->validate(['target'=>['required','in:unassigned,client,quote,policy'], 'version'=>['required','integer','min:0'],
            'client_id'=>['required_unless:target,unassigned','nullable','integer','min:1'],
            'insurance_quote_case_id'=>['required_if:target,quote','nullable','integer','min:1'],
            'insurance_policy_id'=>['required_if:target,policy','nullable','integer','min:1'],
            'document_kind'=>['required', Rule::in(array_keys(InboundEmail::KINDS))], 'association_note'=>['nullable','string','max:1000'], 'confirmed'=>['accepted']]);
        $association->save($email, $request->user(), $values);
        return redirect()->route('inbound-emails.show', $email)->with('success','Associazione salvata. Email e allegati originali conservati.');
    }

    public function download(string $inboundEmail, int $file)
    {
        $this->authorize('communications.manage');
        $document = $this->email($inboundEmail)->files()->findOrFail($file);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        /** Anche HTML e nomi provenienti dalla posta non possono diventare contenuto attivo nel browser. */
        return Storage::disk('local')->download($document->path, $document->original_name, ['Content-Type'=>'application/octet-stream', 'X-Content-Type-Options'=>'nosniff', 'Cache-Control'=>'private, no-store']);
    }
}
