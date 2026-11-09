<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\DocumentSigning;
use Illuminate\Http\Request;

class DocumentSigningController extends Controller
{
    public function show(Attachment $attachment, DocumentSigning $signing)
    {
        $data = $signing->prepare($attachment);
        unset($data['bytes']);
        return response()->view('document-signing.show', $data + ['returnUrl'=>$signing->returnUrl($attachment), 'consentText'=>DocumentSigning::CONSENT])->header('Cache-Control','private, no-store');
    }

    public function store(Request $request, Attachment $attachment, DocumentSigning $signing)
    {
        $this->authorize('download', $attachment);
        $data = app(\App\Services\DocumentSigningInput::class)->validate($request);
        $signature = $signing->sign($attachment, $data);
        $url = $signing->returnUrl($attachment);
        if ($request->expectsJson()) return response()->json(['url'=>$url, 'preview'=>$signature->signedFile->openUrl(), 'message'=>'Firma raccolta. La copia e l’originale sono conservati nello storico.']);
        return redirect($url)->with('success','Firma raccolta. La copia e l’originale sono conservati nello storico.');
    }
}
