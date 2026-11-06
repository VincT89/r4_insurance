<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, SupplierAgency, SupplierCompany, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientVehicleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 30)->setTime(12, 0));
        $this->actingAs(User::factory()->create(['role' => UserRole::Operator]));
        Storage::fake('attachments');
    }

    public function test_each_vehicle_keeps_its_own_class_and_upload_does_not_replace_manual_data(): void
    {
        $client = Client::factory()->create();
        foreach (['AA111AA' => 1, 'BB222BB' => 14] as $plate => $cu) {
            $this->post(route('clients.vehicles.store', $client), [
                'plate' => $plate, 'make' => 'Marca dimostrativa', 'merit_class_cu' => $cu,
                'registration_files' => [UploadedFile::fake()->createWithContent('libretto.pdf', "%PDF-1.4\nTarga ZZ999ZZ, altra persona\n%%EOF")],
            ])->assertSessionHasNoErrors()->assertRedirect();
        }
        $vehicles = $client->vehicles()->orderBy('plate')->get();
        $this->assertSame(['AA111AA', 'BB222BB'], $vehicles->pluck('plate')->all());
        $this->assertSame([1, 14], $vehicles->pluck('merit_class_cu')->all());
        foreach ($vehicles as $vehicle) {
            $document = $vehicle->documents()->sole();
            $this->assertSame($client->id, $document->attachable_id);
            Storage::disk('attachments')->assertExists($document->path);
        }
        $this->get(route('clients.vehicles.create', $client))->assertOk()->assertSee('È obbligatoria solo la targa')->assertDontSee('Leggi dati del libretto');
        $this->get(route('clients.vehicles.edit', [$client, $vehicles[0]]))->assertOk()->assertSee('libretto.pdf');
        $this->get(route('clients.show', [$client, 'tab' => 'vehicles']))->assertOk()->assertSee('CU 14')->assertSee('CU 1');
        $this->get(route('quotes.create', ['client_id' => $client->id]))->assertOk()->assertDontSee('data-registration-reader', false)->assertSee('name="vehicle_files[]"', false);
    }

    public function test_fleet_filters_require_the_same_policy_and_keep_one_row_per_vehicle(): void
    {
        $client = Client::factory()->create(['name' => 'Cliente dimostrativo Alfa']);
        $vehicle = $client->vehicles()->create(['plate' => 'AA111AA', 'merit_class_cu' => 14]);
        $uninsured = $client->vehicles()->create(['plate' => 'BB222BB', 'merit_class_cu' => 1]);
        $company = SupplierCompany::create(['name' => 'Compagnia demo A', 'status' => 'active']);
        $otherCompany = SupplierCompany::create(['name' => 'Compagnia demo B', 'status' => 'active']);
        $agency = SupplierAgency::create(['name' => 'Agenzia demo A', 'status' => 'active']);
        $otherAgency = SupplierAgency::create(['name' => 'Agenzia demo B', 'status' => 'active']);
        foreach ([[$company, $agency, '2026-11-15', 'POL-DEMO-A'], [$otherCompany, $otherAgency, '2027-02-01', 'POL-DEMO-B']] as [$insurer, $intermediary, $end, $number]) {
            $client->policies()->create(['vehicle_id' => $vehicle->id, 'category' => 'RC auto', 'company' => $insurer->name, 'supplier_company_id' => $insurer->id, 'supplier_agency_id' => $intermediary->id, 'number' => $number, 'starts_on' => '2026-01-01', 'expires_on' => $end, 'status' => 'active']);
        }
        $this->get(route('vehicles.index'))->assertOk()->assertViewHas('vehicles', fn ($rows) => $rows->total() === 2)->assertSee($uninsured->plate);
        $this->get(route('vehicles.index', ['search'=>'aa 111']))->assertOk()->assertViewHas('vehicles', fn ($rows) => $rows->modelKeys() === [$vehicle->id]);
        $this->get(route('vehicles.index', ['search'=>'aLFa']))->assertOk()->assertViewHas('vehicles', fn ($rows) => $rows->total() === 2);
        $filters = ['client' => 'alfa', 'plate' => 'aa 111', 'merit_class_cu' => 14, 'company_id' => $company->id, 'agency_id' => $agency->id, 'expires_from' => '2026-11-15', 'expires_to' => '2026-11-15'];
        $this->get(route('vehicles.index', $filters))->assertOk()->assertViewHas('vehicles', fn ($rows) => $rows->total() === 1)->assertSee('AA111AA')->assertSee('POL-DEMO-A')->assertDontSee('POL-DEMO-B')->assertDontSee('BB222BB');
        $this->get(route('vehicles.index', ['company_id' => $company->id, 'agency_id' => $otherAgency->id]))->assertOk()->assertViewHas('vehicles', fn ($rows) => $rows->isEmpty());
        $this->get(route('vehicles.index', ['company_id' => $company->id, 'expires_from' => '2027-01-01']))->assertOk()->assertViewHas('vehicles', fn ($rows) => $rows->isEmpty());
        $this->getJson(route('vehicles.index', ['expires_from' => '2027-01-01', 'expires_to' => '2026-01-01']))->assertUnprocessable()->assertJsonValidationErrors('expires_to');
        $this->getJson(route('vehicles.index', ['merit_class_cu' => 19]))->assertUnprocessable()->assertJsonValidationErrors('merit_class_cu');
    }

    public function test_fleet_pagination_and_merged_clients_respect_registry_visibility(): void
    {
        $client = Client::factory()->create();
        for ($i = 1; $i <= 23; $i++) $client->vehicles()->create(['plate' => sprintf('DEMO%03d', $i), 'merit_class_cu' => 1]);
        $merged = Client::factory()->create(['merged_into_id' => $client->id]);
        $merged->vehicles()->create(['plate' => 'OLD999']);
        $this->get(route('vehicles.index', ['merit_class_cu' => 1, 'page' => 2]))->assertOk()->assertViewHas('vehicles', fn ($rows) => $rows->total() === 23 && $rows->count() === 3)->assertSee('DEMO023')->assertDontSee('OLD999');
        $this->get(route('clients.index'))->assertOk()->assertSee(route('vehicles.index'), false);
        auth()->logout();
        $this->get(route('vehicles.index'))->assertRedirect(route('login'));
    }

    public function test_multiple_identity_documents_keep_individual_metadata_and_private_files(): void
    {
        $client = Client::factory()->create();
        $this->post(route('clients.documents.store', $client), [
            'document_category' => 'identity',
            'files' => [UploadedFile::fake()->image('fronte.png'), UploadedFile::fake()->image('retro.png')],
            'file_details' => [['identity_type' => 'electronic', 'expires_on' => '2030-10-30'], ['identity_type' => 'electronic', 'expires_on' => '2030-10-30']],
        ])->assertSessionHasNoErrors();
        $documents = $client->attachments()->orderBy('id')->get();
        $this->assertSame(['fronte.png', 'retro.png'], $documents->pluck('original_name')->all());
        foreach ($documents as $document) {
            $this->assertSame('identity', $document->document_category);
            $this->assertSame('electronic', $document->identity_type);
            $this->assertSame('2030-10-30', $document->expires_on->toDateString());
            $this->assertNull($document->vehicle_id);
            Storage::disk('attachments')->assertExists($document->path);
        }
        $this->get(route('clients.documents.create', $client))->assertOk()->assertSee('name="files[]" multiple', false);
        $this->post(route('clients.documents.store', $client), [
            'document_category' => 'identity', 'files' => [UploadedFile::fake()->createWithContent('non-consentito.php', '<?php echo 1;')],
        ])->assertSessionHasErrors('files.0');
        $this->assertSame(2, $client->attachments()->count());
    }
}
