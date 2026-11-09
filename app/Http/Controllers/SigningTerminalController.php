<?php

namespace App\Http\Controllers;

use App\Models\{StationSigningItem, StationSigningRequest};
use App\Services\{DocumentSigning, DocumentSigningInput, SigningStations};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SigningTerminalController extends Controller
{
    public function index(Request $request, SigningStations $stations)
    {
        $station = $stations->identify($request);
        return response()->view('signing-stations.terminal',compact('station'))->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
    }

    public function pair(Request $request, SigningStations $stations)
    {
        [, $credential] = $stations->startPairing($request);
        $response = to_route('signing-terminal.index');
        if ($credential) $response->withCookie(cookie(SigningStations::COOKIE,$credential,config('document_signing.station_credential_days')*1440,'/',null,$request->isSecure(),true,false,'strict'));
        return $response;
    }

    public function current(Request $request, SigningStations $stations)
    {
        $station = $stations->identify($request);
        $job = $station?->usable() ? $stations->current($station) : null;
        $state = $station?->usable() ? 'ready' : ($station?->status === 'pending' && $station->pairing_expires_at->isFuture() ? 'pairing' : 'unpaired');
        return response()->json(['state'=>$state,'request_id'=>$job?->id,'url'=>$job ? route('signing-terminal.show',$job) : null])->header('Cache-Control','private, no-store');
    }

    public function show(Request $request, StationSigningRequest $signingRequest, SigningStations $stations, DocumentSigning $signing)
    {
        $station = $stations->active($request);
        try { $stations->assigned($station,$signingRequest); }
        catch (HttpExceptionInterface $exception) { return to_route('signing-terminal.index'); }
        $item = $signingRequest->items()->whereNull('document_signature_id')->first();
        if (!$item) return to_route('signing-terminal.index');
        try { $context = $signing->preparedSource($item->source,$item->prepared_token,$stations->operatorFor($signingRequest)); }
        catch (ValidationException|HttpExceptionInterface|\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $stations->block($signingRequest,'Il documento non è più disponibile o è cambiato. Controllalo e invialo di nuovo.');
            return to_route('signing-terminal.index');
        }
        unset($context['bytes'],$context['hash']);
        return response()->view('document-signing.show',$context + ['stationRequest'=>$signingRequest,'source'=>$item->source,'signer'=>$signingRequest->signer_name,'role'=>$signingRequest->signer_role,
            'needsNote'=>false,'returnUrl'=>route('signing-terminal.index'),'consentText'=>DocumentSigning::CONSENT,'pdfUrl'=>route('signing-terminal.document',$item),
            'submitUrl'=>route('signing-terminal.complete',$item),'position'=>$item->position+1,'total'=>$signingRequest->items()->count()])
            ->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
    }

    public function document(Request $request, StationSigningItem $item, SigningStations $stations, DocumentSigning $signing)
    {
        $station = $stations->active($request);
        $stations->assigned($station,$item->request);
        abort_if($item->document_signature_id,410);
        $context = $signing->preparedSource($item->source,$item->prepared_token,$stations->operatorFor($item->request));
        return response($context['bytes'],200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="documento-da-firmare.pdf"','Cache-Control'=>'private, no-store','Referrer-Policy'=>'no-referrer']);
    }

    public function complete(Request $request, StationSigningItem $item, SigningStations $stations, DocumentSigningInput $input)
    {
        $station = $stations->active($request);
        $stations->sign($station,$item,$input->validate($request,station:true));
        $job = $item->request->fresh();
        return response()->json(['url'=>$job->pending() ? route('signing-terminal.show',$job) : route('signing-terminal.index'),'message'=>'Firma salvata.'])->header('Cache-Control','private, no-store');
    }
}
