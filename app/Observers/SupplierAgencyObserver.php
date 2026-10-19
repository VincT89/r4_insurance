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
        $this->auditLog->log(isset($changes['status']) ? 'status_changed' : 'updated', $supplier,
            array_intersect_key($supplier->getRawOriginal(), $changes), $changes);
    }

    public function deleted(SupplierAgency|SupplierCompany $supplier): void
    {
        $this->auditLog->log('deleted', $supplier, $supplier->getOriginal(), null);
    }
}
