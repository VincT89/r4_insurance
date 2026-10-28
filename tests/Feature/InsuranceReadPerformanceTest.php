<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, InsuranceBranch, InsuranceTitle, SupplierAgency, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsuranceReadPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,10,21)->setTime(12,0));
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']));
    }

    public function test_deadline_sources_keep_boundaries_options_and_all_export_rows(): void
    {
        $client=Client::factory()->create(['name'=>'Elena Varesi','commercial_user_id'=>auth()->id()]);
        $agency=SupplierAgency::create(['name'=>'Agenzia Valserena']);
        $branch=InsuranceBranch::create(['name'=>'Abitazione','name_key'=>hash('sha256','ABITAZIONE')]);
        $policies=[];
        for ($i=0; $i<28; $i++) $policies[]=$client->policies()->create(['number'=>'SA-'.str_pad((string)$i,4,'0',STR_PAD_LEFT),
            'category'=>'RC auto','company'=>'Compagnia Alba','supplier_agency_id'=>$agency->id,'starts_on'=>'2026-01-01',
            'expires_on'=>$i===27 ? '2026-11-06' : '2026-11-05','status'=>'active','payment_frequency'=>'Semestrale']);
        $p=$policies[0];
        $title=InsuranceTitle::create(['insurance_policy_id'=>$p->id,'kind'=>'quietance','due_on'=>'2026-11-05',
            'coverage_from'=>'2026-11-05','coverage_to'=>'2027-05-05','amount_cents'=>10000,'payment_method'=>'Bonifico a Intermediario','payment_frequency'=>'Semestrale']);
        $document=$client->attachments()->create(['insurance_policy_id'=>$p->id,'type'=>'document','document_category'=>'identity',
            'disk'=>'attachments','path'=>'not-read.pdf','directory'=>'','stored_name'=>'not-read.pdf','original_name'=>'identita.pdf',
            'mime_type'=>'application/pdf','extension'=>'pdf','size'=>1,'expires_on'=>'2026-11-05','uploaded_by'=>auth()->id()]);
        $client->documentRequests()->create(['label'=>'Documento abitazione','insurance_branch_id'=>$branch->id,'document_category'=>'other','due_on'=>'2026-11-05']);
        $filters=['period'=>'15','kind'=>'policy','category'=>'RC auto','company'=>'Compagnia Alba','agency'=>$agency->id,'responsible'=>auth()->id(),'payment_frequency'=>'Semestrale'];
        $first=$this->get(route('deadlines.index',$filters))->assertOk();
        $first->assertViewHas('deadlines',fn($rows)=>$rows->total()===27 && $rows->count()===25)
            ->assertViewHas('categories',fn($rows)=>$rows->all()===['Abitazione','RC auto']);
        $second=$this->get(route('deadlines.index',$filters+['page'=>2]))->assertOk()->viewData('deadlines');
        $ids=array_map(fn($p)=>$p->id,array_slice($policies,0,27));
        $this->assertSame($ids,array_map('intval',array_merge($first->viewData('deadlines')->pluck('source_id')->all(),$second->pluck('source_id')->all())));
        $this->get(route('deadlines.index',$filters+['_table_all'=>1]),['X-Table-Export'=>'1'])
            ->assertViewHas('deadlines',fn($rows)=>$rows->count()===27 && array_map('intval',$rows->pluck('source_id')->all())===$ids);
        foreach (['document'=>$document->id,'title'=>$title->id] as $kind=>$id) {
            $this->get(route('deadlines.index',array_replace($filters,['kind'=>$kind])))
                ->assertViewHas('deadlines',fn($rows)=>$rows->total()===1 && (int)$rows->first()->source_id===$id);
        }
        $this->get(route('deadlines.index',['kind'=>'request','category'=>'Abitazione','period'=>'custom','from'=>'2026-11-05','to'=>'2026-11-05']))
            ->assertViewHas('deadlines',fn($rows)=>$rows->total()===1);
        $this->get(route('deadlines.index',['period'=>'15']))->assertViewHas('deadlines',fn($rows)=>$rows->total()===30);
        $this->get(route('deadlines.index',['period'=>'custom','from'=>'2026-11-06','to'=>'2026-11-06']))->assertViewHas('deadlines',fn($rows)=>$rows->total()===1);
    }

    public function test_dashboard_does_not_request_documents_already_linked_to_the_checklist(): void
    {
        $client = Client::factory()->create();
        $pending = $client->documentRequests()->create(['label'=>'Documento ancora da ricevere','document_category'=>'other']);
        $linked = $client->documentRequests()->create(['label'=>'Documento già collegato','document_category'=>'other']);
        $direct = $client->documentRequests()->create(['label'=>'Documento già caricato','document_category'=>'other']);
        $values = ['type'=>'document','document_category'=>'other','disk'=>'attachments','path'=>'test.pdf','directory'=>'','stored_name'=>'test.pdf','original_name'=>'test.pdf','mime_type'=>'application/pdf','extension'=>'pdf','size'=>1,'uploaded_by'=>auth()->id()];
        $file = $client->attachments()->create($values);
        $linked->linkedDocuments()->attach($file->id,['linked_by'=>auth()->id(),'created_at'=>now()]);
        $client->attachments()->create($values+['document_request_id'=>$direct->id]);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('missingDocuments',fn($rows)=>$rows->modelKeys()===[$pending->id]);
    }

    public function test_dashboard_totals_keep_renewal_origin_and_exclude_merged_clients(): void
    {
        $client=Client::factory()->create();
        $base=['category'=>'RC auto','company'=>'Compagnia Alba','starts_on'=>'2026-01-01','expires_on'=>'2026-10-21','status'=>'active','payment_status'=>'unpaid'];
        $client->policies()->create($base+['number'=>'SA-100']);
        $renewed=$client->policies()->create(array_replace($base,['number'=>'SA-101','status'=>'renewed','starts_on'=>'2026-11-01','expires_on'=>'2027-11-01','payment_status'=>'paid']));
        $renewed->forceFill(['coverage_origin_on'=>'2026-01-01'])->save();
        $client->policies()->create(array_replace($base,['number'=>'SA-102','starts_on'=>'2026-10-22','expires_on'=>'2027-10-22','payment_status'=>null]));
        $client->policies()->create(array_replace($base,['number'=>'SA-103','status'=>'review']));
        $client->policies()->create(array_replace($base,['number'=>'SA-104','expires_on'=>'2026-10-20']));
        $merged=Client::factory()->create(['merged_into_id'=>$client->id]);
        $merged->policies()->create($base+['number'=>'SA-105']);
        $this->assertSame(['clients'=>1,'policies'=>5,'active'=>2,'unpaid'=>3,'review'=>1,'due'=>1],$this->get(route('dashboard'))->assertOk()->viewData('overview'));
        $this->get(route('policies.index',['status'=>'active','_table_all'=>1]),['X-Table-Export'=>'1'])
            ->assertViewHas('policies',fn($rows)=>$rows->total()===2);
    }

    public function test_deadline_options_include_closed_contracts_only_when_another_current_deadline_requires_them(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $base = ['company'=>'Compagnia Alba', 'starts_on'=>'2026-01-01', 'expires_on'=>'2026-11-01', 'status'=>'cancelled'];
        $policy = fn ($name) => $client->policies()->create($base + ['number'=>$name, 'category'=>$name]);
        $paid = $policy('Solo pagato');
        $open = $policy('Rata aperta');
        foreach ([$paid, $open] as $p) InsuranceTitle::create(['insurance_policy_id'=>$p->id, 'kind'=>'quietance',
            'due_on'=>'2026-11-01', 'coverage_from'=>'2026-11-01', 'coverage_to'=>'2027-05-01', 'amount_cents'=>10000,
            'collected_cents'=>$p->is($paid) ? 10000 : 0, 'payment_method'=>'Bonifico a Intermediario', 'payment_frequency'=>'Semestrale']);
        $current = $policy('Documento corrente');
        $superseded = $policy('Documento sostituito');
        $foreign = $policy('Documento altro cliente');
        $attachment = ['type'=>'document', 'document_category'=>'identity', 'disk'=>'attachments', 'path'=>'not-read.pdf',
            'directory'=>'', 'stored_name'=>'not-read.pdf', 'original_name'=>'identita.pdf', 'mime_type'=>'application/pdf',
            'extension'=>'pdf', 'size'=>1, 'expires_on'=>'2026-11-01', 'uploaded_by'=>auth()->id()];
        $client->attachments()->create($attachment + ['insurance_policy_id'=>$current->id]);
        $old = $client->attachments()->create($attachment + ['insurance_policy_id'=>$superseded->id]);
        $client->attachments()->create(array_replace($attachment, ['previous_version_id'=>$old->id, 'expires_on'=>null]));
        $other->attachments()->create($attachment + ['insurance_policy_id'=>$foreign->id]);
        $requested = $policy('Richiesta pendente');
        $client->documentRequests()->create(['label'=>'Documento richiesto', 'insurance_policy_id'=>$requested->id, 'document_category'=>'other', 'due_on'=>'2026-11-01']);
        $fulfilled = $policy('Richiesta soddisfatta');
        $request = $client->documentRequests()->create(['label'=>'Documento ricevuto', 'insurance_policy_id'=>$fulfilled->id, 'document_category'=>'other', 'due_on'=>'2026-11-01']);
        $client->attachments()->create(array_replace($attachment, ['document_request_id'=>$request->id, 'expires_on'=>null]));
        $branch = InsuranceBranch::create(['name'=>'Ramo senza polizza', 'name_key'=>hash('sha256', 'RAMO SENZA POLIZZA')]);
        $client->documentRequests()->create(['label'=>'Documento ramo', 'insurance_branch_id'=>$branch->id, 'document_category'=>'other', 'due_on'=>'2026-11-01']);
        $merged = Client::factory()->create(['merged_into_id'=>$client->id]);
        $merged->policies()->create(array_replace($base, ['number'=>'Accorpato', 'category'=>'Cliente accorpato', 'company'=>'Compagnia nascosta', 'status'=>'active']));
        $response = $this->get(route('deadlines.index', ['period'=>'all']))->assertOk();
        $response->assertViewHas('categories', fn ($rows) => $rows->all() === ['Documento corrente', 'Ramo senza polizza', 'Rata aperta', 'Richiesta pendente'])
            ->assertViewHas('companies', fn ($rows) => $rows->all() === ['Compagnia Alba']);
        $response->assertViewHas('deadlines', fn ($rows) => $rows->total() === 5);
    }
}
