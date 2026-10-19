<?php

namespace App\Domain\Core\Queries;

use App\Models\{Client, InsurancePolicy, SupplierAgency, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PolicyPortfolioQuery
{
    public const STATES = ['active' => 'In vigore', 'renewed' => 'Rinnovate', 'future' => 'Decorrenza futura', 'expired' => 'Scadute', 'suspended' => 'Sospese', 'replaced' => 'Sostituite', 'cancelled' => 'Annullate', 'ceased' => 'Cessate', 'review' => 'Da verificare'];

    public function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(self::STATES))],
            'category' => ['nullable', 'string', 'max:100'], 'subcategory' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'], 'agency' => ['nullable', 'integer', 'min:1'],
            'responsible' => ['nullable', 'integer', 'min:1'],
            'month' => ['nullable', 'date_format:Y-m'],
            'date_basis' => ['nullable', 'in:expires_on,starts_on,created_at'],
            'payment_frequency' => ['nullable', \Illuminate\Validation\Rule::in(InsurancePolicy::PAYMENT_FREQUENCIES)],
            'payment_status' => ['nullable', 'in:paid,unpaid,unspecified'], 'payment_method' => ['nullable', 'string', 'max:150'],
            'period' => ['nullable', 'in:all,7,15,30,45,60,overdue,custom'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
    }

    public function forUser(User $user, array $filters = []): Builder
    {
        $query = InsurancePolicy::whereIn('client_id', Client::current()->visibleTo($user)->select('id'));
        foreach (['category', 'subcategory', 'company', 'payment_method', 'payment_frequency'] as $field) if (filled($filters[$field] ?? null)) $query->where($field, $filters[$field]);
        if (filled($filters['agency'] ?? null)) $query->where('supplier_agency_id', $filters['agency']);
        if (filled($filters['responsible'] ?? null)) $query->whereHas('client', fn ($q) => $q->where('commercial_user_id', $filters['responsible']));
        if (($filters['payment_status'] ?? null) === 'unspecified') $query->whereNull('payment_status');
        elseif (filled($filters['payment_status'] ?? null)) $query->where('payment_status', $filters['payment_status']);
        $today = today()->toDateString();
        match ($filters['status'] ?? null) {
            'active' => $query->whereIn('status', ['active', 'renewed'])->whereRaw("DATE(CASE WHEN status='renewed' THEN COALESCE(coverage_origin_on,starts_on) ELSE starts_on END) <= ?",[$today])->whereDate('expires_on', '>=', $today),
            'future' => $query->whereIn('status', ['active', 'renewed'])->whereRaw("DATE(CASE WHEN status='renewed' THEN COALESCE(coverage_origin_on,starts_on) ELSE starts_on END) > ?",[$today]),
            'expired' => $query->where(fn ($q) => $q->where('status', 'expired')->orWhere(fn ($q) => $q->whereIn('status', ['active', 'renewed'])->where('expires_on', '<', $today))),
            'renewed', 'suspended', 'ceased', 'replaced', 'cancelled', 'review' => $query->where('status', $filters['status']),
            default => null,
        };
        if (filled($filters['month'] ?? null)) {
            $start = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $filters['month']);
            $column = in_array($filters['date_basis'] ?? '', ['expires_on', 'starts_on', 'created_at'], true) ? $filters['date_basis'] : 'expires_on';
            $query->where($column, '>=', $start->toDateString())->where($column, '<', $start->copy()->addMonth()->toDateString());
        }
        $period = $filters['period'] ?? 'all';
        if (ctype_digit($period)) $query->whereDate('expires_on', '>=', $today)->whereDate('expires_on','<=',today()->addDays((int) $period)->toDateString());
        elseif ($period === 'overdue') $query->where('expires_on', '<', $today);
        if ($period === 'custom' || (in_array($period, ['all', null], true) && (filled($filters['from'] ?? null) || filled($filters['to'] ?? null)))) {
            if (filled($filters['from'] ?? null)) $query->whereDate('expires_on', '>=', $filters['from']);
            if (filled($filters['to'] ?? null)) $query->whereDate('expires_on', '<=', $filters['to']);
        }
        if (filled($filters['search'] ?? null)) {
            $term = '%'.trim($filters['search']).'%';
            $query->where(fn ($q) => $q->where('number', 'like', $term)->orWhere('product', 'like', $term)->orWhere('insured_subject', 'like', $term)
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', $term)->orWhere('tax_code', 'like', $term)->orWhere('vat_number', 'like', $term))
                ->orWhereHas('vehicle', fn ($q) => $q->where('plate', 'like', preg_replace('/\s+/', '', $term))));
        }
        return $query;
    }

    public function options(User $user): array
    {
        $all = $this->forUser($user);
        return [
            'categories' => (clone $all)->distinct()->orderBy('category')->pluck('category'),
            'subcategories' => (clone $all)->whereNotNull('subcategory')->distinct()->orderBy('subcategory')->pluck('subcategory'),
            'companies' => (clone $all)->distinct()->orderBy('company')->pluck('company'),
            'agencies' => SupplierAgency::whereIn('id', (clone $all)->select('supplier_agency_id'))->orderBy('name')->get(['id', 'name']),
            'responsibles' => User::whereIn('id', Client::current()->visibleTo($user)->select('commercial_user_id')->whereNotNull('commercial_user_id'))->orderBy('name')->get(['id', 'name']),
            'paymentMethods' => (clone $all)->whereNotNull('payment_method')->distinct()->orderBy('payment_method')->pluck('payment_method'),
        ];
    }
}
