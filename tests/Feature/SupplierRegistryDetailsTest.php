<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AuditLog,SupplierAgency,SupplierCompany,User};
use App\Support\{AuditChanges,Iban};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierRegistryDetailsTest extends TestCase
{
    use RefreshDatabase;

    private const IT_IBAN = 'IT60X0542811101000000123456';
    private const DE_IBAN = 'DE89370400440532013000';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']));
    }

    private function details(): array
    {
        return ['name'=>'Compagnia dimostrativa','company_name'=>'Ragione sociale dimostrativa','vat_number'=>'01234567897','tax_code'=>'12345678903',
            'email'=>'PREVENTIVI@example.test','pec'=>'PEC@example.test','phone'=>'0600000000','mobile'=>'3300000000',
            'reference_person'=>'Referente dimostrativo','reference_role'=>'Amministrazione','reference_email'=>'REFERENTE@example.test','reference_phone'=>'0600000001',
            'address'=>'Indirizzo dimostrativo','city'=>'Roma','province'=>'rm','postal_code'=>'00100','country'=>'Italia','status'=>'active',
            'iban'=>'it60 x054 2811 1010 0000 0123 456','account_holder'=>'Intestatario dimostrativo','bank_name'=>'Banca dimostrativa','bic'=>'abcd it mm xxx','billing_email'=>'AMMINISTRAZIONE@example.test','sdi_code'=>'abc1234'];
    }

    public function test_company_registry_saves_full_details_encrypted_iban_and_searches_fiscal_fields(): void
    {
        $this->post(route('supplier-companies.store'),$this->details())->assertSessionHasNoErrors();
        $company=SupplierCompany::sole();
        $this->assertSame(self::IT_IBAN,$company->iban);
        $this->assertSame('ABCDITMMXXX',$company->bic);
        $this->assertSame('amministrazione@example.test',$company->billing_email);
        $this->assertSame('pec@example.test',$company->pec);
        $this->assertSame('RM',$company->province);
        $this->assertSame('Ragione sociale dimostrativa',$company->company_name);
        $this->assertSame('ABC1234',$company->sdi_code);
        $this->assertStringNotContainsString(self::IT_IBAN,DB::table('supplier_companies')->where('id',$company->id)->value('iban'));
        $this->assertArrayNotHasKey('iban',$company->toArray());
        $this->get(route('supplier-companies.show',$company))->assertOk()->assertSee('IT60 X054 2811 1010 0000 0123 456')->assertSee('Intestatario dimostrativo');
        $this->get(route('supplier-companies.edit',$company))->assertOk()->assertSee(self::IT_IBAN);
        foreach(['01234567897','12345678903','Ragione sociale dimostrativa'] as $search) $this->get(route('supplier-companies.index',['search'=>$search]))->assertOk()->assertViewHas('companies',fn($rows)=>$rows->total()===1);
    }

    public function test_operator_updates_agency_bank_details_and_admin_audit_masks_complete_coordinates(): void
    {
        $company=SupplierCompany::create(['name'=>'Compagnia di prova']);
        $operator=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->actingAs($operator);
        $data=$this->details()+['supplier_company_ids'=>[$company->id]];
        $data['name']='Agenzia dimostrativa';
        $this->post(route('suppliers.store'),$data)->assertSessionHasNoErrors();
        $agency=SupplierAgency::sole();
        $this->assertSame(self::IT_IBAN,$agency->iban);
        $this->assertArrayNotHasKey('iban',$agency->toArray());
        $this->patch(route('suppliers.update',$agency),array_replace($data,['iban'=>self::DE_IBAN,'account_holder'=>'Nuovo intestatario di prova']))->assertSessionHasNoErrors();
        $agency->refresh();
        $this->assertSame(self::DE_IBAN,$agency->iban);
        $audit=$agency->auditLogs()->where('action','updated')->latest('id')->firstOrFail();
        $this->assertSame($operator->id,$audit->user_id);
        $this->assertSame('IBAN terminante in 3456',$audit->old_values['iban']);
        $this->assertSame('IBAN terminante in 3000',$audit->new_values['iban']);
        $this->assertStringNotContainsString(self::IT_IBAN,AuditLog::all()->toJson());
        $this->assertStringNotContainsString(self::DE_IBAN,AuditLog::all()->toJson());
        $this->assertStringNotContainsString(self::DE_IBAN,DB::table('supplier_agencies')->where('id',$agency->id)->value('iban'));
        $this->assertSame('IBAN terminante in 3000',collect(AuditChanges::rows($audit))->firstWhere('label','IBAN')['after']);
        $this->get(route('suppliers.show',$agency))->assertOk()->assertDontSee('IBAN terminante in');
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']));
        $this->get(route('suppliers.show',$agency))->assertOk()->assertSee('IBAN terminante in 3000');
    }

    public function test_invalid_coordinates_and_missing_holder_are_rejected_without_changing_saved_details(): void
    {
        $this->post(route('supplier-companies.store'),$this->details())->assertSessionHasNoErrors();
        $company=SupplierCompany::sole();
        foreach([
            ['iban'=>'IT60X0542811101000000123457'],['iban'=>'XX60X0542811101000000123456'],
            ['account_holder'=>''],['bic'=>'TOO-SHORT'],['sdi_code'=>'123'],['iban'=>['forged']],
        ] as $invalid) {
            $field=array_key_first($invalid);
            $this->patch(route('supplier-companies.update',$company),array_replace($this->details(),$invalid))->assertSessionHasErrors($field);
            $this->assertSame(self::IT_IBAN,$company->fresh()->iban);
        }
        $this->get(route('supplier-companies.edit',$company))->assertOk();
        $this->assertSame(0,$company->auditLogs()->where('action','updated')->count());
    }

    public function test_optional_fields_do_not_block_minimal_registry_and_partial_update_preserves_bank_details(): void
    {
        $this->post(route('supplier-companies.store'),['name'=>'Compagnia minima'])->assertSessionHasNoErrors();
        $company=SupplierCompany::sole();
        $this->assertNull($company->iban);
        $this->patch(route('supplier-companies.update',$company),$this->details())->assertSessionHasNoErrors();
        $this->patch(route('supplier-companies.update',$company),['name'=>$company->name,'status'=>'active','phone'=>'0200000000'])->assertSessionHasNoErrors();
        $this->assertSame(self::IT_IBAN,$company->fresh()->iban);
        $this->assertSame('Intestatario dimostrativo',$company->fresh()->account_holder);
        $this->patch(route('supplier-companies.update',$company),['name'=>$company->name,'status'=>'active','iban'=>'','account_holder'=>''])->assertSessionHasNoErrors();
        $this->assertNull($company->fresh()->iban);
    }

    public function test_registry_escapes_bank_holder_and_commission_link_keeps_the_company_agency_pair(): void
    {
        $this->post(route('supplier-companies.store'),array_replace($this->details(),['account_holder'=>'<script>alert("test")</script>']))->assertSessionHasNoErrors();
        $company=SupplierCompany::sole();
        $agency=SupplierAgency::create(['name'=>'Agenzia dimostrativa','supplier_company_id'=>$company->id]);
        $page=$this->get(route('supplier-companies.show',$company))->assertOk();
        $page->assertDontSee('<script>alert("test")</script>',false)->assertSee('&lt;script&gt;',false);
        $page->assertSee(e(route('suppliers.show',[$agency,'tab'=>'commissions','company'=>$company->id])),false);
    }

    public function test_iban_checks_national_format_and_checksum_using_official_examples(): void
    {
        foreach([self::IT_IBAN,self::DE_IBAN,'GB29NWBK60161331926819','AT611904300234573201','BE68539007547034'] as $iban) $this->assertTrue(Iban::valid($iban),$iban);
        $this->assertTrue(Iban::valid('it60 x054 2811 1010 0000 0123 456'));
        foreach(['',str_repeat('A',80),'IT60X0542811101000000123457','IT60X054281110100000012345','IT60X05428111010000001234566','IT60XX542811101000000123456','US60X0542811101000000123456','IT60X05428111010000001234-6'] as $iban) $this->assertFalse(Iban::valid($iban),$iban);
    }
}
