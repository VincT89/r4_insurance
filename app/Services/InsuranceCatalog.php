<?php

namespace App\Services;

use App\Models\{InsuranceBranch, InsuranceCatalogAlias, InsurancePolicy, InsuranceSubbranch};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InsuranceCatalog
{
    public static function normalize(string $value): string { return mb_strtoupper(preg_replace('/\s+/u', ' ', trim($value))); }
    public static function key(string $value): string { return hash('sha256', self::normalize($value)); }

    public function save(InsuranceBranch|InsuranceSubbranch $record, array $values): InsuranceBranch|InsuranceSubbranch
    {
        return DB::transaction(function () use ($record, $values) {
            // All catalog mutations and policy selection take branch locks in the same order.
            InsuranceBranch::orderBy('id')->lockForUpdate()->get();
            if ($record->exists) $record = $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $values['name'] = preg_replace('/\s+/u', ' ', trim($values['name']));
            $values['name_key'] = self::key($values['name']);
            $query = $record->newQuery()->where('name_key', $values['name_key'])->when($record->exists, fn ($q) => $q->where('id', '!=', $record->id));
            if ($record instanceof InsuranceSubbranch) {
                $branch = InsuranceBranch::findOrFail($values['insurance_branch_id']);
                $query->where('insurance_branch_id', $branch->id);
                if ($branch->status !== 'active' && $values['status'] === 'active') throw ValidationException::withMessages(['status' => 'Riattiva prima il ramo, oppure conserva il sottoramo come inattivo.']);
            }
            if ($query->exists()) throw ValidationException::withMessages(['name' => 'Questa denominazione è già presente nel catalogo.']);
            $moves = $record instanceof InsuranceSubbranch && (int) $record->insurance_branch_id !== (int) $values['insurance_branch_id'];
            if ($record->exists && ($record->name !== $values['name'] || $moves) && $this->used($record)) {
                throw ValidationException::withMessages(['name' => 'Questa voce è usata da polizze, richieste documentali o accorpamenti approvati dell’Excel. Conserva la denominazione e disattivala se non serve più; puoi creare una nuova voce.']);
            }
            $old = $record->exists ? $record->only(['name', 'status', 'needs_review', 'notes', 'insurance_branch_id', 'document_categories', 'person_document_categories', 'company_document_categories']) : null;
            $record->fill($values)->save();
            app(AuditLogService::class)->log($old ? 'catalog_updated' : 'catalog_created', $record, $old, $values, ($old ? 'Aggiornato ' : 'Creato ').($record instanceof InsuranceBranch ? 'il ramo ' : 'il sottoramo ').$record->name);
            return $record;
        });
    }

    public function used(InsuranceBranch|InsuranceSubbranch $record): bool
    {
        if (\App\Models\InsuranceFormTemplate::where($record instanceof InsuranceBranch ? 'insurance_branch_id' : 'insurance_subbranch_id',$record->id)->exists()) return true;
        if (\App\Models\AgencyCommissionRate::where($record instanceof InsuranceBranch ? 'insurance_branch_id' : 'insurance_subbranch_id',$record->id)->exists()) return true;
        if (\App\Models\InsuranceQuoteCase::where($record instanceof InsuranceBranch ? 'insurance_branch_id' : 'insurance_subbranch_id',$record->id)->exists()) return true;
        if (InsuranceCatalogAlias::where($record instanceof InsuranceBranch ? 'insurance_branch_id' : 'insurance_subbranch_id', $record->id)->exists()) return true;
        if ($record instanceof InsuranceBranch && \App\Models\ClientDocumentRequest::where('insurance_branch_id', $record->id)->exists()) return true;
        if ($record->policies()->exists()) return true;
        $query = InsurancePolicy::whereRaw('UPPER(category) = ?', [self::normalize($record instanceof InsuranceBranch ? $record->name : $record->branch->name)]);
        if ($record instanceof InsuranceSubbranch) $query->whereRaw('UPPER(subcategory) = ?', [self::normalize($record->name)]);
        return $query->exists();
    }

    public function delete(InsuranceBranch|InsuranceSubbranch $record): void
    {
        DB::transaction(function () use ($record) {
            InsuranceBranch::orderBy('id')->lockForUpdate()->get();
            $record = $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($this->used($record) || ($record instanceof InsuranceBranch && $record->subbranches()->exists())) {
                throw ValidationException::withMessages(['catalog' => 'La voce ha collegamenti e non può essere eliminata. Puoi impostarla come inattiva.']);
            }
            app(AuditLogService::class)->log('catalog_deleted', $record, description: 'Eliminata la voce di catalogo '.$record->name);
            $record->delete();
        });
    }

    public function policyValues(array $values, ?InsurancePolicy $policy = null): array
    {
        $branches = InsuranceBranch::orderBy('id')->lockForUpdate()->get();
        if ($branches->isEmpty()) return $values;
        $branch = $branches->firstWhere('name_key', self::key($values['category']));
        $same = $policy && $policy->category === $values['category'] && ($policy->subcategory ?: null) === ($values['subcategory'] ?? null);
        if (!$branch || $branch->status !== 'active') {
            if ($same) return $values;
            throw ValidationException::withMessages(['category' => 'Seleziona un ramo attivo dal catalogo.']);
        }
        $subbranch = filled($values['subcategory'] ?? null) ? $branch->subbranches()->where('name_key', self::key($values['subcategory']))->lockForUpdate()->first() : null;
        if (filled($values['subcategory'] ?? null) && (!$subbranch || $subbranch->status !== 'active')) {
            if ($same) return $values;
            throw ValidationException::withMessages(['subcategory' => 'Seleziona un sottoramo attivo appartenente al ramo scelto.']);
        }
        return array_merge($values, ['insurance_branch_id' => $branch->id, 'insurance_subbranch_id' => $subbranch?->id, 'category' => $branch->name, 'subcategory' => $subbranch?->name]);
    }

    public function importPreview(): array
    {
        $summary = app(InsuranceImportPreview::class)->summary() ?? throw new \RuntimeException('Anteprima Excel non disponibile.');
        return DB::transaction(function () use ($summary) {
            InsuranceBranch::orderBy('id')->lockForUpdate()->get();
            $report = ['branches_created' => 0, 'subbranches_created' => 0, 'excluded_pairs' => 0];
            $groups = collect($summary['taxonomy'])->reject(function ($pair) use (&$report) {
                if ($pair['result'] === 'esclusa' || !trim($pair['source_branch'])) { $report['excluded_pairs']++; return true; }
                return false;
            })->groupBy(fn ($pair) => self::normalize($pair['source_branch']));
            foreach ($groups as $name => $pairs) {
                $key = self::key($name);
                $branch = InsuranceCatalogAlias::where('source_key', InsuranceCatalogAlias::sourceKey($name))->first()?->branch
                    ?? InsuranceBranch::where('import_key', $key)->first();
                if (!$branch) {
                    // A manually created matching name must be reviewed rather than overwritten.
                    if (InsuranceBranch::where('name_key', $key)->exists()) throw ValidationException::withMessages(['catalog' => 'Esiste già una voce manuale chiamata '.$name.'. Verifica prima del caricamento.']);
                    $branch = InsuranceBranch::create(['name' => $name, 'name_key' => $key, 'import_key' => $key, 'status' => 'active',
                        'needs_review' => $pairs->contains(fn ($p) => $p['result'] === 'da_confermare'),
                        'source_values' => $pairs->values()->all(), 'notes' => 'Denominazione operativa originale del file Gestionale. Nessun accorpamento semantico applicato.']);
                    $report['branches_created']++;
                    app(AuditLogService::class)->log('catalog_imported', $branch, description: 'Caricato dal catalogo Excel il ramo '.$name);
                }
                foreach ($pairs->filter(fn ($p) => filled($p['source_subbranch']))->groupBy(fn ($p) => self::normalize($p['source_subbranch'])) as $subname => $sources) {
                    // An approved pair can deliberately map to no subbranch (RCA/RCA, RCAUTO/RCAUTO).
                    if (InsuranceCatalogAlias::where('source_key', InsuranceCatalogAlias::sourceKey($name, $subname))->exists()) continue;
                    $subkey = self::key($name.'|'.$subname);
                    if (InsuranceSubbranch::where('import_key', $subkey)->exists()) continue;
                    if ($branch->subbranches()->where('name_key', self::key($subname))->exists()) throw ValidationException::withMessages(['catalog' => 'Sottoramo manuale già presente: '.$name.' / '.$subname]);
                    $sub = $branch->subbranches()->create(['name' => $subname, 'name_key' => self::key($subname), 'import_key' => $subkey, 'status' => 'active',
                        'needs_review' => $sources->contains(fn ($p) => $p['result'] === 'da_confermare'), 'source_values' => $sources->values()->all()]);
                    $report['subbranches_created']++;
                    app(AuditLogService::class)->log('catalog_imported', $sub, description: 'Caricato dal catalogo Excel il sottoramo '.$name.' / '.$subname);
                }
            }
            return $report;
        });
    }

    public function previewTaxonomy(array $pairs): array
    {
        $aliases = InsuranceCatalogAlias::with(['branch', 'subbranch'])->get()->keyBy('source_key');
        $branches = InsuranceBranch::with('subbranches')->get()->keyBy('import_key');
        return array_map(function ($pair) use ($aliases, $branches) {
            $alias = $aliases->get(InsuranceCatalogAlias::sourceKey($pair['source_branch'], $pair['source_subbranch']));
            $branchAlias = $aliases->get(InsuranceCatalogAlias::sourceKey($pair['source_branch']));
            $branch = $alias?->branch ?? $branchAlias?->branch ?? $branches->get(self::key($pair['source_branch']));
            $sub = $alias ? $alias->subbranch : $branch?->subbranches->firstWhere('import_key', self::key($pair['source_branch'].'|'.$pair['source_subbranch']));
            $excluded = $pair['result'] === 'esclusa';
            $unmapped = !$branch || (filled($pair['source_subbranch']) && !$alias && !$sub);
            $review = !$excluded && ($unmapped || $branch->needs_review || $sub?->needs_review);
            return array_merge($pair, [
                'branch' => $excluded ? null : $branch?->name,
                'subbranch' => $excluded ? null : $sub?->name,
                'product' => null,
                'result' => $excluded ? 'esclusa' : ($review ? 'da_verificare' : ($alias ? 'accorpamento_approvato' : 'conservata')),
                'reason' => $alias ? 'Accorpamento approvato. '.($review ? 'La suddivisione resta da verificare.' : 'Originali conservati.').(filled($pair['source_subbranch']) && !$sub ? ' La voce ripete il ramo: sottoramo non specificato.' : '') : $pair['reason'],
            ]);
        }, $pairs);
    }
}
