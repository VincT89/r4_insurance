<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AuditLog, Client, InsuranceBranch, InsurancePolicy, InsurancePolicyCorrection, SupplierCompany, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InsurancePolicyWorkflowTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private User $operator;
    private Client $client;
    private InsurancePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 16)->setTime(23, 59, 50));
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        $this->operator = User::factory()->create(['role' => UserRole::Operator, 'status' => 'active']);
        $this->client = Client::factory()->create(['commercial_user_id' => $this->operator->id]);
        $branch = InsuranceBranch::create(['name' => 'RC auto', 'name_key' => hash('sha256', 'RC AUTO'), 'status' => 'active']);
        $company = SupplierCompany::create(['name' => 'Nobis', 'status' => 'active']);
        $this->actingAs($this->operator);
        $this->policy = $this->client->policies()->create(['category' => 'RC auto', 'insurance_branch_id' => $branch->id, 'company' => $company->name, 'supplier_company_id' => $company->id, 'number' => 'SA-2026-601', 'starts_on' => '2026-10-16', 'expires_on' => '2027-10-16', 'status' => 'active', 'premium' => '700.00']);
    }

    private function path(string $action): string { return route('clients.policies.'.$action, [$this->client, $this->policy]); }
    private function closeWork(): void { $this->post($this->path('close-work'), ['confirmed' => 1, 'fingerprint' => $this->policy->fresh()->workFingerprint()])->assertSessionHasNoErrors()->assertRedirect(); }
    private function values(): array { return ['policy_revision' => $this->policy->fresh()->editFingerprint(), 'category' => $this->policy->category, 'company' => $this->policy->company, 'number' => $this->policy->number, 'status' => $this->policy->status, 'starts_on' => '2026-10-16', 'expires_on' => '2027-10-16']; }
    private function reportError(): InsurancePolicyCorrection
    {
        $this->post($this->path('corrections.store'), ['request_key' => (string) Str::uuid(), 'message' => 'Il numero di polizza va controllato con il documento ricevuto.'])->assertSessionHasNoErrors();
        return $this->policy->corrections()->latest('id')->firstOrFail();
    }

    public function test_explicit_completion_locks_an_active_policy_at_the_next_local_midnight(): void
    {
        $this->closeWork();
        $closed = $this->policy->fresh();
        $this->assertSame('active', $closed->status);
        $this->assertSame($this->operator->id, $closed->work_closed_by);
        $this->get($this->path('edit'))->assertOk();
        $this->patch($this->path('update'), $this->values() + ['notes' => 'Ultima nota prima di mezzanotte'])->assertSessionHasNoErrors();
        $this->travelTo(now()->addSeconds(10));
        $this->assertSame('2026-10-17 00:00:00', now()->format('Y-m-d H:i:s'));
        $this->get($this->path('edit'))->assertForbidden();
        $this->patch($this->path('update'), $this->values() + ['notes' => 'Modifica non autorizzata'])->assertForbidden();
        $this->get($this->path('show'))->assertOk()->assertDontSee('Modifica polizza')->assertSee('Invia segnalazione');
        $this->assertSame('Ultima nota prima di mezzanotte', $this->policy->fresh()->notes);
    }

    public function test_completion_uses_the_revision_rendered_in_the_actual_policy_page(): void
    {
        $page=$this->get($this->path('show'))->assertOk()->assertSee('Prepara rate e quietanze');
        $this->assertSame(1,preg_match('/name="fingerprint" value="([a-f0-9]{64})"/',$page->getContent(),$match));
        $this->post($this->path('close-work'),['confirmed'=>1,'fingerprint'=>$match[1]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull($this->policy->fresh()->work_closed_at);
        $this->assertSame('active',$this->policy->fresh()->status);
    }

    public function test_contract_status_and_legacy_closure_do_not_complete_work(): void
    {
        $this->policy->forceFill(['status' => 'ceased', 'closed_at' => today()->subDays(2)])->save();
        $this->get($this->path('edit'))->assertOk();
        $this->assertNull($this->policy->fresh()->work_closed_at);
        $this->get($this->path('show'))->assertSee('Chiudi lavorazione');
    }

    public function test_close_requires_confirmation_fresh_data_and_cannot_be_reset_by_repeated_posts(): void
    {
        $fingerprint = $this->policy->fresh()->workFingerprint();
        $this->post($this->path('close-work'), ['fingerprint' => $fingerprint])->assertSessionHasErrors('confirmed');
        $this->policy->update(['notes' => 'Aggiornamento avvenuto durante il controllo']);
        $this->post($this->path('close-work'), ['fingerprint' => $fingerprint, 'confirmed' => 1])->assertSessionHasErrors('workflow');
        $this->assertNull($this->policy->fresh()->work_closed_at);
        $this->closeWork();
        $stamp = $this->policy->fresh()->work_closed_at->toIso8601String();
        $this->travel(2)->days();
        $this->closeWork();
        $this->assertSame($stamp, $this->policy->fresh()->work_closed_at->toIso8601String());
        $this->assertSame(1, AuditLog::where('action', 'policy_work_closed')->count());
        $this->assertFalse($this->policy->fresh()->editableBy($this->operator));
    }

    public function test_forged_completion_fields_cannot_be_submitted_in_the_normal_policy_form(): void
    {
        $this->patch($this->path('update'), $this->values() + ['work_closed_at' => today()->subDay()->toDateString(), 'work_closed_by' => $this->admin->id])->assertSessionHasErrors(['work_closed_at', 'work_closed_by']);
        $this->assertNull($this->policy->fresh()->work_closed_at);
    }

    public function test_admin_correction_requires_reason_and_never_reopens_work(): void
    {
        $this->closeWork();
        $this->travel(1)->days();
        $this->actingAs($this->admin);
        $this->get($this->path('edit'))->assertOk()->assertSee('Motivo della correzione');
        $this->patch($this->path('update'), $this->values() + ['notes' => 'Dato corretto'])->assertSessionHasErrors('correction_reason');
        $this->patch($this->path('update'), $this->values() + ['notes' => 'Dato corretto', 'correction_reason' => 'Corretto dopo verifica del documento della compagnia.'])->assertSessionHasNoErrors();
        $this->assertSame('Dato corretto', $this->policy->fresh()->notes);
        $this->assertNotNull($this->policy->fresh()->work_closed_at);
        $this->assertSame(1, AuditLog::where('action', 'policy_corrected')->count());
        $this->delete($this->path('destroy'))->assertSessionHasErrors('policy');
    }

    public function test_error_reports_work_after_lock_notify_active_admins_and_are_idempotent(): void
    {
        $inactive = User::factory()->create(['role' => UserRole::Admin, 'status' => 'inactive']);
        $this->closeWork();
        $this->travel(1)->days();
        $data = ['request_key' => (string) Str::uuid(), 'message' => '<script>alert(1)</script> Il dato va corretto.'];
        $this->post($this->path('corrections.store'), $data)->assertSessionHasNoErrors();
        $this->post($this->path('corrections.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('insurance_policy_corrections', 1);
        $this->assertSame(1, $this->admin->notifications()->where('type', InsurancePolicyCorrection::NOTIFICATION_TYPE)->count());
        $this->assertSame(0, $inactive->notifications()->count());
        $this->assertNull($this->policy->fresh()->notes);
        $this->get($this->path('show'))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->post($this->path('corrections.store'), array_replace($data, ['message' => 'Modifica del testo precedente non ammessa']))->assertStatus(409);
    }

    public function test_only_admin_can_resolve_and_the_record_and_notification_are_preserved(): void
    {
        $correction = $this->reportError();
        $path = route('clients.policies.corrections.resolve', [$this->client, $this->policy, $correction]);
        $data = ['status' => 'no_change', 'resolution' => 'Verificato il contratto: il numero inserito è corretto.'];
        $this->post($path, $data)->assertForbidden();
        $this->actingAs($this->admin)->post($path, ['status' => 'no_change'])->assertSessionHasErrors('resolution');
        $this->post($path, $data)->assertSessionHasNoErrors();
        $this->post($path, $data)->assertSessionHasNoErrors();
        $this->assertSame('no_change', $correction->fresh()->status);
        $this->assertSame($this->admin->id, $correction->fresh()->resolved_by);
        $this->assertSame(1, $this->operator->notifications()->where('type', InsurancePolicyCorrection::NOTIFICATION_TYPE)->count());
        $this->post($path, array_replace($data, ['resolution' => 'Un esito differente non deve sovrascrivere lo storico']))->assertSessionHasErrors('workflow');
        $this->delete($this->path('destroy'))->assertSessionHasErrors('policy');
    }

    public function test_cross_client_ids_and_inactive_users_cannot_close_or_report_or_resolve(): void
    {
        $other = Client::factory()->create();
        $this->post(route('clients.policies.close-work', [$other, $this->policy]), ['confirmed' => 1, 'fingerprint' => $this->policy->workFingerprint()])->assertNotFound();
        $this->post(route('clients.policies.corrections.store', [$other, $this->policy]), ['request_key' => (string) Str::uuid(), 'message' => 'Accesso al cliente sbagliato'])->assertNotFound();
        $correction = $this->reportError();
        $otherPolicy = $other->policies()->create($this->policy->fresh()->only($this->policy->getFillable()));
        $this->actingAs($this->admin)->post(route('clients.policies.corrections.resolve', [$other, $otherPolicy, $correction]), ['status' => 'no_change', 'resolution' => 'Identificativo non coerente'])->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => UserRole::Operator, 'status'=>'inactive']));
        $this->postJson($this->path('close-work'), ['confirmed' => 1, 'fingerprint' => $this->policy->workFingerprint()])->assertForbidden();
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator, 'status'=>'inactive']))->postJson($this->path('corrections.store'), ['request_key' => (string) Str::uuid(), 'message' => 'Un utente disattivato'])->assertForbidden();
    }

    public function test_client_reassignment_keeps_correction_notifications_visible_to_operators(): void
    {
        $correction = $this->reportError();
        $this->actingAs($this->admin)->post(route('clients.policies.corrections.resolve', [$this->client, $this->policy, $correction]), ['status' => 'no_change', 'resolution' => 'Dati verificati sulla documentazione originale.'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->operator->visibleNotifications()->where('type', InsurancePolicyCorrection::NOTIFICATION_TYPE)->count());
        $this->client->update(['commercial_user_id' => null]);
        $this->assertSame(1, $this->operator->visibleNotifications()->where('type', InsurancePolicyCorrection::NOTIFICATION_TYPE)->count());
        $this->actingAs($this->operator)->get($this->path('show'))->assertOk();
    }
}
