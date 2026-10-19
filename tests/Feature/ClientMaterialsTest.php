<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientMaterialsTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private User $commercial;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Storage::fake('attachments');
        $this->commercial = User::factory()->create(['role' => UserRole::Operator]);
        $this->client = Client::factory()->create(['commercial_user_id' => $this->commercial->id]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function upload(string $filename = 'logo.svg', string $category = 'logo', string $contents = '<svg xmlns="http://www.w3.org/2000/svg"/>')
    {
        $fixture = UploadedFile::fake()->createWithContent($filename, $contents);

        return $this->post(route('clients.materials.store', $this->client), [
            'category' => $category, 'description' => 'Materiale dimostrativo del cliente',
            'file' => new UploadedFile($fixture->getPathname(), $filename, null, null, true),
        ]);
    }

    public static function designFormats(): array
    {
        return [['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'], ['sorgente.ai', '%!PS-Adobe-3.0'], ['logo.eps', '%!PS-Adobe-3.0 EPSF-3.0'], ['modello.psd', '8BPSdimostrativo'], ['linee-guida.pdf', "%PDF-1.7\nDocumento dimostrativo"]];
    }

    #[DataProvider('designFormats')]
    public function test_design_materials_are_private_downloads_with_their_original_name(string $filename, string $content): void
    {
        $this->upload($filename, 'vector', $content)->assertSessionHasNoErrors()->assertRedirect(route('clients.materials.index', $this->client));
        $material = $this->client->attachments()->sole();
        $this->assertSame('vector', $material->client_material_category);
        $this->assertSame($content, Storage::disk('attachments')->get($material->path));
        $this->get(route('attachments.download', $material))->assertOk()->assertDownload($filename)
            ->assertHeader('Content-Type', 'application/octet-stream')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('clients.materials.index', $this->client))->assertOk()->assertSee($filename)->assertSee('Vettoriali');
    }

    public function test_operators_can_access_all_client_materials_but_cannot_delete_even_their_own_upload(): void
    {
        $this->actingAs($this->commercial);
        $this->upload()->assertSessionHasNoErrors();
        $material = Attachment::sole();
        $this->get(route('attachments.download', $material))->assertOk();
        $this->delete(route('attachments.destroy', $material))->assertForbidden();
        $other = User::factory()->create(['role'=>UserRole::Operator]);
        $this->actingAs($other);
        $this->get(route('clients.materials.index', $this->client))->assertOk();
        $this->upload('another.svg')->assertSessionHasNoErrors();
        $this->get(route('attachments.download', $material))->assertOk();
        $this->delete(route('attachments.destroy', $material))->assertForbidden();
        $legacy = $material->replicate();
        $legacy->client_material_category = null;
        $legacy->save();
        $this->get(route('attachments.download', $legacy))->assertOk();
        Storage::disk('attachments')->assertExists($material->path);
    }

    public function test_admin_and_operator_can_manage_materials_without_legacy_specialist_roles(): void
    {
        foreach ([UserRole::Admin, UserRole::Operator] as $role) {
            $this->actingAs(User::factory()->create(['role'=>$role]));
            $this->get(route('clients.show', $this->client))->assertOk();
            $this->get(route('clients.materials.index', $this->client))->assertOk()->assertSee('Carica materiale');
            $this->upload($role->value.'.svg')->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('attachments', 2);
    }

    public function test_inactive_operators_cannot_read_or_upload_client_materials(): void
    {
        $this->upload();
        $material = Attachment::sole();
        $inactive = User::factory()->create(['role'=>UserRole::Operator, 'status'=>'inactive']);
        $this->actingAs($inactive)->getJson(route('clients.materials.index', $this->client))->assertForbidden();
        $this->actingAs($inactive)->getJson(route('attachments.download', $material))->assertForbidden();
        $this->actingAs($inactive)->postJson(route('clients.materials.store', $this->client), [])->assertForbidden();
    }

    public function test_search_and_category_filter_do_not_include_other_clients_or_general_attachments(): void
    {
        $this->upload('marchio.svg', 'logo');
        $this->upload('sorgente.eps', 'vector', '%!PS-Adobe-3.0 EPSF-3.0');
        $other = Attachment::first()->replicate();
        $other->attachable_id = Client::factory()->create()->id;
        $other->original_name = 'riservato.svg';
        $other->save();
        $this->get(route('clients.materials.index', [$this->client, 'category' => 'logo']))->assertOk()->assertSee('marchio.svg')->assertDontSee('sorgente.eps')->assertDontSee('riservato.svg');
        $this->get(route('clients.materials.index', [$this->client, 'search' => 'sorgente']))->assertOk()->assertSee('sorgente.eps')->assertDontSee('marchio.svg');
        $this->get(route('clients.show', $this->client))->assertOk()->assertDontSee('marchio.svg');
    }

    public function test_disallowed_extensions_invalid_categories_and_oversized_uploads_are_rejected(): void
    {
        $this->upload('script.php', 'logo', '<?php echo "test";')->assertSessionHasErrors('file');
        $this->upload('file.html', 'logo', '<html>test</html>')->assertSessionHasErrors('file');
        $this->upload('fake.jpg', 'logo', '<?php echo "test";')->assertSessionHasErrors('file');
        $this->upload('file.svg', 'invalid')->assertSessionHasErrors('category');
        $this->post(route('clients.materials.store', $this->client), ['category' => 'logo', 'file' => UploadedFile::fake()->create('file.svg', 10241)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('attachments', 0);
        $this->assertCount(0, Storage::disk('attachments')->allFiles());
    }
}
