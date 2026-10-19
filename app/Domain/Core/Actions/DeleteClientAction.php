<?php
namespace App\Domain\Core\Actions;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteClientAction
{
    public function execute(Client $client): void
    {
        DB::transaction(function () use ($client) {
            $locked = Client::whereKey($client->id)->lockForUpdate()->firstOrFail();
            if ($locked->claims()->exists() || $locked->formPackages()->exists() || $locked->quoteCases()->exists() || $locked->attachments()->exists() || $locked->documentRequests()->exists() || $locked->policies()->exists() || $locked->vehicles()->exists()
                || $locked->contacts()->exists() || $locked->assets()->exists() || $locked->interactions()->exists()
                || $locked->consents()->exists() || $locked->relationships()->exists() || $locked->households()->exists()
                || $locked->mergedSources()->exists() || $locked->merged_into_id) {
                throw ValidationException::withMessages([
                    'client' => 'Il cliente contiene dati o collegamenti da conservare. Impostalo come inattivo per conservarne lo storico.',
                ]);
            }
            $locked->delete();
        });
    }
}
