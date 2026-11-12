<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceiveInboundEmailRequest;
use App\Services\InboundEmailReceiver;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ReceiveInboundEmailController extends Controller
{
    public function __invoke(ReceiveInboundEmailRequest $request, InboundEmailReceiver $receiver)
    {
        try {
            [$email, $duplicate] = $receiver->receive($request->validated('message'), $request->file('attachments', []));
        } catch (HttpExceptionInterface $exception) {
            return response()->json(['message'=>$exception->getMessage()], $exception->getStatusCode());
        } catch (\Throwable $exception) {
            /** Gli errori di database o storage possono contenere dati della mail: non esporre né registrare il messaggio grezzo. */
            Log::error('Ricezione email non completata.', ['error_type'=>$exception::class]);
            return response()->json(['message'=>'Consegna non completata. Riprovare con lo stesso identificativo.'], 503);
        }
        return response()->json(['id'=>$email->public_id, 'duplicate'=>$duplicate, 'status'=>$email->status, 'attachments_count'=>$email->files()->count()], $duplicate ? 200 : 201);
    }
}
