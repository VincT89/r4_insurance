<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class DocumentSignaturePdf
{
    public function render(string $original, array $input, array $evidence): string
    {
        $directory = storage_path('app/signing-tmp/'.Str::uuid());
        File::ensureDirectoryExists($directory, 0700);
        try {
            File::put($directory.'/source.pdf', $original);
            $pdf = new \Dompdf\Dompdf(['isRemoteEnabled'=>false, 'isPhpEnabled'=>false, 'isJavascriptEnabled'=>false]);
            $pdf->setPaper('a4');
            $pdf->loadHtml(view('document-signing.receipt', compact('evidence'))->render());
            $pdf->render();
            File::put($directory.'/receipt.pdf', $pdf->output());
            /** A fixed local program receives only private temporary paths; no shell or external service is involved. */
            /** Le richieste HTTP PHP possono omettere le variabili di sistema necessarie a Node e alla sorgente casuale di Windows. */
            $environment = ['NODE_OPTIONS'=>false, 'NODE_PATH'=>false];
            foreach (['PATH','SystemRoot','WINDIR','TEMP','TMP','COMSPEC','PATHEXT'] as $variable) {
                $value = getenv($variable);
                if ($value !== false) $environment[$variable] = $value;
            }
            $process = new Process([(string)config('document_signing.node_binary'), '--max-old-space-size=256', base_path('scripts/sign-document.mjs'), $directory], base_path(), $environment);
            $process->setInput(json_encode(['strokes'=>$input['strokes'], 'placements'=>$input['placements'], 'maxPages'=>config('document_signing.max_pages')], JSON_THROW_ON_ERROR));
            $process->setTimeout(35);
            $process->run();
            $result = json_decode($process->getOutput(), true);
            if (!$process->isSuccessful() || empty($result['ok']) || !is_file($directory.'/signed.pdf')) {
                /** Registra solo l'esito tecnico, senza contenuto PDF, firma o dati del firmatario. */
                Log::warning('Motore firma PDF: operazione non completata.', ['exit_code'=>$process->getExitCode(), 'error_code'=>in_array($result['error'] ?? null, ['signed','encrypted','pages','placement','invalid'], true) ? $result['error'] : 'no_result', 'engine_missing'=>str_contains($process->getErrorOutput(), 'not recognized') || str_contains($process->getErrorOutput(), 'non è riconosciuto'), 'module_missing'=>str_contains($process->getErrorOutput(), 'Cannot find module')]);
                $message = match ($result['error'] ?? null) {
                    'signed' => 'Il PDF contiene già una firma digitale. Conserva quella versione e richiedi un documento predisposto per la nuova firma.',
                    'encrypted' => 'Il PDF è protetto. Occorre una copia accessibile e predisposta per la firma.',
                    'pages' => 'Il documento supera il limite di 100 pagine per questa modalità di firma.',
                    'placement' => 'Un punto firma non appartiene alle pagine del documento. Riapri il documento e riposizionalo.',
                    default => 'Non è possibile preparare la copia firmata. Il documento originale è conservato; riprova o usa Carica copia firmata.',
                };
                throw ValidationException::withMessages(['signature'=>$message]);
            }
            $bytes = File::get($directory.'/signed.pdf');
            if (strlen($bytes) > 30 * 1024 * 1024 || !str_starts_with($bytes, '%PDF-')) throw ValidationException::withMessages(['signature'=>'La copia firmata supera i limiti consentiti.']);
            return $bytes;
        } catch (\Symfony\Component\Process\Exception\ExceptionInterface $exception) {
            throw ValidationException::withMessages(['signature'=>'La firma non è stata salvata. Il motore PDF non è disponibile o ha impiegato troppo tempo: riprova o contatta l’amministratore.']);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
