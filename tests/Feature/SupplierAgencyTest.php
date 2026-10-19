<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AuditLog, SupplierAgency, SupplierCompany, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierAgencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_filter_and_delete_an_agency_with_audit(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $company = SupplierCompany::create(['name' => 'Compagnia dimostrativa']);
        $this->get(route('suppliers.index'))->assertOk()->assertSee('Agenzie');
        $this->get(route('suppliers.create'))->assertOk();
        $this->post(route('suppliers.store'), [
            'supplier_company_id' => $company->id,
            'name' => 'Agenzia dimostrativa', 'company_name' => 'Società dimostrativa', 'agency_code' => 'DEMO-01',
            'email' => 'AGENZIA@example.test', 'phone' => '0100000000', 'vat_number' => 'demo-iva',
            'reference_person' => 'Referente dimostrativo', 'city' => 'Roma', 'notes' => 'Nota dimostrativa',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $supplier = SupplierAgency::sole();
        $this->assertSame('agenzia@example.test', $supplier->email);
        $this->assertSame('DEMO-IVA', $supplier->vat_number);
        $this->get(route('suppliers.show', $supplier))->assertOk()->assertSee('Referente dimostrativo')->assertSee('Nota dimostrativa');
        $this->get(route('suppliers.edit', $supplier))->assertOk();
        $this->patch(route('suppliers.update', $supplier), ['name' => $supplier->name, 'supplier_company_id' => $company->id, 'status' => 'inactive', 'phone' => '0200000000'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('supplier_agencies', ['id' => $supplier->id, 'phone' => '0200000000', 'status' => 'inactive']);
        $log = $supplier->auditLogs()->where('action', 'status_changed')->sole();
        $this->assertSame('0100000000', $log->old_values['phone']);
        $this->assertSame('0200000000', $log->new_values['phone']);
        $this->get(route('suppliers.index', ['status' => 'active']))->assertOk()->assertDontSee($supplier->name);
        $this->get(route('suppliers.index', ['status' => 'inactive', 'search' => 'demo-01']))->assertOk()->assertSee($supplier->name);
        $this->delete(route('suppliers.destroy', $supplier))->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseMissing('supplier_agencies', ['id' => $supplier->id]);
        $this->get(route('audit-logs.index', ['auditable_type' => 'suppliers']))->assertOk()->assertSee($supplier->name);
        $this->assertSame(1, AuditLog::where('auditable_type', SupplierAgency::class)->where('action', 'deleted')->count());
    }

    public function test_operator_can_manage_agencies_but_cannot_delete_them(): void
    {
        $company = SupplierCompany::create(['name'=>'Compagnia prova']);
        $supplier = SupplierAgency::create(['name'=>'Agenzia prova']);
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator]));
        $this->get(route('suppliers.index'))->assertOk();
        $this->get(route('suppliers.show',$supplier))->assertOk()->assertSee('Modifica agenzia');
        $this->post(route('suppliers.store'), ['name'=>'Agenzia operativa','supplier_company_id'=>$company->id])->assertSessionHasNoErrors();
        $this->patch(route('suppliers.update',$supplier), ['name'=>'Agenzia aggiornata','status'=>'active','supplier_company_id'=>$company->id])->assertSessionHasNoErrors();
        $this->delete(route('suppliers.destroy',$supplier))->assertForbidden();
        $this->assertSame('Agenzia aggiornata',$supplier->fresh()->name);
    }

    public function test_agency_validation_does_not_create_insurance_policies(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $company = SupplierCompany::create(['name' => 'Compagnia dimostrativa']);
        $this->post(route('suppliers.store'), ['name' => '', 'email' => 'non-email', 'status' => 'other'])->assertSessionHasErrors(['name', 'email', 'status']);
        $this->assertDatabaseCount('supplier_agencies', 0);
        $this->post(route('suppliers.store'), ['name' => 'Agenzia dimostrativa', 'supplier_company_id' => $company->id, 'notes' => '<script>demo</script>'])->assertSessionHasNoErrors();
        $supplier = SupplierAgency::sole();
        $this->get(route('suppliers.show', $supplier))->assertOk()->assertSee('&lt;script&gt;demo&lt;/script&gt;', false)->assertDontSee('<script>demo</script>', false);
        $this->assertFalse($supplier->policies()->exists());
        $this->assertDatabaseCount('insurance_policies', 0);
    }

    public function test_company_hierarchy_can_be_created_browsed_and_filtered(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->get(route('supplier-companies.index'))->assertOk();
        $this->get(route('supplier-companies.create'))->assertOk();
        $this->post(route('supplier-companies.store'), ['name' => 'Compagnia dimostrativa A', 'email' => 'DEMO@example.test', 'notes' => '<script>demo</script>'])->assertSessionHasNoErrors();
        $company = SupplierCompany::sole();
        $this->assertSame('demo@example.test', $company->email);
        $this->get(route('supplier-companies.show', $company))->assertOk()->assertSee('Nuova agenzia')->assertSee('&lt;script&gt;demo&lt;/script&gt;', false);
        $this->get(route('supplier-companies.edit', $company))->assertOk();
        $this->patch(route('supplier-companies.update', $company), ['name' => $company->name, 'status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('supplier_companies', ['id' => $company->id, 'status' => 'inactive']);
        $this->assertSame('inactive', $company->auditLogs()->where('action', 'status_changed')->sole()->new_values['status']);
        $this->post(route('suppliers.store'), ['name' => 'Agenzia principale dimostrativa', 'supplier_company_id' => $company->id])->assertSessionHasNoErrors();
        $agency = SupplierAgency::sole();
        $this->get(route('suppliers.create', ['parent_agency_id' => $agency->id]))->assertOk()->assertSee('Nuova subagenzia')
            ->assertViewHas('supplier', fn ($s) => (int) $s->supplier_company_id === $company->id && (int) $s->parent_agency_id === $agency->id);
        $this->post(route('suppliers.store'), ['name' => 'Subagenzia dimostrativa', 'supplier_company_id' => $company->id, 'parent_agency_id' => $agency->id])->assertSessionHasNoErrors();
        $subagency = SupplierAgency::where('parent_agency_id', $agency->id)->sole();
        $this->get(route('supplier-companies.show', $company))->assertOk()->assertSeeInOrder([$agency->name, $subagency->name])->assertSee('Nuova subagenzia');
        $this->get(route('suppliers.show', $agency))->assertOk()->assertSee($company->name)->assertSee($subagency->name)->assertSee('Nuova subagenzia');
        $this->get(route('suppliers.show', $subagency))->assertOk()->assertSee($agency->name)->assertDontSee('Nuova subagenzia');
        $other = SupplierCompany::create(['name' => 'Compagnia dimostrativa B']);
        $otherAgency = SupplierAgency::create(['name' => 'Altra agenzia dimostrativa', 'supplier_company_id' => $other->id]);
        $this->get(route('suppliers.index', ['supplier_company_id' => $company->id]))->assertOk()->assertSee($agency->name)->assertSee($subagency->name)->assertDontSee($otherAgency->name);
        $this->get(route('suppliers.index', ['search' => $company->name]))->assertOk()->assertSee($agency->name)->assertDontSee($otherAgency->name);
        $this->get(route('supplier-companies.index', ['search' => $company->name]))->assertOk()->assertSee($company->name)->assertDontSee($other->name);
        $this->get(route('audit-logs.index', ['auditable_type' => 'supplier_companies']))->assertOk()->assertSee($company->name);
    }

    public function test_relationships_cannot_cross_companies_form_cycles_or_orphan_subagencies(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $company = SupplierCompany::create(['name' => 'Compagnia dimostrativa A']);
        $other = SupplierCompany::create(['name' => 'Compagnia dimostrativa B']);
        $agency = SupplierAgency::create(['name' => 'Agenzia principale dimostrativa', 'supplier_company_id' => $company->id]);
        $subagency = SupplierAgency::create(['name' => 'Subagenzia dimostrativa', 'supplier_company_id' => $company->id, 'parent_agency_id' => $agency->id]);
        $sibling = SupplierAgency::create(['name' => 'Agenzia alternativa dimostrativa', 'supplier_company_id' => $company->id]);
        $this->post(route('suppliers.store'), ['name' => 'Collegamento vietato', 'supplier_company_id' => $other->id, 'parent_agency_id' => $agency->id])->assertSessionHasErrors('parent_agency_id');
        $this->post(route('suppliers.store'), ['name' => 'Livello vietato', 'supplier_company_id' => $company->id, 'parent_agency_id' => $subagency->id])->assertSessionHasErrors('parent_agency_id');
        foreach ([$agency->id, $subagency->id, $sibling->id] as $parentId) {
            $this->patch(route('suppliers.update', $agency), ['name' => $agency->name, 'status' => 'active', 'supplier_company_id' => $company->id, 'parent_agency_id' => $parentId])->assertSessionHasErrors('parent_agency_id');
        }
        $this->patch(route('suppliers.update', $agency), ['name' => $agency->name, 'status' => 'active', 'supplier_company_id' => $other->id])->assertSessionHasErrors('supplier_company_id');
        $this->patch(route('suppliers.update', $subagency), ['name' => $subagency->name, 'status' => 'active', 'supplier_company_id' => $other->id, 'parent_agency_id' => $agency->id])->assertSessionHasErrors('parent_agency_id');
        $this->from(route('suppliers.show', $agency))->delete(route('suppliers.destroy', $agency))->assertRedirect(route('suppliers.show', $agency))->assertSessionHasErrors('supplier');
        $this->from(route('supplier-companies.show', $company))->delete(route('supplier-companies.destroy', $company))->assertRedirect(route('supplier-companies.show', $company))->assertSessionHasErrors('company');
        $this->assertDatabaseCount('supplier_agencies', 3);
        $this->assertDatabaseCount('supplier_companies', 2);
        $this->assertDatabaseHas('supplier_agencies', ['id' => $agency->id, 'supplier_company_id' => $company->id, 'parent_agency_id' => null]);
        $this->assertDatabaseHas('supplier_agencies', ['id' => $subagency->id, 'supplier_company_id' => $company->id, 'parent_agency_id' => $agency->id]);
        $this->assertSame(0, $agency->auditLogs()->whereIn('action', ['updated', 'deleted'])->count());
    }

    public function test_subagency_can_be_reassigned_explicitly_and_empty_companies_deleted(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $company = SupplierCompany::create(['name' => 'Compagnia dimostrativa A']);
        $other = SupplierCompany::create(['name' => 'Compagnia dimostrativa B']);
        $agency = SupplierAgency::create(['name' => 'Agenzia dimostrativa', 'supplier_company_id' => $company->id]);
        $newParent = SupplierAgency::create(['name' => 'Nuova principale dimostrativa', 'supplier_company_id' => $other->id]);
        $subagency = SupplierAgency::create(['name' => 'Subagenzia dimostrativa', 'supplier_company_id' => $company->id, 'parent_agency_id' => $agency->id]);
        $this->patch(route('suppliers.update', $subagency), ['name' => $subagency->name, 'status' => 'active', 'supplier_company_id' => $other->id, 'parent_agency_id' => $newParent->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('supplier_agencies', ['id' => $subagency->id, 'supplier_company_id' => $other->id, 'parent_agency_id' => $newParent->id]);
        $log = $subagency->auditLogs()->where('action', 'updated')->sole();
        $this->assertSame($company->id, (int) $log->old_values['supplier_company_id']);
        $this->assertSame($other->id, (int) $log->new_values['supplier_company_id']);
        $this->delete(route('suppliers.destroy', $agency))->assertSessionHasNoErrors();
        $this->delete(route('supplier-companies.destroy', $company))->assertSessionHasNoErrors()->assertRedirect(route('supplier-companies.index'));
        $this->assertDatabaseMissing('supplier_companies', ['id' => $company->id]);
        $this->get(route('supplier-companies.show', $other))->assertOk()->assertSee($subagency->name);
        $this->get(route('audit-logs.index', ['auditable_type' => 'supplier_companies', 'action' => 'deleted']))->assertOk()->assertSee($company->name);
    }

    public function test_companies_use_the_same_access_rules_as_agencies(): void
    {
        $company=SupplierCompany::create(['name'=>'Compagnia prova']);
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator]));
        foreach (['supplier-companies.index','supplier-companies.create'] as $route) $this->get(route($route))->assertOk();
        foreach (['supplier-companies.show','supplier-companies.edit'] as $route) $this->get(route($route,$company))->assertOk();
        $this->post(route('supplier-companies.store'), ['name'=>'Compagnia operativa'])->assertSessionHasNoErrors();
        $this->patch(route('supplier-companies.update',$company), ['name'=>'Compagnia aggiornata','status'=>'active'])->assertSessionHasNoErrors();
        $this->delete(route('supplier-companies.destroy',$company))->assertForbidden();
        $this->assertSame('Compagnia aggiornata',$company->fresh()->name);
    }

    public function test_existing_unassigned_agencies_are_preserved_and_new_relationships_validated(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $agency = SupplierAgency::create(['name' => 'Anagrafica preesistente', 'company_name' => 'Ragione sociale preesistente']);
        $this->get(route('supplier-companies.index'))->assertOk()->assertSee('Anagrafiche con compagnia da assegnare');
        $this->get(route('suppliers.index', ['unassigned' => 1]))->assertOk()->assertSee($agency->name);
        $this->get(route('suppliers.show', $agency))->assertOk()->assertSee('Da assegnare');
        $this->get(route('suppliers.edit', $agency))->assertOk();
        $this->post(route('suppliers.store'), ['name' => 'Senza compagnia'])->assertSessionHasErrors('supplier_company_id');
        $this->post(route('suppliers.store'), ['name' => 'Compagnia assente', 'supplier_company_id' => 99999])->assertSessionHasErrors('supplier_company_id');
        $this->post(route('suppliers.store'), ['name' => 'Compagnie multiple', 'supplier_company_id' => [1, 2]])->assertSessionHasErrors('supplier_company_id');
        $this->post(route('supplier-companies.store'), ['name' => '', 'email' => 'non-email', 'status' => 'other'])->assertSessionHasErrors(['name', 'email', 'status']);
        $this->assertDatabaseCount('supplier_companies', 0);
        $company = SupplierCompany::create(['name' => 'Compagnia dimostrativa']);
        $this->patch(route('suppliers.update', $agency), ['name' => $agency->name, 'supplier_company_id' => $company->id, 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('supplier_agencies', ['id' => $agency->id, 'supplier_company_id' => $company->id, 'company_name' => 'Ragione sociale preesistente']);
        $this->assertDatabaseCount('insurance_policies', 0);
        $this->assertFalse($company->policies()->exists());
    }
}
