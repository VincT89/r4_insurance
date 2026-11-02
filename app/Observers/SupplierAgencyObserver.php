<?php

namespace App\Observers;

use App\Models\{SupplierAgency, SupplierCompany};
use App\Services\AuditLogService;

class SupplierAgencyObserver
{
    public function __construct(private AuditLogService $auditLog) {}

    public function created(SupplierAgency|SupplierCompany $supplier): void
    {
        $this->auditLog->log('created', $supplier, null, $supplier->getAttributes());
    }

    public function updated(SupplierAgency|SupplierCompany $supplier): void
    {
        $changes = $supplier->getChanges();
        $original = array_intersect_key($supplier->getRawOriginal(), $changes);
        if (array_key_exists('iban',$changes)) {
            /** Lo storico identifica la variazione senza duplicare le coordinate complete nei log. */
            $previous = $supplier->getOriginal('iban');
            $original['iban'] = $previous ? 'IBAN terminante in '.substr($previous,-4) : null;
            $changes['iban'] = $supplier->iban ? 'IBAN terminante in '.substr($supplier->iban,-4) : null;
        }
        $this->auditLog->log(isset($changes['status']) ? 'status_changed' : 'updated', $supplier, $original, $changes);
    }

    public function deleted(SupplierAgency|SupplierCompany $supplier): void
    {
        $this->auditLog->log('deleted', $supplier, $supplier->getOriginal(), null);
    }
}
