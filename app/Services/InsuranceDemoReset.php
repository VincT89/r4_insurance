<?php

namespace App\Services;

use App\Models\{Attachment, Client, InsurancePolicy, User};
use Illuminate\Support\Facades\{Crypt, DB, Schema, Storage};

class InsuranceDemoReset
{
    public const TABLES = ['insurance_policy_changes','agency_communication_changes','client_document_request_files','agency_communication_attachments','insurance_commission_movements','insurance_commissions','insurance_claims','insurance_claim_activities','insurance_claim_events','insurance_claim_reminders','insurance_form_packages','insurance_form_documents','insurance_form_submissions','insurance_coverage_checks','insurance_quote_emails','insurance_policy_corrections','agency_communication_collections','insurance_quote_cases','insurance_quotes','insurance_titles','insurance_collections','client_consents', 'attachments', 'client_document_requests', 'insurance_policy_imports', 'insurance_policies', 'client_relationships', 'client_household_members', 'client_households', 'client_merges', 'client_contacts', 'client_interactions', 'client_assets', 'client_vehicles', 'client_user', 'clients', 'insurance_deadline_reminders', 'agency_communication_policies'];
    public const PRESERVED = ['insurance_form_templates','users', 'supplier_companies', 'supplier_agencies', 'supplier_agency_company', 'insurance_branches', 'insurance_subbranches', 'insurance_catalog_aliases','agency_commission_schedules','agency_commission_rates'];

    public function apply(User $actor): array
    {
        /** Recognize the new schema without allowing a reset to erase contractual history. */
        if (DB::table('insurance_policy_changes')->exists() || DB::table('agency_communication_changes')->exists()) throw new \RuntimeException('Reset non disponibile: variazioni contrattuali e comunicazioni da conservare.');
        if (DB::table('client_document_request_files')->exists()) throw new \RuntimeException('Reset non disponibile: collegamenti alle checklist documentali da conservare.');
        if (DB::table('agency_communication_attachments')->exists()) throw new \RuntimeException('Reset non disponibile: comunicazioni con allegati da conservare.');
        if (DB::table('insurance_claims')->exists()) throw new \RuntimeException('Reset non disponibile: sinistri e attività da conservare.');
        if (!$actor->isAdmin() || $actor->status !== 'active') throw new \RuntimeException('Richiesto amministratore attivo.');
        if (DB::table('insurance_form_packages')->exists() || DB::table('insurance_coverage_checks')->exists()) throw new \RuntimeException('Reset non disponibile: moduli o controlli RCA da conservare.');
        if (InsurancePolicy::whereNotNull('work_closed_at')->exists() || DB::table('insurance_policy_corrections')->exists()) throw new \RuntimeException('Reset non disponibile: lavorazioni chiuse o segnalazioni da conservare.');
        foreach(['insurance_commissions','insurance_commission_movements','insurance_quote_cases','insurance_quotes','insurance_titles','insurance_collections'] as $table) if(DB::table($table)->exists()) throw new \RuntimeException('Reset non disponibile: sono presenti preventivi o movimenti contabili da conservare.');
        $backup = 'insurance-demo-backup-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
        $disk = Storage::disk('local'); $files = []; $manifest = [];
        $result = DB::transaction(function () use ($actor, $backup, $disk, &$files, &$manifest) {
            $before = [];
            foreach (self::PRESERVED as $table) $before[$table] = $this->fingerprint($table);
            $clients = DB::table('clients')->orderBy('id')->lockForUpdate()->get();
            $policies = DB::table('insurance_policies')->orderBy('id')->lockForUpdate()->get();
            /** Unknown dependencies stop the reset; foreign-key checks remain enabled throughout. */
            if (DB::connection()->getDriverName() === 'mysql') {
                $schema = DB::connection()->getDatabaseName();
                $dependencies = DB::table('information_schema.key_column_usage')->where('table_schema', $schema)->where('referenced_table_schema', $schema)->whereIn('referenced_table_name', self::TABLES)->distinct()->get(['table_name', 'referenced_table_name']);
                foreach ($dependencies as $foreign) if (!in_array($foreign->table_name, self::TABLES, true)) throw new \RuntimeException('Dipendenza non prevista: '.$foreign->table_name);
            } else {
                foreach (Schema::getTables('main') as $table) foreach (Schema::getForeignKeys($table['name']) as $foreign) {
                    if (in_array($foreign['foreign_table'], self::TABLES, true) && !in_array($table['name'], self::TABLES, true)) throw new \RuntimeException('Dipendenza non prevista: '.$table['name']);
                }
            }
            $attachments = DB::table('attachments')->where('attachable_type', (new Client)->getMorphClass())->get();
            if (DB::table('attachments')->whereNotNull('insurance_policy_id')->where('attachable_type','!=',(new Client)->getMorphClass())->exists()) throw new \RuntimeException('Allegato collegato a una polizza con proprietario inatteso.');
            $snapshots = [];
            foreach (array_merge(self::TABLES, self::PRESERVED, ['registry_import_entries','notifications','audit_logs']) as $table) {
                $json = json_encode(DB::table($table)->get()->all(), JSON_THROW_ON_ERROR);
                $encrypted = Crypt::encryptString($json);
                if (!$disk->put($backup.'/'.$table.'.json.enc', $encrypted)) throw new \RuntimeException('Backup non riuscito.');
                if (!hash_equals(hash('sha256',$json),hash('sha256',Crypt::decryptString($disk->get($backup.'/'.$table.'.json.enc'))))) throw new \RuntimeException('Backup non verificato.');
                $manifest[$table] = ['rows'=>DB::table($table)->count(), 'sha256'=>hash('sha256',$json)];
            }
            foreach ($attachments as $file) $files[] = ['disk'=>$file->disk,'path'=>$file->path];
            foreach ($clients as $client) if ($client->logo_path) $files[]=['disk'=>'public','path'=>$client->logo_path];
            foreach ($files as $index => $file) {
                if (!in_array($file['disk'], ['attachments','public'], true) || str_contains($file['path'],'..') || preg_match('/^[\\\\\/]|^[a-z]:/i',$file['path'])) throw new \RuntimeException('Percorso allegato inatteso.');
                $source = Storage::disk($file['disk']);
                if (!$source->exists($file['path'])) { $manifest['missing_files'][]=$file; continue; }
                $bytes = $source->get($file['path']);
                if (!$disk->put($backup.'/files/'.$index.'.enc', Crypt::encryptString($bytes))) throw new \RuntimeException('Copia allegati non riuscita.');
                if (!hash_equals(hash('sha256',$bytes),hash('sha256',Crypt::decryptString($disk->get($backup.'/files/'.$index.'.enc'))))) throw new \RuntimeException('Copia allegato non verificata.');
                $manifest['files'][$index]=$file+['sha256'=>hash('sha256',$bytes)];
            }
            if (!$disk->put($backup.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))) throw new \RuntimeException('Manifest backup non disponibile.');
            DB::table('client_consents')->delete();
            DB::table('attachments')->whereIn('id',$attachments->pluck('id'))->update(['previous_version_id'=>null]);
            DB::table('attachments')->whereIn('id',$attachments->pluck('id'))->delete();
            DB::table('client_document_requests')->delete();
            DB::table('insurance_policy_imports')->delete();
            DB::table('insurance_policies')->delete();
            foreach (['client_relationships','client_household_members','client_households','client_merges','client_contacts','client_interactions','client_assets','client_vehicles','client_user'] as $table) DB::table($table)->delete();
            DB::table('clients')->update(['merged_into_id'=>null]);
            DB::table('clients')->delete();
            DB::table('registry_import_entries')->where('kind','client')->delete();
            DB::table('insurance_deadline_reminders')->delete();
            DB::table('notifications')->where('type',DeadlineReminders::TYPE)->orWhereNotNull('data->client_id')->delete();
            /** Historical audit events remain attributable; new IDs are not recycled. */
            (new \Database\Seeders\InsuranceDemoSeeder)->run();
            foreach ($before as $table=>$hash) if (!hash_equals($hash,$this->fingerprint($table))) throw new \RuntimeException('Modifica inattesa a '.$table.'. Ripristino della transazione.');
            if (Client::count()!==50 || InsurancePolicy::count()!==50) throw new \RuntimeException('Conteggio dei dati di prova inatteso.');
            app(AuditLogService::class)->log('demo_reset', $actor, description:'Sostituiti '.$clients->count().' clienti e '.$policies->count().' polizze con 50 clienti e 50 polizze dimostrative. Backup privato: '.$backup.'.', userId:$actor->id);
            return ['previous_clients'=>$clients->count(), 'previous_policies'=>$policies->count(), 'clients'=>50, 'policies'=>50, 'preserved'=>array_keys($before), 'backup'=>$backup];
        });
        $result['files_removed']=0; $result['file_cleanup_pending']=[];
        foreach ($files as $file) {
            $source=Storage::disk($file['disk']);
            if ($source->exists($file['path'])) {
                if ($source->delete($file['path'])) $result['files_removed']++;
                else $result['file_cleanup_pending'][]=$file;
            }
        }
        return $result;
    }

    private function fingerprint(string $table): string
    {
        $rows=DB::table($table)->get()->map(fn($row)=>json_encode($row,JSON_THROW_ON_ERROR))->sort()->values()->all();
        return hash('sha256',implode("\n",$rows));
    }
}
