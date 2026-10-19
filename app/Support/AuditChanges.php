<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\{AuditLog, InsuranceCollection};

class AuditChanges
{
    private const LABELS = [
        'name'=>'Nome', 'email'=>'Email', 'role'=>'Ruolo', 'status'=>'Stato', 'number'=>'Numero polizza',
        'insurance_policy_id'=>'Polizza', 'insurance_title_id'=>'Rata', 'insurance_collection_id'=>'Incasso',
        'amount_cents'=>'Importo', 'collected_cents'=>'Totale incassato sulla rata', 'premium'=>'Premio',
        'due_on'=>'Scadenza', 'coverage_from'=>'Copertura dal', 'coverage_to'=>'Copertura al',
        'starts_on'=>'Decorrenza', 'expires_on'=>'Scadenza contratto', 'received_on'=>'Data incasso',
        'payment_method'=>'Metodo di pagamento', 'payment_frequency'=>'Frazionamento', 'recipient'=>'Destinatario',
        'document_type'=>'Tipo documento', 'reference'=>'Riferimento', 'notes'=>'Note',
        'cancelled_at'=>'Annullata il', 'cancellation_reason'=>'Motivo annullamento',
        'voided_at'=>'Stornato il', 'void_reason'=>'Motivo storno',
    ];

    public static function rows(AuditLog $log): array
    {
        $rows = [];
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];
        foreach (self::LABELS as $key => $label) {
            if (!array_key_exists($key, $old) && !array_key_exists($key, $new)) continue;
            $rows[] = ['label'=>$label, 'before'=>self::value($key, $old[$key] ?? null), 'after'=>self::value($key, $new[$key] ?? null)];
        }
        foreach ($new['titles'] ?? [] as $index => $title) {
            $rows[] = ['label'=>'Rata '.($index + 1), 'before'=>'Non presente', 'after'=>self::value('amount_cents', $title['amount_cents'] ?? null).' · scadenza '.self::value('due_on', $title['due_on'] ?? null)];
        }
        return $rows;
    }

    private static function value(string $key, mixed $value): string
    {
        if ($value === null || $value === '') return 'Non presente';
        if (is_array($value) || is_object($value)) return 'Vedi dettaglio operazione';
        if (in_array($key, ['amount_cents', 'collected_cents'], true) && is_numeric($value)) return Money::format((int) $value);
        if ($key === 'premium' && is_numeric($value)) return number_format((float) $value, 2, ',', '.').' €';
        if ($key === 'role') return UserRole::tryFrom((string) $value)?->label() ?? (string) $value;
        if ($key === 'recipient') return InsuranceCollection::RECIPIENTS[$value] ?? (string) $value;
        if ($key === 'document_type') return InsuranceCollection::DOCUMENT_TYPES[$value] ?? (string) $value;
        if ($key === 'status') return ['active'=>'Attivo', 'inactive'=>'Inattivo', 'renewed'=>'Rinnovata'][$value] ?? (string) $value;
        if (preg_match('/(?:_on|_from|_to|_at)$/', $key) && preg_match('/\A(\d{4})-(\d{2})-(\d{2})/', (string) $value, $date)) return $date[3].'/'.$date[2].'/'.$date[1];
        return (string) $value;
    }
}
