<?php

namespace App\Services;

use App\Models\{SupplierAgency, SupplierCompany};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierAgencyRegistry
{
    public function save(SupplierAgency $supplier, array $values): SupplierAgency
    {
        return DB::transaction(function () use ($supplier, $values) {
            // Serialize relationship writes; deletion of a company takes the same lock.
            $companies = SupplierCompany::orderBy('id')->lockForUpdate()->get();
            if ($supplier->exists) $supplier = SupplierAgency::whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $values['name'] = preg_replace('/\s+/u', ' ', trim($values['name']));
            if (array_key_exists('agency_code', $values)) $values['agency_code'] = $values['agency_code'] !== null ? trim($values['agency_code']) : null;
            $agencyCode = array_key_exists('agency_code', $values) ? $values['agency_code'] : $supplier->agency_code;
            $sameAgency = SupplierAgency::whereRaw('UPPER(TRIM(name)) = ?', [mb_strtoupper($values['name'])])
                ->when($supplier->exists, fn ($q) => $q->where('id', '!=', $supplier->id))->get()
                ->contains(fn ($other) => mb_strtoupper(trim($other->agency_code ?? '')) === mb_strtoupper($agencyCode ?? ''));
            if ($sameAgency) throw ValidationException::withMessages(['name' => 'Esiste già un’agenzia con questo nome e codice. Apri la scheda esistente e aggiungi le altre compagnie.']);
            $field = array_key_exists('supplier_company_ids', $values) ? 'supplier_company_ids' : 'supplier_company_id';
            $ids = array_values(array_unique(array_map('intval', $values['supplier_company_ids'] ?? [$values['supplier_company_id'] ?? 0])));
            sort($ids);
            if (! $ids || array_diff($ids, $companies->modelKeys())) {
                throw ValidationException::withMessages([$field => 'Seleziona almeno una compagnia disponibile.']);
            }
            $oldIds = $supplier->exists ? $supplier->companies()->pluck('supplier_companies.id')->map(fn ($id) => (int) $id)->all() : [];
            sort($oldIds);
            if ($supplier->exists && $supplier->commissionSchedules()->whereNotIn('supplier_company_id',$ids)->exists()) throw ValidationException::withMessages([$field=>'Le condizioni provvigionali conservano questo mandato nello storico. Mantieni il collegamento alla compagnia.']);
            if($supplier->exists && \App\Models\InsuranceQuote::where('supplier_agency_id',$supplier->id)->whereNotIn('supplier_company_id',$ids)->exists()) throw ValidationException::withMessages([$field=>'Un preventivo usa uno dei mandati rimossi. Conserva il collegamento alla compagnia.']);
            if ($supplier->exists && $supplier->policies()->whereNotNull('supplier_company_id')->whereNotIn('supplier_company_id', $ids)->exists()) {
                throw ValidationException::withMessages([$field => 'Una polizza usa uno dei mandati rimossi. Conserva il collegamento alla compagnia.']);
            }
            $parentId = $values['parent_agency_id'] ?? null;
            if ($parentId) {
                $parent = SupplierAgency::whereKey($parentId)->lockForUpdate()->first();
                if (! $parent || (int) $parent->id === (int) $supplier->id || $parent->parent_agency_id
                    || array_diff($ids, $parent->companies()->pluck('supplier_companies.id')->all())) {
                    throw ValidationException::withMessages(['parent_agency_id' => 'Scegli un’agenzia principale collegata a tutte le compagnie selezionate, diversa da questa anagrafica.']);
                }
            }
            if ($supplier->exists && $supplier->subagencies()->exists()) {
                foreach ($supplier->subagencies()->with('companies')->get() as $child) {
                    if (array_diff($child->companies->modelKeys(), $ids)) {
                        throw ValidationException::withMessages([$field => 'Una subagenzia usa una delle compagnie rimosse. Riassegnala prima di rimuovere il collegamento.']);
                    }
                }
                if ($parentId) {
                    throw ValidationException::withMessages(['parent_agency_id' => 'Questa agenzia ha già subagenzie. Riassegnale prima di trasformarla in subagenzia.']);
                }
            }
            unset($values['supplier_company_ids']);
            $values['supplier_company_id'] = in_array((int) $supplier->supplier_company_id, $ids, true) ? $supplier->supplier_company_id : $ids[0];
            $supplier->fill($values + ['parent_agency_id' => null])->save();
            $supplier->companies()->sync($ids);
            if ($oldIds !== $ids) app(AuditLogService::class)->log('mandates_changed', $supplier, ['supplier_company_ids' => $oldIds], ['supplier_company_ids' => $ids]);
            return $supplier->unsetRelation('companies');
        });
    }

    public function delete(SupplierAgency $supplier): void
    {
        DB::transaction(function () use ($supplier) {
            SupplierCompany::orderBy('id')->lockForUpdate()->get();
            $supplier = SupplierAgency::whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            if ($supplier->commissionSchedules()->exists()) throw ValidationException::withMessages(['supplier'=>'L’agenzia ha condizioni provvigionali da conservare. Puoi impostarla come inattiva.']);
            if ($supplier->subagencies()->exists() || $supplier->policies()->exists() || \App\Models\InsuranceQuote::where('supplier_agency_id',$supplier->id)->exists()) {
                throw ValidationException::withMessages(['supplier' => 'L’agenzia ha subagenzie o polizze collegate e non può essere eliminata. Puoi impostarla come inattiva.']);
            }
            $supplier->delete();
        });
    }
}
