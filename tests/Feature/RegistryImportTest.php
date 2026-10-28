<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AuditLog, InsuranceBranch, InsuranceSubbranch, SupplierAgency, SupplierCompany, User};
use App\Services\{InsuranceCatalog, RegistryImportReader, RegistryImportStore};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class RegistryImportTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        $this->actingAs($this->admin);
    }

    private function upload(string $content, string $kind = 'company'): string
    {
        $response = $this->post(route('registry-imports.upload'), ['kind' => $kind, 'separator' => 'auto', 'encoding' => 'UTF-8', 'file' => UploadedFile::fake()->createWithContent('anagrafiche.csv', $content)])->assertSessionHasNoErrors()->assertRedirect();
        return basename(dirname($response->headers->get('Location')));
    }

    private function preview(string $id, array $mapping, int $sheet = 0): array
    {
        $this->post(route('registry-imports.prepare', $id), compact('mapping', 'sheet'))->assertSessionHasNoErrors()->assertRedirect(route('registry-imports.preview', $id));
        return app(RegistryImportStore::class)->load($this->admin->id, $id);
    }

    private function confirm(array $batch)
    {
        return $this->post(route('registry-imports.confirm', $batch['id']), ['confirm' => '1', 'hash' => $batch['preview']['hash']]);
    }

    public function test_preview_filters_export_and_confirmation_preserve_existing_and_skip_duplicates(): void
    {
        $existing = SupplierCompany::create(['name' => 'Aurora', 'email' => 'aurora@example.test', 'status' => 'active']);
        $before = $existing->fresh()->getRawOriginal();
        $id = $this->upload("\xEF\xBB\xBFNome compagnia;Email\nAurora;aurora@example.test\nNuova città;nuova@example.test\n nuova   città ;nuova@example.test\nAurora;diversa@example.test\nErrata;email non valida\n");
        $this->get(route('registry-imports.mapping', $id))->assertOk()->assertSee('Associa le colonne')->assertSee('Nome compagnia');
        $batch = $this->preview($id, ['name' => 0, 'email' => 1]);
        $this->assertSame(['new' => 1, 'exists' => 0, 'duplicate' => 1, 'review' => 3], $batch['preview']['counts']);
        $this->assertDatabaseCount('supplier_companies', 1);
        $this->assertSame($before, $existing->fresh()->getRawOriginal());
        $this->get(route('registry-imports.preview', ['id' => $id, 'state' => 'new']))->assertOk()->assertSee('Nuova città')->assertDontSee('email non valida');
        $export = $this->get(route('registry-imports.report', $id))->assertOk()->streamedContent();
        $this->assertStringContainsString('Email', $export);
        $this->assertStringContainsString('nuova@example.test', $export);
        $this->confirm($batch)->assertSessionHasNoErrors()->assertRedirect();
        $this->confirm($batch)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('supplier_companies', 2);
        $this->assertSame($before, $existing->fresh()->getRawOriginal());
        $this->assertSame(1, AuditLog::where('action', 'registry_import_completed')->count());
        $this->get(route('registry-imports.preview', $id))->assertOk()->assertSee('Importazione completata')->assertDontSee('Importa 1 nuove voci');
    }

    public function test_imports_agency_with_multiple_existing_companies_and_preserves_text_identifiers(): void
    {
        $a = SupplierCompany::create(['name' => 'Compagnia A', 'status' => 'active']);
        $b = SupplierCompany::create(['name' => 'Compagnia B', 'status' => 'active']);
        $id = $this->upload("Nome agenzia;Compagnie collegate;Codice agenzia;Partita IVA;CAP\nAgenzia Test;compagnia b|Compagnia A;0001;00123456789;00100\nDa controllare;Compagnia mancante;0002;;\n", 'agency');
        $batch = $this->preview($id, ['name' => 0, 'companies' => 1, 'agency_code' => 2, 'vat_number' => 3, 'postal_code' => 4]);
        $this->assertSame(1, $batch['preview']['counts']['new']);
        $this->assertSame(1, $batch['preview']['counts']['review']);
        $this->confirm($batch)->assertSessionHasNoErrors();
        $agency = SupplierAgency::sole();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $agency->companies->modelKeys());
        $this->assertSame('0001', $agency->agency_code);
        $this->assertSame('00123456789', $agency->vat_number);
        $this->assertSame('00100', $agency->postal_code);
        $id2 = $this->upload("Nome;Compagnie\nAgenzia Test;Compagnia A\n", 'agency');
        $review = $this->preview($id2, ['name' => 0, 'companies' => 1]);
        $this->assertSame(1, $review['preview']['counts']['review']);
        $this->assertDatabaseCount('supplier_agencies', 1);
    }

    public function test_branches_then_subbranches_require_an_existing_unambiguous_parent(): void
    {
        $id = $this->upload("Ramo;Stato\nAbitazione;Attivo\nVeicoli;Inattivo\n", 'branch');
        $batch = $this->preview($id, ['name' => 0, 'status' => 1]);
        $this->confirm($batch)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('insurance_branches', ['name' => 'Abitazione', 'name_key' => InsuranceCatalog::key('Abitazione'), 'status' => 'active']);
        $id = $this->upload("Sottoramo;Ramo;Stato\nIncendio;abitazione;Attivo\nAuto;Veicoli;Attivo\nAuto storiche;Veicoli;Inattivo\nAltro;Inesistente;Attivo\n", 'subbranch');
        $batch = $this->preview($id, ['name' => 0, 'branch' => 1, 'status' => 2]);
        $this->assertSame(['new' => 2, 'exists' => 0, 'duplicate' => 0, 'review' => 2], $batch['preview']['counts']);
        $this->confirm($batch)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('insurance_subbranches', 2);
        $this->assertSame('Abitazione', InsuranceSubbranch::where('name', 'Incendio')->firstOrFail()->branch->name);
    }

    public function test_existing_entries_and_same_file_conflicts_are_never_overwritten_or_chosen_arbitrarily(): void
    {
        SupplierCompany::create(['name' => 'Esistente', 'status' => 'inactive']);
        SupplierCompany::create(['name' => 'Ambigua', 'status' => 'active']);
        SupplierCompany::create(['name' => ' AMBIGUA ', 'status' => 'active']);
        $id = $this->upload("Nome;Email\nEsistente;\nAmbigua;\nConflitto;a@example.test\nConflitto;b@example.test\n");
        $batch = $this->preview($id, ['name' => 0, 'email' => 1]);
        $this->assertSame(['new' => 0, 'exists' => 1, 'duplicate' => 0, 'review' => 3], $batch['preview']['counts']);
        $this->confirm($batch)->assertSessionHasErrors('confirm');
        $this->assertDatabaseCount('supplier_companies', 3);
    }

    public function test_stale_preview_is_blocked_and_recalculation_excludes_a_newly_created_duplicate(): void
    {
        $id = $this->upload("Nome\nUna compagnia\nAltra compagnia\n");
        $batch = $this->preview($id, ['name' => 0]);
        SupplierCompany::create(['name' => 'Una compagnia', 'status' => 'active']);
        $this->confirm($batch)->assertSessionHasErrors('confirm');
        $this->assertDatabaseCount('supplier_companies', 1);
        $batch = $this->preview($id, ['name' => 0]);
        $this->assertSame(1, $batch['preview']['counts']['exists']);
        $this->confirm($batch)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('supplier_companies', 2);
    }

    public function test_administrator_access_ownership_confirmation_and_expiry_are_enforced(): void
    {
        $id = $this->upload("Nome\nRiservata\n");
        $batch = $this->preview($id, ['name' => 0]);
        $this->post(route('registry-imports.confirm', $id), ['hash' => $batch['preview']['hash']])->assertSessionHasErrors('confirm');
        $operator = User::factory()->create(['role' => UserRole::Operator, 'status' => 'active']);
        $this->actingAs($operator)->get(route('registry-imports.index'))->assertForbidden();
        $this->get(route('registry-imports.mapping', $id))->assertForbidden();
        $this->get(route('registry-imports.report', $id))->assertForbidden();
        $this->confirm($batch)->assertForbidden();
        $other = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        $this->actingAs($other)->get(route('registry-imports.preview', $id))->assertNotFound();
        $this->confirm($batch)->assertNotFound();
        $this->actingAs($this->admin);
        $encrypted = Storage::disk('local')->get('registry-imports/'.$this->admin->id.'/'.$id.'.json');
        $this->assertStringNotContainsString('Riservata', $encrypted);
        $this->travel(25)->hours();
        $this->get(route('registry-imports.preview', $id))->assertNotFound();
        $this->confirm($batch)->assertNotFound();
        $this->assertDatabaseCount('supplier_companies', 0);
    }

    public function test_mapping_pagination_and_csv_formula_escaping(): void
    {
        $csv = "Nome;Email\n";
        for ($i = 1; $i <= 30; $i++) $csv .= 'Compagnia '.$i.";\n";
        $csv .= "=HYPERLINK(1);\n";
        $id = $this->upload($csv);
        $this->post(route('registry-imports.prepare', $id), ['sheet' => 0, 'mapping' => ['email' => 1]])->assertSessionHasErrors('mapping.name');
        $this->post(route('registry-imports.prepare', $id), ['sheet' => 0, 'mapping' => ['name' => 0, 'email' => 0]])->assertSessionHasErrors('mapping');
        $batch = $this->preview($id, ['name' => 0]);
        $this->get(route('registry-imports.preview', ['id' => $id, 'page' => 2]))->assertOk()->assertSee('Compagnia 30')->assertDontSee('Compagnia 24');
        $report = $this->get(route('registry-imports.report', $id))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK(1)", $report);
        $this->confirm($batch)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('supplier_companies', 31);
    }

    public function test_discard_removes_only_staging_and_templates_are_empty(): void
    {
        $id = $this->upload("Nome\nDa conservare\n");
        $batch = $this->preview($id, ['name' => 0]);
        $this->confirm($batch)->assertSessionHasNoErrors();
        $this->delete(route('registry-imports.discard', $id))->assertRedirect();
        $this->get(route('registry-imports.preview', $id))->assertNotFound();
        $this->assertDatabaseCount('supplier_companies', 1);
        foreach (['company', 'agency', 'branch', 'subbranch'] as $kind) {
            $text = $this->get(route('registry-imports.template', $kind))->assertOk()->streamedContent();
            $this->assertSame(1, substr_count($text, "\n"));
        }
    }

    private function workbook(array $sheets, string $extra = '', string $shared = ''): UploadedFile
    {
        $path = Storage::disk('local')->path('fixture-'.bin2hex(random_bytes(5)).'.xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $book = '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($sheets as $i => $rows) {
            $n = $i + 1;
            $book .= '<sheet name="Foglio '.$n.'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
            $zip->addFromString('xl/worksheets/sheet'.$n.'.xml', $extra.'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rows.'</sheetData></worksheet>');
        }
        $zip->addFromString('xl/workbook.xml', $book.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels.'</Relationships>');
        if ($shared) $zip->addFromString('xl/sharedStrings.xml', $shared);
        $zip->close();
        return new UploadedFile($path, 'registro.xlsx', null, null, true);
    }

    private function textCell(string $ref, string $text): string
    {
        return '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars($text, ENT_XML1).'</t></is></c>';
    }

    public function test_excel_selects_sheet_reads_shared_strings_and_blocks_formulas_and_numeric_identifiers(): void
    {
        SupplierCompany::create(['name' => 'Compagnia A', 'status' => 'active']);
        $first = '<row r="1">'.$this->textCell('A1', 'Nome').'</row><row r="2">'.$this->textCell('A2', 'Foglio da ignorare').'</row>';
        $second = '<row r="1">'.$this->textCell('A1', 'Nome agenzia').$this->textCell('B1', 'Compagnie').$this->textCell('C1', 'Codice').'</row>';
        $second .= '<row r="2"><c r="A2" t="s"><v>0</v></c>'.$this->textCell('B2', 'Compagnia A').$this->textCell('C2', '0010').'</row>';
        $second .= '<row r="3">'.$this->textCell('A3', 'Numerica').$this->textCell('B3', 'Compagnia A').'<c r="C3"><v>11</v></c></row>';
        $second .= '<row r="4"><c r="A4"><f>1+1</f><v>2</v></c>'.$this->textCell('B4', 'Compagnia A').$this->textCell('C4', '0012').'</row>';
        $file = $this->workbook([$first, $second], shared: '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Agenzia città</t></si></sst>');
        $response = $this->post(route('registry-imports.upload'), ['kind' => 'agency', 'separator' => 'auto', 'encoding' => 'UTF-8', 'file' => $file])->assertSessionHasNoErrors();
        $id = basename(dirname($response->headers->get('Location')));
        $this->get(route('registry-imports.mapping', ['id' => $id, 'sheet' => 1]))->assertOk()->assertSee('Foglio 2')->assertSee('Agenzia città');
        $batch = $this->preview($id, ['name' => 0, 'companies' => 1, 'agency_code' => 2], 1);
        $this->assertSame(['new' => 1, 'exists' => 0, 'duplicate' => 0, 'review' => 2], $batch['preview']['counts']);
        $this->confirm($batch)->assertSessionHasNoErrors();
        $this->assertSame('0010', SupplierAgency::sole()->agency_code);
        $this->assertDatabaseMissing('supplier_agencies', ['name' => 'Foglio da ignorare']);
    }

    public function test_malformed_or_hostile_workbooks_and_excessive_csv_are_rejected_before_staging(): void
    {
        $rows = '<row r="1">'.$this->textCell('A1', 'Nome').'</row><row r="2">'.$this->textCell('A2', 'Test').'</row>';
        $file = $this->workbook([$rows], '<!DOCTYPE worksheet [<!ENTITY x SYSTEM "file:///sensitive">]>');
        $this->post(route('registry-imports.upload'), ['kind' => 'company', 'separator' => 'auto', 'encoding' => 'UTF-8', 'file' => $file])->assertSessionHasErrors('file');
        $file = UploadedFile::fake()->createWithContent('falso.xlsx', 'not a workbook');
        $this->post(route('registry-imports.upload'), ['kind' => 'company', 'separator' => 'auto', 'encoding' => 'UTF-8', 'file' => $file])->assertSessionHasErrors('file');
        $file = UploadedFile::fake()->createWithContent('troppo.csv', "Nome\n".str_repeat("Riga\n", RegistryImportReader::MAX_ROWS + 1));
        $this->post(route('registry-imports.upload'), ['kind' => 'company', 'separator' => 'auto', 'encoding' => 'UTF-8', 'file' => $file])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('supplier_companies', 0);
        $this->assertSame([], app(RegistryImportStore::class)->recent($this->admin->id));
    }

    public function test_csv_supports_quoted_delimiters_linebreaks_and_windows_encoding(): void
    {
        $file = UploadedFile::fake()->createWithContent('contatti.csv', mb_convert_encoding("Nome;Note\nCittà;\"Prima; voce\nseconda riga\"\n", 'Windows-1252', 'UTF-8'));
        $data = app(RegistryImportReader::class)->read($file, 'semicolon', 'Windows-1252');
        $this->assertSame('Città', $data[0]['rows'][1]['cells'][0]['value']);
        $this->assertSame("Prima; voce\nseconda riga", $data[0]['rows'][1]['cells'][1]['value']);
    }
}
