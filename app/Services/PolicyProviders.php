<?php

namespace App\Services;

use App\Models\{InsurancePolicy, SupplierAgency, SupplierCompany};
use Illuminate\Validation\ValidationException;

class PolicyProviders
{
    public function values(array $values, ?InsurancePolicy $policy = null): array
    {
        $companies = SupplierCompany::orderBy('id')->lockForUpdate()->get();
        $sameCompany = $policy && $policy->company === $values['company'];
        $company = $sameCompany && $policy->supplier_company_id ? $companies->firstWhere('id', $policy->supplier_company_id)
            : $companies->first(fn ($record) => InsuranceCatalog::normalize($record->name) === InsuranceCatalog::normalize($values['company']));
        $agencyId = array_key_exists('supplier_agency_id', $values) ? $values['supplier_agency_id'] : $policy?->supplier_agency_id;
        $agency = $agencyId ? SupplierAgency::whereKey($agencyId)->lockForUpdate()->first() : null;
        $sameAgency = $sameCompany && (int) $policy->supplier_agency_id === (int) $agencyId;
        if ($company && $company->status !== 'active' && !$sameCompany) throw ValidationException::withMessages(['company' => 'Scegli una compagnia attiva.']);
        if ($agencyId && (!$company || !$agency || (!$sameAgency && $agency->status !== 'active') || !$agency->companies()->whereKey($company->id)->exists())) {
            throw ValidationException::withMessages(['supplier_agency_id' => 'Scegli un’agenzia collegata alla compagnia indicata.']);
        }
        return array_merge($values, ['supplier_company_id' => $company?->id, 'supplier_agency_id' => $agency?->id]);
    }
}
