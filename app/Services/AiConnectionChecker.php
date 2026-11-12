<?php

namespace App\Services;

use App\Models\AiSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AiConnectionChecker
{
    public function check(AiSetting $setting): array
    {
        if (blank($setting->api_key)) return $this->failure('missing_key', 'Salva una chiave OpenAI prima di verificare il collegamento.');
        if (!array_key_exists($setting->model, AiPricing::MODELS)) return $this->failure('invalid_model', 'Scegli e salva uno dei modelli disponibili prima di verificare.');

        try {
            /** Il controllo interroga solo il modello salvato: nessun documento, generazione o reindirizzamento esterno. */
            $response = Http::withToken($setting->api_key)->acceptJson()->connectTimeout(3)->timeout(10)->withoutRedirecting()
                ->get('https://api.openai.com/v1/models/'.rawurlencode($setting->model));
        } catch (ConnectionException) {
            return $this->failure('connection_error', 'OpenAI non è raggiungibile. Controlla la connessione del server e riprova.');
        } catch (\Throwable) {
            /** Le eccezioni del client HTTP possono contenere credenziali: non vengono mostrate né registrate. */
            return $this->failure('connection_error', 'Non è stato possibile completare la verifica. Riprova tra poco.');
        }

        if ($response->status() === 200) {
            if ($response->json('object') !== 'model' || $response->json('id') !== $setting->model) {
                return $this->failure('unexpected_response', 'OpenAI ha restituito una risposta non riconosciuta. Riprova tra poco.');
            }
            return ['state'=>'success', 'code'=>'connected', 'message'=>'Collegamento riuscito. La chiave è riconosciuta e il modello salvato è accessibile.'];
        }

        /** Usiamo messaggi locali: gli errori del fornitore non devono riportare la chiave nell'interfaccia. */
        return match ($response->status()) {
            401 => $this->failure('invalid_key', 'OpenAI non riconosce la chiave salvata. Usa Modifica per sostituirla.'),
            403 => $this->failure('permission_denied', 'La chiave non consente questo controllo. Verifica i permessi di lettura dei modelli nel progetto OpenAI.'),
            404 => $this->failure('model_unavailable', 'Il modello salvato non è disponibile per questa chiave. Controlla il modello e i permessi del progetto OpenAI.'),
            429 => $this->failure('rate_limited', 'OpenAI ha limitato le richieste. Attendi un momento e riprova.'),
            default => $this->failure('provider_unavailable', 'OpenAI non ha completato la verifica. Riprova tra poco.'),
        };
    }

    private function failure(string $code, string $message): array
    {
        return ['state'=>'error', 'code'=>$code, 'message'=>$message];
    }
}
