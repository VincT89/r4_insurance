<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AgencyCommissionSchedule, InsuranceBranch, InsuranceSubbranch, SupplierAgency, SupplierCompany, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyCommissionTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private SupplierAgency $agency;
    private SupplierCompany $company;
    private InsuranceBranch $branch;
    private InsuranceSubbranch $sub;

    protected function setUp(): void
    {
        parent::setUp(); $this->travelTo(now()->setDate(2026,10,16)->setTime(12,0));
        $this->admin = User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']); $this->actingAs($this->admin);
        $this->company = SupplierCompany::create(['name'=>'Nobis','status'=>'active']);
        $this->agency = SupplierAgency::create(['name'=>'Agenzia Valserena','status'=>'active','supplier_company_id'=>$this->company->id]);
        $this->branch = InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $this->sub = $this->branch->subbranches()->create(['name'=>'Autovetture','name_key'=>hash('sha256','AUTOVETTURE'),'status'=>'active']);
    }

    private function row(array $values=[]): array { return array_replace(['branch_id'=>$this->branch->id,'subbranch_id'=>null,'selected'=>1,'basis'=>'net_premium','issue_rate'=>'12,3456','renewal_rate'=>null],$values); }
    private function data(array $values=[]): array { return array_replace(['form_complete'=>1,'supplier_company_id'=>$this->company->id,'valid_from'=>'2026-10-01','valid_to'=>'2026-12-31','accrual_event'=>null,'entries'=>[$this->row()]],$values); }
    private function create(array $values=[]): AgencyCommissionSchedule
    {
        $response = $this->post(route('suppliers.commissions.store',$this->agency),$this->data($values))->assertSessionHasNoErrors()->assertRedirect();
        return AgencyCommissionSchedule::findOrFail(basename($response->headers->get('Location')));
    }

    public function test_admin_configures_exact_rates_and_bases_without_creating_financial_movements(): void
    {
        $schedule = $this->create(['entries'=>[$this->row(),$this->row(['subbranch_id'=>$this->sub->id,'basis'=>'gross_premium','issue_rate'=>'0','renewal_rate'=>'100'])]]);
        $rows = $schedule->rates()->orderBy('id')->get();
        $this->assertSame(123456,$rows[0]->issue_rate_units); $this->assertNull($rows[0]->renewal_rate_units);
        $this->assertSame(0,$rows[1]->issue_rate_units); $this->assertSame(1000000,$rows[1]->renewal_rate_units);
        $this->assertNull($schedule->accrual_event);
        $this->assertSame('12,3456',\App\Models\AgencyCommissionRate::percent($rows[0]->issue_rate_units,','));
        foreach (['insurance_policies','insurance_titles','insurance_collections'] as $table) $this->assertDatabaseCount($table,0);
        $this->assertDatabaseHas('audit_logs',['action'=>'commission_created','user_id'=>$this->admin->id,'auditable_id'=>$this->agency->id]);
        $this->get(route('suppliers.commissions.show',[$this->agency,$schedule]))->assertOk()->assertSee('12,3456 %')->assertSee('Da definire con il mandante');
    }

    public function test_only_admin_can_write_conditions_while_existing_agency_read_roles_are_preserved(): void
    {
        $schedule = $this->create();
        foreach ([UserRole::Operator] as $role) {
            $this->actingAs(User::factory()->create(['role'=>$role,'status'=>'active']));
            $response = $this->get(route('suppliers.commissions.index',$this->agency));
            $response->assertOk()->assertDontSee('Configura aliquote');
            $this->get(route('suppliers.commissions.create',$this->agency))->assertForbidden();
            $this->get(route('suppliers.commissions.edit',[$this->agency,$schedule]))->assertForbidden();
            $this->post(route('suppliers.commissions.store',$this->agency),$this->data())->assertForbidden();
            $this->patch(route('suppliers.commissions.update',[$this->agency,$schedule]),$this->data(['revision_reason'=>'Tentativo non autorizzato']))->assertForbidden();
            $this->post(route('suppliers.commissions.void',[$this->agency,$schedule]),['confirmed'=>1,'reason'=>'Tentativo non autorizzato'])->assertForbidden();
        }
        $this->assertDatabaseCount('agency_commission_schedules',1);
    }

    public function test_unlinked_companies_crossed_subbranches_and_duplicate_rows_are_rejected(): void
    {
        $other = SupplierCompany::create(['name'=>'Altra compagnia','status'=>'active']);
        $this->post(route('suppliers.commissions.store',$this->agency),$this->data(['supplier_company_id'=>$other->id]))->assertSessionHasErrors('supplier_company_id');
        $branch = InsuranceBranch::create(['name'=>'Infortuni','name_key'=>hash('sha256','INFORTUNI'),'status'=>'active']);
        $this->post(route('suppliers.commissions.store',$this->agency),$this->data(['entries'=>[$this->row(['branch_id'=>$branch->id,'subbranch_id'=>$this->sub->id])]]))->assertSessionHasErrors('entries.0.selected');
        $this->post(route('suppliers.commissions.store',$this->agency),$this->data(['entries'=>[$this->row(),$this->row()]]))->assertSessionHasErrors('entries');
        $this->assertDatabaseCount('agency_commission_schedules',0);
    }

    public function test_invalid_percentages_unknown_bases_and_truncated_forms_are_rejected(): void
    {
        foreach (['-1','100.0001','1.23456','1e2','abc'] as $value) $this->post(route('suppliers.commissions.store',$this->agency),$this->data(['entries'=>[$this->row(['issue_rate'=>$value])]]))->assertSessionHasErrors('entries.0.issue_rate');
        $this->post(route('suppliers.commissions.store',$this->agency),$this->data(['entries'=>[$this->row(['basis'=>'other'])]]))->assertSessionHasErrors('entries.0.basis_note');
        $this->post(route('suppliers.commissions.store',$this->agency),$this->data(['entries'=>[$this->row(['basis'=>'untrusted'])]]))->assertSessionHasErrors('entries.0.basis');
        $this->post(route('suppliers.commissions.store',$this->agency),$this->data(['form_complete'=>null]))->assertSessionHasErrors('form_complete');
        $this->post(route('suppliers.commissions.store',$this->agency),$this->data(['entries'=>[$this->row(['selected'=>0])]]))->assertSessionHasErrors('entries');
        $this->assertDatabaseCount('agency_commission_rates',0);
    }

    public function test_overlaps_include_boundaries_but_different_scopes_and_companies_are_allowed(): void
    {
        $this->create();
        $this->post(route('suppliers.commissions.store',$this->agency),$this->data())->assertSessionHasErrors('valid_from');
        $this->post(route('suppliers.commissions.store',$this->agency),$this->data(['valid_from'=>'2026-12-31','valid_to'=>null]))->assertSessionHasErrors('valid_from');
        $this->create(['valid_from'=>'2027-01-01','valid_to'=>null]);
        $this->create(['entries'=>[$this->row(['subbranch_id'=>$this->sub->id])]]);
        $other = SupplierCompany::create(['name'=>'Europe Assistance','status'=>'active']); $this->agency->companies()->attach($other->id);
        $this->create(['supplier_company_id'=>$other->id]);
        $this->assertDatabaseCount('agency_commission_schedules',4);
    }

    public function test_revision_is_atomic_keeps_previous_rates_and_rejects_stale_writes(): void
    {
        $original = $this->create();
        $this->patch(route('suppliers.commissions.update',[$this->agency,$original]),$this->data())->assertSessionHasErrors('revision_reason');
        $data = $this->data(['revision_reason'=>'Nuove condizioni comunicate nel mandato','entries'=>[$this->row(['issue_rate'=>'15','renewal_rate'=>'8'])]]);
        $response = $this->patch(route('suppliers.commissions.update',[$this->agency,$original]),$data)->assertSessionHasNoErrors();
        $next = AgencyCommissionSchedule::findOrFail(basename($response->headers->get('Location')));
        $this->assertSame($original->id,$next->replaces_id); $this->assertNotNull($original->fresh()->superseded_at);
        $this->assertSame(123456,$original->rates()->sole()->issue_rate_units); $this->assertSame(150000,$next->rates()->sole()->issue_rate_units);
        $this->patch(route('suppliers.commissions.update',[$this->agency,$original]),$data)->assertSessionHasErrors('commission');
        $this->get(route('suppliers.commissions.edit',[$this->agency,$original]))->assertStatus(409);
        $this->get(route('suppliers.commissions.show',[$this->agency,$next]))->assertOk()->assertSee('Versione precedente');
        $this->assertDatabaseCount('agency_commission_schedules',2);
    }

    public function test_conflicting_revision_does_not_replace_the_original(): void
    {
        $original = $this->create(); $this->create(['valid_from'=>'2027-01-01','valid_to'=>null]);
        $this->patch(route('suppliers.commissions.update',[$this->agency,$original]),$this->data(['revision_reason'=>'Prolungamento del periodo di validità','valid_to'=>null]))->assertSessionHasErrors('valid_from');
        $this->assertNull($original->fresh()->superseded_at); $this->assertDatabaseCount('agency_commission_schedules',2);
    }

    public function test_void_preserves_history_and_allows_replacement_after_confirmation(): void
    {
        $schedule = $this->create(); $route = route('suppliers.commissions.void',[$this->agency,$schedule]);
        $this->post($route,['reason'=>'Condizioni registrate sul mandato errato'])->assertSessionHasErrors('confirmed');
        $this->post($route,['confirmed'=>1,'reason'=>'Condizioni registrate sul mandato errato'])->assertSessionHasNoErrors();
        $this->assertNotNull($schedule->fresh()->voided_at); $this->assertSame(1,$schedule->rates()->count());
        $this->post($route,['confirmed'=>1,'reason'=>'Tentativo di secondo annullamento'])->assertSessionHasErrors('commission');
        $this->create(); $this->assertDatabaseCount('agency_commission_schedules',2);
    }

    public function test_historic_conditions_protect_agency_mandate_and_catalog_links(): void
    {
        $schedule = $this->create(['entries'=>[$this->row(['subbranch_id'=>$this->sub->id])]]);
        $this->delete(route('suppliers.destroy',$this->agency))->assertSessionHasErrors('supplier');
        $other = SupplierCompany::create(['name'=>'Europe Assistance','status'=>'active']);
        $this->patch(route('suppliers.update',$this->agency),['name'=>$this->agency->name,'status'=>'active','supplier_company_ids'=>[$other->id]])->assertSessionHasErrors('supplier_company_ids');
        $this->delete(route('supplier-companies.destroy',$this->company))->assertSessionHasErrors('company');
        $this->delete(route('insurance-catalog.destroy',['kind'=>'subbranch','id'=>$this->sub->id]))->assertSessionHasErrors('catalog');
        $this->patch(route('insurance-catalog.update',['kind'=>'subbranch','id'=>$this->sub->id]),['name'=>'Nome diverso','insurance_branch_id'=>$this->branch->id,'status'=>'active','needs_review'=>0])->assertSessionHasErrors('name');
        $this->assertSame($this->sub->id,$schedule->rates()->sole()->insurance_subbranch_id);
    }

    public function test_agency_cannot_access_or_mutate_another_agencys_configuration(): void
    {
        $schedule = $this->create();
        $other = SupplierAgency::create(['name'=>'Agenzia Borgoluce','status'=>'active','supplier_company_id'=>$this->company->id]);
        $this->get(route('suppliers.commissions.show',[$other,$schedule]))->assertNotFound();
        $this->get(route('suppliers.commissions.edit',[$other,$schedule]))->assertNotFound();
        $this->patch(route('suppliers.commissions.update',[$other,$schedule]),$this->data(['revision_reason'=>'Cambio sulla scheda errata']))->assertNotFound();
        $this->post(route('suppliers.commissions.void',[$other,$schedule]),['confirmed'=>1,'reason'=>'Annullamento dalla scheda errata'])->assertNotFound();
    }

    public function test_inactive_catalog_rows_already_in_schedule_are_preserved_in_revisions(): void
    {
        $schedule = $this->create(['entries'=>[$this->row(['subbranch_id'=>$this->sub->id])]]);
        $this->sub->update(['status'=>'inactive']);
        $this->get(route('suppliers.commissions.edit',[$this->agency,$schedule]))->assertOk()->assertSee('inattivo, già configurato');
        $this->patch(route('suppliers.commissions.update',[$this->agency,$schedule]),$this->data(['revision_reason'=>'Correzione del riferimento contrattuale','entries'=>[$this->row(['subbranch_id'=>$this->sub->id])]]))->assertSessionHasNoErrors();
        $this->post(route('suppliers.commissions.store',$this->agency),$this->data(['valid_from'=>'2027-01-01','valid_to'=>null,'entries'=>[$this->row(['subbranch_id'=>$this->sub->id])]]))->assertSessionHasErrors('entries.0.selected');
    }

    public function test_pages_and_filters_render_and_untrusted_old_input_is_safe(): void
    {
        $schedule = $this->create(['notes'=>'<script>alert(1)</script>']);
        $this->get(route('suppliers.commissions.create',$this->agency))->assertOk();
        $this->get(route('suppliers.commissions.edit',[$this->agency,$schedule]))->assertOk();
        $this->get(route('suppliers.commissions.show',[$this->agency,$schedule]))->assertOk()->assertDontSee('<script>alert(1)</script>',false);
        $this->get(route('suppliers.commissions.index',[$this->agency,'company'=>$this->company->id,'branch'=>$this->branch->id,'on'=>'2026-10-01','basis'=>'net_premium','_table_all'=>1]))->assertOk()->assertViewHas('rates',fn($r)=>$r->total()===1);
        $this->get(route('suppliers.commissions.index',[$this->agency,'on'=>'2027-01-01']))->assertOk()->assertViewHas('rates',fn($r)=>$r->isEmpty());
        $this->withSession(['_old_input'=>['notes'=>['invalid'],'valid_from'=>['invalid'],'form_complete'=>1,'entries'=>[['issue_rate'=>['invalid'],'basis'=>['invalid']]]]])->get(route('suppliers.commissions.create',$this->agency))->assertOk();
    }
}
