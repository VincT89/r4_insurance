<?php

namespace App\Http\Controllers;

use App\Models\{Attachment, Client, SigningStation, StationSigningRequest};
use App\Services\{DocumentSigning, SigningStations};
use Illuminate\Http\Request;

class SigningStationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('create',Client::class);
        $stations = SigningStation::whereNotNull('paired_at')->with('pairedBy')->withCount(['requests as pending_requests_count'=>fn($q)=>$q->where('status','pending')->where('expires_at','>',now())])->orderBy('name')->get();
        return response()->view('signing-stations.index',compact('stations'))->header('Cache-Control','private, no-store');
    }

    public function pair(Request $request, SigningStations $stations)
    {
        $this->authorize('create',Client::class);
        $data = $request->validate(['pairing_code'=>['required','string','regex:/^[0-9]{8}$/D'],'name'=>['required','string','min:2','max:80','not_regex:/[\r\n\x00]/']]);
        $stations->pair($request->user(),$data['pairing_code'],$data['name']);
        return to_route('signing-stations.index')->with('success','Postazione collegata. Ora puoi inviare i documenti alla firma.');
    }

    public function revoke(Request $request, SigningStation $station, SigningStations $stations)
    {
        $request->validate(['confirmed'=>['accepted']]);
        $stations->revoke($station,$request->user());
        return to_route('signing-stations.index')->with('success','Collegamento revocato. Le firme già raccolte restano nello storico.');
    }

    public function create(Request $request, Attachment $attachment, DocumentSigning $signing, SigningStations $registry)
    {
        $prepared = $signing->prepare($attachment);
        unset($prepared['bytes'],$prepared['token']);
        $stations = SigningStation::where('status','active')->where('credential_expires_at','>',now())->withCount(['requests as pending_requests_count'=>fn($q)=>$q->where('status','pending')->where('expires_at','>',now())])->orderBy('name')->get();
        $candidates = $registry->candidates($attachment);
        $selected = $request->boolean('all') ? $candidates->modelKeys() : [$attachment->id];
        return response()->view('signing-stations.dispatch',$prepared + compact('stations','candidates','selected') + ['returnUrl'=>$signing->returnUrl($attachment)])->header('Cache-Control','private, no-store');
    }

    public function store(Request $request, Attachment $attachment, SigningStations $stations)
    {
        $this->authorize('download',$attachment);
        $data = $request->validate(['signing_station_id'=>['required','uuid'],'attachment_ids'=>['required','array','list','min:1','max:20'],'attachment_ids.*'=>['required','integer','distinct'],
            'signer_name'=>['required','string','max:150'],'signer_role'=>['nullable','string','max:150'],'note'=>['nullable','string','max:2000'],'identity_confirmed'=>['accepted']]);
        $job = $stations->dispatch($attachment,$request->user(),$data);
        return to_route('station-signing.show',$job)->with('success','Documenti inviati alla postazione. Il cliente può leggere e firmare.');
    }

    private function authorizeRequest(StationSigningRequest $signingRequest): void
    {
        $this->authorize('updateRegistry',$signingRequest->client);
    }

    public function show(StationSigningRequest $signingRequest, DocumentSigning $signing)
    {
        $this->authorizeRequest($signingRequest);
        $signingRequest->load(['station','items.source','items.signature.signedFile','author']);
        $returnUrl = $signing->returnUrl($signingRequest->items->first()->source);
        return response()->view('signing-stations.request',compact('signingRequest','returnUrl'))->header('Cache-Control','private, no-store');
    }

    public function status(StationSigningRequest $signingRequest)
    {
        $this->authorizeRequest($signingRequest);
        return response()->json(['status'=>$signingRequest->pending() ? 'pending' : ($signingRequest->status === 'pending' ? 'expired' : $signingRequest->status),
            'label'=>$signingRequest->state_label,'completed'=>$signingRequest->items()->whereNotNull('document_signature_id')->count(),
            'total'=>$signingRequest->items()->count()])->header('Cache-Control','private, no-store');
    }

    public function cancel(Request $request, StationSigningRequest $signingRequest, SigningStations $stations)
    {
        $stations->cancel($signingRequest,$request->user());
        return to_route('station-signing.show',$signingRequest)->with('success','Richiesta annullata. Le firme già raccolte restano nello storico.');
    }
}
