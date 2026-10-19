<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, InsuranceBranch, InsuranceCatalogAlias, InsurancePolicy, InsuranceSubbranch, User};
use App\Services\{ApprovedInsuranceCatalogMerges, InsuranceCatalog};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApprovedInsuranceCatalogMergesTest extends TestCase
{
    use RefreshDatabase;

    private function loadCatalog(): array
    {
        Storage::fake('local');
        config(['insurance-import.directory' => Storage::disk('local')->path('catalog')]);
        $pairs = [];
        foreach ([
            'RCA' => ['AUTO', 'MOTO', 'RCA'], 'RCAUTO' => ['', 'RCAUTO'],
            'FIDEIUSSIONE' => ['', 'PROVVIS'], 'FIDEJUSSIONI' => [''],
            'GLOBALE FABBRICATI' => ['CONDOMINIO', 'FABBRICATO'], 'GLOBALI FABBRICATI' => [''],
            'VIAGGI' => ['VIAGGI'], 'VIAGGIO' => ['VIAGGIO'],
            'CANE& GATTO' => ['CANE&GATTO'], 'CANE&GATTO' => ['CANE&GATTO'],
            'AUTO' => ['CVT'], '3VIRUS' => ['3VIRUS'],
        ] as $branch => $children) {
            foreach ($children as $child) $pairs[] = ['source_branch' => $branch, 'source_subbranch' => $child, 'branch' => 'Proposta', 'subbranch' => null, 'rows' => 2, 'result' => 'da_confermare', 'reason' => 'Verifica originale'];
        }
        $summary = json_encode(['version' => 1, 'mode' => 'preview_only', 'taxonomy' => $pairs, 'company_agencies' => [], 'issue_counts' => [], 'mapping' => []]);
        Storage::disk('local')->put('catalog/summary.json', $summary);
        Storage::disk('local')->put('catalog/manifest.json', json_encode(['files' => ['summary.json' => hash('sha256', $summary)]]));
        app(InsuranceCatalog::class)->importPreview();
        return $pairs;
    }

    public function test_five_merges_preserve_sources_uncertainties_and_repeat_import_without_policies(): void
    {
        $pairs = $this->loadCatalog();
        $original = file_get_contents(Storage::disk('local')->path('catalog/summary.json'));
        $uncertain = InsuranceBranch::whereIn('name', ['AUTO', '3VIRUS'])->with('subbranches')->orderBy('id')->get()->toArray();
        $subsBefore = InsuranceSubbranch::count();
        $result = app(ApprovedInsuranceCatalogMerges::class)->apply();
        $this->assertCount(5, $result['merged']);
        $this->assertDatabaseCount('insurance_branches', 7);
        $this->assertDatabaseCount('insurance_subbranches', $subsBefore - 4);
        $this->assertSame($uncertain, InsuranceBranch::whereIn('name', ['AUTO', '3VIRUS'])->with('subbranches')->orderBy('id')->get()->toArray());
        $this->assertDatabaseHas('insurance_subbranches', ['name' => 'PROVVIS', 'needs_review' => true]);
        $this->assertSame(['AUTO', 'MOTO'], InsuranceBranch::where('name', 'RC auto')->sole()->subbranches->pluck('name')->all());
        $this->assertDatabaseMissing('insurance_subbranches', ['name' => 'RCA']);
        $this->assertDatabaseMissing('insurance_subbranches', ['name' => 'RCAUTO']);
        foreach ($pairs as $pair) {
            if (in_array($pair['source_branch'], ['AUTO', '3VIRUS'])) continue;
            $alias = InsuranceCatalogAlias::where('source_key', InsuranceCatalogAlias::sourceKey($pair['source_branch'], $pair['source_subbranch']))->sole();
            $this->assertContains($pair, $alias->branch->source_values);
            if ($alias->subbranch) $this->assertSame($alias->insurance_branch_id, $alias->subbranch->insurance_branch_id);
        }
        $this->assertNull(InsuranceCatalogAlias::where('source_key', InsuranceCatalogAlias::sourceKey('RCA', 'RCA'))->sole()->insurance_subbranch_id);
        $this->assertSame(0, app(InsuranceCatalog::class)->importPreview()['branches_created']);
        $this->assertSame(0, app(InsuranceCatalog::class)->importPreview()['subbranches_created']);
        $this->assertCount(5, app(ApprovedInsuranceCatalogMerges::class)->apply()['already_applied']);
        $this->assertDatabaseCount('insurance_policies', 0);
        $this->assertDatabaseCount('clients', 0);
        $this->assertSame($original, file_get_contents(Storage::disk('local')->path('catalog/summary.json')));
    }

    public function test_a_conflict_rolls_back_all_merges_and_aliases(): void
    {
        $this->loadCatalog();
        app(InsuranceCatalog::class)->save(new InsuranceBranch, ['name' => 'Cane e gatto', 'status' => 'active', 'needs_review' => false]);
        $before = InsuranceBranch::with('subbranches')->orderBy('id')->get()->toArray();
        try { app(ApprovedInsuranceCatalogMerges::class)->apply(); $this->fail('Expected conflict'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('catalog', $e->errors()); }
        $this->assertSame($before, InsuranceBranch::with('subbranches')->orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('insurance_catalog_aliases', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'catalog_merged']);
    }

    public function test_existing_policy_links_are_never_reclassified_by_this_catalog_operation(): void
    {
        $this->loadCatalog();
        $branch = InsuranceBranch::where('name', 'RCAUTO')->sole();
        $policy = Client::factory()->create()->policies()->create(['category' => 'RCAUTO', 'insurance_branch_id' => $branch->id, 'company' => 'Demo', 'number' => 'PREEXISTING', 'product' => 'Prodotto originale', 'starts_on' => '2026-01-01', 'expires_on' => '2027-01-01', 'status' => 'review']);
        $before = $policy->fresh()->toArray();
        try { app(ApprovedInsuranceCatalogMerges::class)->apply(); $this->fail('Expected linked-record guard'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('catalog', $e->errors()); }
        $this->assertSame($before, $policy->fresh()->toArray());
        $this->assertDatabaseCount('insurance_branches', 12);
        $this->assertDatabaseCount('insurance_catalog_aliases', 0);
    }

    public function test_approved_alias_targets_cannot_be_deleted_or_moved_but_can_be_deactivated(): void
    {
        $this->loadCatalog();
        app(ApprovedInsuranceCatalogMerges::class)->apply();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $branch = InsuranceBranch::where('name', 'RC auto')->sole();
        $sub = $branch->subbranches->first();
        $this->delete(route('insurance-catalog.destroy', ['subbranch', $sub->id]))->assertSessionHasErrors('catalog');
        $this->patch(route('insurance-catalog.update', ['subbranch', $sub->id]), ['insurance_branch_id' => InsuranceBranch::where('name', 'Fideiussioni')->sole()->id, 'name' => $sub->name, 'status' => 'active', 'needs_review' => 0])->assertSessionHasErrors('name');
        $this->patch(route('insurance-catalog.update', ['branch', $branch->id]), ['name' => $branch->name, 'status' => 'inactive', 'needs_review' => 0])->assertSessionHasNoErrors();
        app(InsuranceCatalog::class)->importPreview();
        $this->assertSame('inactive', $branch->fresh()->status);
    }

    public function test_preview_uses_approved_destinations_and_keeps_product_and_uncertain_sources_separate(): void
    {
        $pairs = $this->loadCatalog();
        app(ApprovedInsuranceCatalogMerges::class)->apply();
        $rows = collect(app(InsuranceCatalog::class)->previewTaxonomy($pairs));
        $car = $rows->first(fn ($row) => $row['source_branch'] === 'RCA' && $row['source_subbranch'] === 'RCA');
        $this->assertSame('RC auto', $car['branch']);
        $this->assertNull($car['subbranch']);
        $this->assertNull($car['product']);
        $this->assertSame('accorpamento_approvato', $car['result']);
        $this->assertSame('da_verificare', $rows->firstWhere('source_subbranch', 'PROVVIS')['result']);
        $this->assertSame('3VIRUS', $rows->firstWhere('source_branch', '3VIRUS')['branch']);
        $this->assertSame('da_verificare', $rows->firstWhere('source_branch', '3VIRUS')['result']);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->get(route('insurance-import.index', ['section' => 'branches']))->assertOk()->assertSee('Nome del prodotto')->assertSee('RC auto')->assertSee('Le polizze non sono state importate');
        $this->assertDatabaseCount('insurance_policies', 0);
    }

    public function test_product_is_independent_from_catalog_selection_and_visible_in_policy_tables(): void
    {
        $this->loadCatalog();
        app(ApprovedInsuranceCatalogMerges::class)->apply();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $client = Client::factory()->create();
        $values = ['category' => 'RC auto', 'subcategory' => 'AUTO', 'product' => '  Prodotto   dimostrativo  ', 'company' => 'Compagnia demo', 'number' => 'PRODOTTO-1', 'starts_on' => '2026-01-01', 'expires_on' => '2027-01-01', 'status' => 'active'];
        $this->post(route('clients.policies.store', $client), ($values) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasNoErrors();
        $policy = InsurancePolicy::sole();
        $this->assertSame('RC auto', $policy->category);
        $this->assertSame('AUTO', $policy->subcategory);
        $this->assertSame('Prodotto dimostrativo', $policy->product);
        $this->get(route('clients.show', [$client, 'tab' => 'policies', 'category' => 'RC auto', 'subcategory' => 'AUTO']))->assertOk()->assertSee('<th>Ramo</th>', false)->assertSee('<th>Sottoramo</th>', false)->assertSee('<th>Nome del prodotto</th>', false)->assertSee('Prodotto dimostrativo');
        $this->get(route('clients.policies.show', [$client, $policy]))->assertOk()->assertSee('Nome del prodotto')->assertSee('Prodotto dimostrativo');
        $this->patch(route('clients.policies.update', [$client, $policy]), array_replace($values, ['policy_revision' => $policy->fresh()->editFingerprint(), 'product' => 'Altro nome commerciale']))->assertSessionHasNoErrors();
        $this->assertSame('AUTO', $policy->fresh()->subcategory);
        $this->assertSame('Altro nome commerciale', $policy->fresh()->product);
        $this->post(route('clients.policies.store', $client), (array_replace($values, ['number' => 'INVALID', 'subcategory' => 'Altro nome commerciale'])) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasErrors('subcategory');
        $this->post(route('clients.policies.store', $client), (array_replace($values, ['number' => 'NO-PRODUCT', 'product' => null, 'subcategory' => null])) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('insurance_policies', ['number' => 'NO-PRODUCT', 'product' => null, 'subcategory' => null]);
    }
}
