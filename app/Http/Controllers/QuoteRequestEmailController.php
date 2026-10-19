<?php

namespace App\Http\Controllers;

use App\Models\{InsuranceQuoteCase, InsuranceQuoteEmail, SupplierCompany};
use App\Services\{AgencyMailConfiguration, QuoteRequestEmailWorkflow, QuoteRequestDocuments};
use Illuminate\Http\Request;

class QuoteRequestEmailController extends Controller
{
    public function create(InsuranceQuoteCase $quoteCase, QuoteRequestEmailWorkflow $workflow, AgencyMailConfiguration $configuration)
    {
        $this->authorize('updateRegistry',$quoteCase->client);
        abort_unless($quoteCase->status === 'open',409);
        $quoteCase->load('documents');
        $companies = SupplierCompany::where('status','active')->orderByDesc('quote_priority')->orderBy('name')->get();
        $claimed = $quoteCase->emails()->whereNotNull('delivery_key')->pluck('supplier_company_id');
        $answered = $quoteCase->quotes()->where('status','!=','requested')->pluck('supplier_company_id');
        return response()->view('quotes.email-compose',['case'=>$quoteCase,'companies'=>$companies,'unavailable'=>$claimed->merge($answered), 'remaining'=>$workflow->remainingPriorities($quoteCase), 'sender'=>$configuration->sender(), 'mailReady'=>$configuration->ready()])->header('Cache-Control','no-store, private');
    }

    public function review(Request $request, InsuranceQuoteCase $quoteCase, QuoteRequestEmailWorkflow $workflow)
    {
        $this->authorize('updateRegistry',$quoteCase->client);
        $data = $request->validate(['supplier_company_id'=>['required','integer'], 'subject'=>['required','string','max:180','not_regex:/[\r\n\x00]/'], 'body'=>['required','string','min:20','max:20000','not_regex:/\x00/'], 'document_ids'=>['nullable','array','list','max:10'],'document_ids.*'=>['integer','distinct']] + QuoteRequestDocuments::rules());
        return to_route('quote-emails.show',$workflow->prepare($quoteCase,$request->user(),$data,QuoteRequestDocuments::uploads($request)));
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
