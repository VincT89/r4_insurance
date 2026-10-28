<?php
namespace App\Services;

use App\Models\{AuditLog, Client, SupplierAgency, SupplierCompany, User};
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    public function log(string $action, Model $auditable, ?array $oldValues = null, ?array $newValues = null, ?string $description = null, ?int $userId = null): ?AuditLog
    {
        $fields = $auditable instanceof Client
            ? ['name', 'company_name', 'commercial_user_id', 'email', 'phone', 'reference_person', 'vat_number', 'tax_code', 'address', 'city', 'postal_code', 'province', 'country', 'country_code', 'billing_email', 'pec', 'sdi_code', 'status', 'notes', 'commercial_notes', 'logo_path', 'activity_description']
            : ['name', 'email', 'role', 'status', 'original_name'];
        if ($auditable instanceof Client) {
            $fields = array_merge($fields, ['client_type', 'title', 'birth_date', 'birth_place', 'mobile', 'fax', 'target', 'reference_email', 'reference_role', 'reference_phone', 'office', 'consultant', 'collaborator', 'operator', 'salient_notes']);
        }
        if ($auditable instanceof SupplierAgency || $auditable instanceof SupplierCompany) $fields = $auditable->getFillable();
        if ($auditable instanceof \App\Models\InsurancePolicy) $fields = $auditable->getFillable();
        if ($auditable instanceof SupplierAgency) $fields[] = 'supplier_company_ids';
        if ($auditable instanceof \App\Models\QuoteRecipientGroup) $fields = ['name','insurance_branch_id','insurance_subbranch_id','cu_from','cu_to','status','company_ids'];
        if ($auditable instanceof \App\Models\Attachment) $fields = ['review_status', 'validated_by', 'validated_at', 'validation_note', 'previous_version_id', 'version_number', 'document_request_id'];
        if ($auditable instanceof \App\Models\InsuranceBranch || $auditable instanceof \App\Models\InsuranceSubbranch) $fields = ['name', 'status', 'needs_review', 'notes', 'insurance_branch_id', 'document_categories'];
        if ($auditable instanceof \App\Models\InsurancePolicyImport) $fields = ['insurance_policy_id', 'status', 'issues', 'source_key', 'payload_hash', 'source_rows', 'resolution_rule', 'compared_fields', 'source_statuses'];
        $oldValues = $oldValues === null ? null : array_intersect_key($oldValues, array_flip($fields));
        $newValues = $newValues === null ? null : array_intersect_key($newValues, array_flip($fields));
        if (in_array($action, ['updated', 'status_changed'], true) && ! $newValues) {
            return null;
        }
        if (in_array($action, ['created', 'deleted'], true)) {
            $snapshot = array_filter(['name' => $auditable->name, 'original_name' => $auditable->original_name]);
            $oldValues = $action === 'deleted' ? $snapshot : null;
            $newValues = $action === 'created' ? $snapshot : null;
        }
        $userId ??= auth()->id();
        $actor = $userId ? (User::find($userId)?->name ?? 'Sistema') : 'Sistema';
        $entity = $auditable instanceof Client ? 'il cliente '.$auditable->name : ($auditable instanceof User ? "l'utente ".$auditable->name : 'il documento');
        if ($auditable instanceof SupplierAgency) $entity = "l'agenzia ".$auditable->name;
        if ($auditable instanceof SupplierCompany) $entity = 'la compagnia '.$auditable->name;
        if ($auditable instanceof \App\Models\InsurancePolicy) $entity = 'la polizza '.$auditable->number;
        $description ??= match ($action) {
            'created' => "$actor ha creato $entity",
            'deleted' => "$actor ha eliminato $entity",
            'login' => "$actor ha effettuato l'accesso",
            'logout' => "$actor ha terminato la sessione",
            'password_reset' => "$actor ha reimpostato la password per $entity",
            default => "$actor ha aggiornato $entity",
        };
        return AuditLog::create([
            'user_id' => $userId, 'action' => $action,
            'auditable_type' => $auditable->getMorphClass(), 'auditable_id' => $auditable->getKey(),
            'old_values' => $oldValues, 'new_values' => $newValues, 'description' => $description, 'created_at' => now(),
        ]);
    }
}
