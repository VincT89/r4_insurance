<?php

namespace App\Http\Controllers;

use App\Models\{InsuranceQuoteCase, InsuranceQuoteEmail, QuoteRecipientGroup};
use App\Services\{AgencyMailConfiguration, QuoteRecipients, QuoteRequestEmailWorkflow, QuoteRequestDocuments};
use Illuminate\Http\Request;

class QuoteRequestEmailController extends Controller
{
    public function create(Request $request, InsuranceQuoteCase $quoteCase, QuoteRequestEmailWorkflow $workflow, AgencyMailConfiguration $configuration)
    {
        $this->authorize('updateRegistry',$quoteCase->client);
        abort_unless($quoteCase->status === 'open',409);
        $quoteCase->load('documents');
        $request->validate(['draft'=>['nullable','uuid']]);
        $drafts = $request->filled('draft') ? $workflow->editableDraft($quoteCase, $request->user(), $request->input('draft')) : collect();
        $draft = $drafts->first();
        $draftValues = $draft ? ['subject'=>$draft->payload['subject'], 'body'=>$draft->payload['request_body'] ?? $draft->payload['body'], 'document_ids'=>$draft->documents->modelKeys()] : [];
        $documents = $quoteCase->documents->merge($draft?->documents ?? collect());
        $companies = app(QuoteRecipients::class)->catalog($quoteCase, activeOnly:true);
        $groups = $quoteCase->purpose === 'addition' ? collect() : QuoteRecipientGroup::forRequest($quoteCase->insurance_branch_id, $quoteCase->insurance_subbranch_id, $quoteCase->merit_class_cu)->with('companies')->orderBy('name')->get();
        return response()->view('quotes.email-compose',['case'=>$quoteCase,'companies'=>$companies,'unavailableKeys'=>$workflow->unavailableKeys($quoteCase), 'groups'=>$groups, 'selectedKeys'=>$draft ? $drafts->map(fn($message)=>QuoteRecipients::key($message->supplier_company_id,$message->supplier_agency_id))->all() : $quoteCase->suggestedRecipientKeys(), 'draft'=>$draft, 'draftValues'=>$draftValues, 'documents'=>$documents, 'sender'=>$configuration->sender(), 'mailReady'=>$configuration->ready()])->header('Cache-Control','no-store, private');
    }

    public function review(Request $request, InsuranceQuoteCase $quoteCase, QuoteRequestEmailWorkflow $workflow)
    {
        $this->authorize('updateRegistry',$quoteCase->client);
        $batch = $request->has('recipient_selection_present') || $request->has('recipient_keys');
        $recipients = $batch ? QuoteRecipients::rules() + ['recipient_group_choice'=>['nullable','integer','min:1']] : ['supplier_company_id'=>['required','integer'], 'supplier_agency_id'=>['nullable','integer']];
        $data = $request->validate($recipients + ['draft_id'=>['nullable','uuid',\Illuminate\Validation\Rule::prohibitedIf(!$batch)], 'subject'=>['required','string','max:180','not_regex:/[\r\n\x00]/'], 'body'=>['required','string','min:20','max:20000','not_regex:/\x00/'], 'document_ids'=>['nullable','array','list','max:10'],'document_ids.*'=>['integer','distinct']] + QuoteRequestDocuments::rules(), ['recipient_keys.required'=>'Seleziona almeno una compagnia o un’agenzia.']);
        if ($batch) {
            $messages = $workflow->prepareBatch($quoteCase,$request->user(),$data,QuoteRequestDocuments::uploads($request));
            return to_route('quote-email-batches.show',$messages->first()->review_batch_id);
        }
        return to_route('quote-emails.show',$workflow->prepare($quoteCase,$request->user(),$data,QuoteRequestDocuments::uploads($request)));
    }

    private function batchMessages(string $batch)
    {
        $messages = InsuranceQuoteEmail::with(['quoteCase.client','company','agency','user'])->where('review_batch_id',$batch)->orderBy('created_at')->orderBy('id')->get();
        abort_if($messages->isEmpty(),404);
        abort_unless($messages->count() <= QuoteRecipients::MAX_RECIPIENTS && $messages->pluck('insurance_quote_case_id')->unique()->count() === 1,409);
        $this->authorize('view',$messages->first()->quoteCase->client);
        return $messages;
    }

    public function batchShow(string $batch, AgencyMailConfiguration $configuration)
    {
        $messages = $this->batchMessages($batch)->sortBy(function (InsuranceQuoteEmail $message) {
            $target = $message->payload['target'];
            return [mb_strtolower($target['company_name']), mb_strtolower($target['agency_name'] ?? ''), $message->id];
        })->values();
        return response()->view('quotes.email-batch',['batch'=>$batch,'messages'=>$messages,'case'=>$messages->first()->quoteCase,'payload'=>$messages->first()->payload,'mailReady'=>$configuration->ready()])->header('Cache-Control','no-store, private');
    }

    public function batchSend(Request $request, string $batch, QuoteRequestEmailWorkflow $workflow)
    {
        $messages = $this->batchMessages($batch);
        $this->authorize('updateRegistry',$messages->first()->quoteCase->client);
        $request->validate(['reviewed'=>['accepted']]);
        $results = $workflow->sendBatch($messages,$request->user());
        $sent = $results->where('status','sent')->count();
        $pending = $results->count() - $sent;
        return to_route('quote-email-batches.show',$batch)->with($pending ? 'warning' : 'success', $sent.' richieste accettate dal server di posta.'.($pending ? ' '.$pending.' invii da verificare con l’admin: controlla gli esiti qui sotto.' : ' Tutti gli invii sono registrati nella pratica.'));
    }

    public function show(InsuranceQuoteEmail $message, AgencyMailConfiguration $configuration)
    {
        $this->authorize('view',$message->quoteCase->client);
        return response()->view('quotes.email-show',['message'=>$message,'payload'=>$message->payload,'mailReady'=>$configuration->ready()])->header('Cache-Control','no-store, private');
    }

    public function send(Request $request, InsuranceQuoteEmail $message, QuoteRequestEmailWorkflow $workflow)
    {
        $this->authorize('updateRegistry',$message->quoteCase->client);
        $request->validate(['reviewed'=>['accepted']]);
        $sent = $workflow->send($message,$request->user());
        return to_route('quote-emails.show',$sent)->with($sent->status === 'sent' ? 'success' : 'warning', $sent->status === 'sent' ? 'Richiesta accettata dal server di posta e registrata nella pratica.' : 'Esito incerto: chiedi all’admin di verificarlo prima di ripetere l’invio.');
    }

    public function resolve(Request $request, InsuranceQuoteEmail $message, QuoteRequestEmailWorkflow $workflow)
    {
        $this->authorize('system.admin');
        $data = $request->validate(['checked'=>['accepted'],'outcome'=>['required','in:sent,not_sent'],'note'=>['required','string','min:10','max:1000']]);
        $workflow->resolve($message,$request->user(),$data['outcome']==='sent',$data['note']);
        return to_route('quote-emails.show',$message)->with('success','Esito verificato e registrato nello storico.');
    }
}
