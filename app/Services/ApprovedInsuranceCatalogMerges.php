<?php

namespace App\Services;

use App\Models\{InsuranceBranch, InsuranceCatalogAlias, InsuranceSubbranch};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The five approved naming corrections; no product or policy classification is inferred. */
class ApprovedInsuranceCatalogMerges
{
    public const GROUPS = [
        ['name' => 'RC auto', 'sources' => ['RCA', 'RCAUTO'], 'children' => ['AUTO' => 'AUTO', 'MOTO' => 'MOTO', 'RCA' => null, 'RCAUTO' => null]],
        ['name' => 'Fideiussioni', 'sources' => ['FIDEIUSSIONE', 'FIDEJUSSIONI'], 'children' => ['PROVVIS' => 'PROVVIS'], 'review' => ['PROVVIS']],
        ['name' => 'Globale fabbricati', 'sources' => ['GLOBALE FABBRICATI', 'GLOBALI FABBRICATI'], 'children' => ['CONDOMINIO' => 'CONDOMINIO', 'FABBRICATO' => 'FABBRICATO']],
        ['name' => 'Viaggi', 'sources' => ['VIAGGI', 'VIAGGIO'], 'children' => ['VIAGGI' => 'Viaggi', 'VIAGGIO' => 'Viaggi']],
        ['name' => 'Cane e gatto', 'sources' => ['CANE& GATTO', 'CANE&GATTO'], 'children' => ['CANE&GATTO' => 'Cane e gatto']],
    ];

    public function apply(): array
    {
        return DB::transaction(function () {
            InsuranceBranch::orderBy('id')->lockForUpdate()->get();
            $report = ['merged' => [], 'already_applied' => []];
            foreach (self::GROUPS as $group) {
                $aliases = InsuranceCatalogAlias::whereIn('source_key', array_map(fn ($name) => InsuranceCatalogAlias::sourceKey($name), $group['sources']))->get();
                if ($aliases->isNotEmpty()) {
                    if ($aliases->count() !== count($group['sources']) || $aliases->pluck('insurance_branch_id')->unique()->count() !== 1) $this->conflict($group['name']);
                    $report['already_applied'][] = $group['name'];
                    continue;
                }
                $branches = collect($group['sources'])->map(function ($name) use ($group) {
                    $branch = InsuranceBranch::where('import_key', InsuranceCatalog::key($name))->first();
                    if (!$branch || InsuranceCatalog::normalize($branch->name) !== $name) $this->conflict($group['name']);
                    return $branch;
                });
                $branchIds = $branches->pluck('id')->all();
                $sources = $this->sources($branches);
                $children = InsuranceSubbranch::whereIn('insurance_branch_id', $branchIds)->orderBy('id')->lockForUpdate()->get();
                foreach ($branches->concat($children) as $record) {
                    if ($record->status !== 'active' || !$record->source_values || $record->document_categories || app(InsuranceCatalog::class)->used($record)) $this->conflict($group['name']);
                }
                foreach ($children as $child) {
                    if (!array_key_exists(InsuranceCatalog::normalize($child->name), $group['children']) || !$child->import_key) $this->conflict($group['name']);
                }
                if (InsuranceBranch::where('name_key', InsuranceCatalog::key($group['name']))->whereNotIn('id', $branchIds)->exists()) $this->conflict($group['name']);

                $target = $branches->first();
                $oldName = $target->name;
                $target->update(['name' => $group['name'], 'name_key' => InsuranceCatalog::key($group['name']), 'needs_review' => false,
                    'source_values' => $sources,
                    'notes' => $this->notes($branches, 'Accorpamento approvato: '.implode(' / ', $group['sources']).'.')]);
                $destinations = [];
                foreach ($children->groupBy(fn ($child) => $group['children'][InsuranceCatalog::normalize($child->name)] ?? '') as $name => $siblings) {
                    if ($name === '') continue;
                    $child = $siblings->first();
                    $child->update(['insurance_branch_id' => $target->id, 'name' => $name, 'name_key' => InsuranceCatalog::key($name),
                        'needs_review' => in_array($name, $group['review'] ?? [], true), 'source_values' => $this->sources($siblings),
                        'notes' => $siblings->pluck('notes')->filter()->unique()->implode("\n") ?: null]);
                    $destinations[$name] = $child->id;
                }
                // Save every source pair before removing redundant catalog records.
                foreach ($group['sources'] as $sourceName) $this->alias($sourceName, null, $target->id, null);
                foreach ($sources as $source) {
                    $subname = InsuranceCatalog::normalize($source['source_subbranch'] ?? '');
                    if ($subname === '') continue;
                    if (!array_key_exists($subname, $group['children'])) $this->conflict($group['name']);
                    $destination = $group['children'][$subname];
                    if ($destination !== null && !isset($destinations[$destination])) $this->conflict($group['name']);
                    $this->alias($source['source_branch'], $source['source_subbranch'], $target->id, $destination === null ? null : $destinations[$destination]);
                }
                foreach ($children as $child) if (!in_array($child->id, $destinations, true)) $child->delete();
                foreach ($branches->skip(1) as $branch) $branch->delete();
                app(AuditLogService::class)->log('catalog_merged', $target, ['name' => $oldName], ['name' => $target->name, 'needs_review' => false], 'Accorpati '.implode(' / ', $group['sources']).' in '.$target->name.'. Originali conservati; nessuna polizza importata.');
                $report['merged'][] = $target->name;
            }
            return $report;
        });
    }

    private function alias(string $branch, ?string $subbranch, int $target, ?int $child): void
    {
        $key = InsuranceCatalogAlias::sourceKey($branch, $subbranch);
        $alias = InsuranceCatalogAlias::firstOrNew(['source_key' => $key]);
        if ($alias->exists && ($alias->insurance_branch_id !== $target || $alias->insurance_subbranch_id !== $child)) $this->conflict($branch);
        $alias->fill(['source_branch' => $branch, 'source_subbranch' => $subbranch, 'insurance_branch_id' => $target, 'insurance_subbranch_id' => $child])->save();
    }

    private function sources(Collection $records): array
    {
        return $records->flatMap(fn ($record) => $record->source_values ?? [])->unique(fn ($value) => json_encode($value))->values()->all();
    }

    private function notes(Collection $records, string $decision): string
    {
        return $records->pluck('notes')->filter()->map(fn ($note) => str_replace('Nessun accorpamento semantico applicato.', 'Valori originali conservati.', $note))->unique()->push($decision)->implode("\n");
    }

    private function conflict(string $name): never
    {
        throw ValidationException::withMessages(['catalog' => 'Accorpamento non applicato: '.$name.' contiene modifiche o collegamenti da verificare. Nessuna voce è stata modificata.']);
    }
}
