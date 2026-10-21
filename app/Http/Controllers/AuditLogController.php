<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    private const ENTITY_FILTERS = [
        'clients' => ['label' => 'Clienti', 'model' => Client::class],
        'users' => ['label' => 'Utenti', 'model' => User::class],
        'suppliers' => ['label' => 'Agenzie', 'model' => \App\Models\SupplierAgency::class],
        'supplier_companies' => ['label' => 'Compagnie', 'model' => \App\Models\SupplierCompany::class],
        'policies' => ['label' => 'Polizze', 'model' => \App\Models\InsurancePolicy::class],
        'claims' => ['label' => 'Sinistri', 'model' => \App\Models\InsuranceClaim::class],
        'communications' => ['label' => 'Comunicazioni', 'model' => \App\Models\AgencyCommunication::class],
    ];

    private const ACTION_FILTERS = [
        'commissions' => ['label'=>'Provvigioni e liquidazioni', 'actions'=>['commission_created','commission_revised','commission_voided','commission_calculated','commission_base_recorded','commission_accrued','commission_confirmed','commission_settled','commission_reversed','commission_adjusted']],
        'ledger' => ['label' => 'Rate, rinnovi e incassi', 'actions' => ['titles_generated', 'title_created', 'title_cancelled', 'collection_recorded', 'collection_voided', 'policy_renewed']],
        'reversals' => ['label' => 'Annullamenti e storni', 'actions' => ['title_cancelled', 'collection_voided', 'commission_reversed', 'commission_adjusted']],
        'claims' => ['label'=>'Operazioni sui sinistri','actions'=>['claim_created','claim_updated','claim_status','claim_note','claim_activity_added','claim_activity_completed','claim_activity_rescheduled','claim_activity_cancelled']],
        'login' => ['label' => 'Accessi', 'actions' => ['login']],
        'logout' => ['label' => 'Uscite', 'actions' => ['logout']],
        'created' => ['label' => 'Creazioni', 'actions' => ['created']],
        'updated' => ['label' => 'Modifiche', 'actions' => ['updated', 'roles_migrated', 'mandates_changed', 'policy_operational_details', 'policy_import_consolidated']],
        'status_changed' => ['label' => 'Cambi di stato', 'actions' => ['status_changed']],
        'assignments' => ['label' => 'Assegnazioni', 'actions' => ['assigned', 'unassigned']],
        'attachments' => [
            'label' => 'Operazioni sugli allegati',
            'actions' => ['uploaded_attachment', 'deleted_attachment'],
        ],
        'password_reset' => ['label' => 'Reimpostazioni password', 'actions' => ['password_reset']],
        'deleted' => ['label' => 'Eliminazioni', 'actions' => ['deleted']],
        'communications' => ['label' => 'Comunicazioni email', 'actions' => ['agency_email_review','agency_email_sending','agency_email_sent','agency_email_uncertain','agency_email_resolved','agency_email_draft','email_settings_updated']],
    ];

    public function index(Request $request): View
    {
        Gate::authorize('system.admin');

        $filters = $request->validate([
            'log_id' => ['nullable', 'integer', 'min:1'],
            'auditable_type' => ['nullable', Rule::in(array_keys(self::ENTITY_FILTERS))],
            'action' => ['nullable', Rule::in(array_keys(self::ACTION_FILTERS))],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $query = AuditLog::with('user')->latest()->latest('id');
        if ($filters['log_id'] ?? null) $query->whereKey($filters['log_id']);

        if ($action = $filters['action'] ?? null) {
            $query->whereIn('action', self::ACTION_FILTERS[$action]['actions']);
        }

        if ($userId = $filters['user_id'] ?? null) {
            $query->where('user_id', $userId);
        }

        if ($entity = $filters['auditable_type'] ?? null) {
            if ($entity === 'communications') $query->whereIn('action',self::ACTION_FILTERS['communications']['actions']);
            elseif ($entity === 'policies') $query->where(fn ($q) => $q->where('auditable_type', \App\Models\InsurancePolicy::class)->orWhere(fn ($q) => $q->where('auditable_type', Client::class)->where('description', 'like', 'Polizza %')));
            else $query->where('auditable_type', self::ENTITY_FILTERS[$entity]['model']);
        }

        if ($dateFrom = $filters['date_from'] ?? null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $filters['date_to'] ?? null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->paginate(\App\Support\TableListing::perPage(25))->withQueryString();
        $users = User::query()
            ->whereIn('id', AuditLog::query()->select('user_id')->whereNotNull('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('audit-logs.index', [
            'logs' => $logs,
            'users' => $users,
            'entityFilters' => self::ENTITY_FILTERS,
            'actionFilters' => self::ACTION_FILTERS,
            'activeFiltersCount' => collect($filters)->filter(fn ($value) => filled($value))->count(),
        ]);
    }
}
