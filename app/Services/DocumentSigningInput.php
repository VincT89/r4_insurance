<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentSigningInput
{
    public function validate(Request $request, bool $station = false): array
    {
        $data = $request->validate([
            'token'=>$station ? ['prohibited'] : ['required','string','max:8000'],
            'signer_name'=>[$station ? 'sometimes' : 'required','string','max:150'], 'signer_role'=>['nullable','string','max:150'],
            'consent'=>['accepted'], 'identity_confirmed'=>$station ? ['prohibited'] : ['accepted'], 'note'=>['nullable','string','max:2000'],
            'strokes'=>['required','array','list','max:80'],
            'placements'=>['required','array','list','min:1','max:10'], 'placements.*'=>['array:page,x,y,width,height'],
            'placements.*.page'=>['required','integer','min:1','max:100'],
            'placements.*.x'=>['required','numeric','between:0,1'], 'placements.*.y'=>['required','numeric','between:0,1'],
            'placements.*.width'=>['required','numeric','between:0.05,0.8'], 'placements.*.height'=>['required','numeric','between:0.015,0.5'],
        ], ['consent.accepted'=>'Conferma la lettura e la volontà di firmare il documento.', 'identity_confirmed.accepted'=>'L’operatore deve confermare la verifica dell’identità.', 'placements.required'=>'Indica almeno un punto firma sul documento.']);
        foreach ($data['placements'] as $placement) {
            if ($placement['x']+$placement['width'] > 1.00001 || $placement['y']+$placement['height'] > 1.00001) throw ValidationException::withMessages(['signature'=>'Il punto firma esce dalla pagina. Riposizionalo.']);
        }
        $data['placements'] = array_map(fn ($placement) => ['page'=>(int)$placement['page'], 'x'=>(float)$placement['x'], 'y'=>(float)$placement['y'], 'width'=>(float)$placement['width'], 'height'=>(float)$placement['height']], $data['placements']);
        return $data;
    }
}
