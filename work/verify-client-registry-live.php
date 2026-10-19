<?php

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Database inatteso.');
config(['session.driver' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync']);
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    $counts = [];
    foreach (['clients', 'client_vehicles', 'insurance_policies', 'attachments', 'supplier_companies', 'supplier_agencies', 'client_contacts', 'client_assets', 'client_interactions', 'client_consents', 'client_relationships', 'client_households', 'client_household_members', 'client_merges'] as $table) $counts[$table] = $db->table($table)->count();
    $fkChecks = [];
    $keys = $db->table('information_schema.KEY_COLUMN_USAGE')->where('TABLE_SCHEMA', 'r4_insurance')->whereNotNull('REFERENCED_TABLE_NAME')->get();
    foreach ($keys as $key) {
        $fkChecks[$key->TABLE_NAME.'.'.$key->COLUMN_NAME] = $db->table($key->TABLE_NAME.' as child')->leftJoin($key->REFERENCED_TABLE_NAME.' as parent', 'parent.'.$key->REFERENCED_COLUMN_NAME, '=', 'child.'.$key->COLUMN_NAME)->whereNotNull('child.'.$key->COLUMN_NAME)->whereNull('parent.'.$key->REFERENCED_COLUMN_NAME)->count();
    }
    $links = [];
    foreach (['vehicle_id' => 'client_vehicles', 'client_asset_id' => 'client_assets'] as $column => $table) $links['policy_'.$column] = $db->table('insurance_policies as p')->join($table.' as v', 'v.id', '=', 'p.'.$column)->whereColumn('p.client_id', '!=', 'v.client_id')->count();
    $links['policy_multiple_assets'] = $db->table('insurance_policies')->whereNotNull('vehicle_id')->whereNotNull('client_asset_id')->count();
    $links['document_client'] = $db->table('attachments as d')->leftJoin('clients as c', 'c.id', '=', 'd.attachable_id')->where('d.attachable_type', App\Models\Client::class)->whereNull('c.id')->count();
    foreach (['insurance_policy_id' => 'insurance_policies', 'vehicle_id' => 'client_vehicles'] as $column => $table) $links['document_'.$column] = $db->table('attachments as d')->join($table.' as target', 'target.id', '=', 'd.'.$column)->where(fn ($q) => $q->where('d.attachable_type', '!=', App\Models\Client::class)->orWhereColumn('d.attachable_id', '!=', 'target.client_id'))->count();
    $links['document_policy_vehicle'] = $db->table('attachments as d')->join('insurance_policies as p', 'p.id', '=', 'd.insurance_policy_id')->whereNotNull('d.vehicle_id')->where(fn ($q) => $q->whereNull('p.vehicle_id')->orWhereColumn('d.vehicle_id', '!=', 'p.vehicle_id'))->count();
    $links['consent_policy'] = $db->table('client_consents as c')->join('insurance_policies as p', 'p.id', '=', 'c.insurance_policy_id')->whereColumn('c.client_id', '!=', 'p.client_id')->count();
    $links['consent_document'] = $db->table('client_consents as c')->join('attachments as d', 'd.id', '=', 'c.attachment_id')->where(fn ($q) => $q->where('d.attachable_type', '!=', App\Models\Client::class)->orWhereColumn('d.attachable_id', '!=', 'c.client_id')->orWhere('d.disk', '!=', 'attachments')->orWhereNull('d.document_category')->orWhereNotNull('d.client_material_category'))->count();
    $links['consent_scope'] = $db->table('client_consents')->where(fn ($q) => $q->where(fn ($r) => $r->where('scope', 'policy')->whereNull('insurance_policy_id'))->orWhere(fn ($r) => $r->where('scope', 'all')->whereNotNull('insurance_policy_id')))->count();
    $links['consent_evidence'] = $db->table('client_consents')->whereNull('attachment_id')->where(fn ($q) => $q->whereNull('evidence_reference')->orWhere('evidence_reference', ''))->count();
    $links['relationship_order'] = $db->table('client_relationships')->whereColumn('client_id', '>=', 'related_client_id')->count();
    $links['household_non_person'] = $db->table('client_household_members as m')->join('clients as c', 'c.id', '=', 'm.client_id')->where(fn ($q) => $q->whereNull('c.client_type')->orWhere('c.client_type', '!=', 'person'))->count();
    $links['supplier_hierarchy'] = $db->table('supplier_agencies as child')->join('supplier_agencies as parent', 'parent.id', '=', 'child.parent_agency_id')->where(fn ($q) => $q->whereColumn('parent.id', 'child.id')->orWhereNotNull('parent.parent_agency_id')->orWhereNull('child.supplier_company_id')->orWhereNull('parent.supplier_company_id')->orWhereColumn('child.supplier_company_id', '!=', 'parent.supplier_company_id'))->count();
    $missingFiles = 0;
    foreach ($db->table('attachments')->where('attachable_type', App\Models\Client::class)->get(['disk', 'path']) as $file) if (!Illuminate\Support\Facades\Storage::disk($file->disk)->exists($file->path)) $missingFiles++;
    $admin = App\Models\User::where('email', 'admin@admin.it')->firstOrFail();
    $backup = json_decode(file_get_contents($root.'/storage/app/private/supplier-hierarchy-20261014/admin-backup.json'), true, flags: JSON_THROW_ON_ERROR);
    $adminUnchanged = true;
    foreach ($backup as $field => $value) if ($admin->getRawOriginal($field) != $value) $adminUnchanged = false;
    $requiredColumns = ['clients' => ['preferred_contact_channel', 'preferred_contact_time', 'contact_preferences', 'normalized_mobile', 'registry_name_key', 'merged_into_id', 'merged_at'], 'insurance_policies' => ['client_asset_id', 'subcategory'], 'client_consents' => ['kind', 'scope', 'insurance_policy_id', 'signer_name', 'signer_role', 'notice_reference', 'attachment_id', 'evidence_reference']];
    $missingColumns = [];
    foreach ($requiredColumns as $table => $required) {
        $actual = $db->table('information_schema.COLUMNS')->where('TABLE_SCHEMA', 'r4_insurance')->where('TABLE_NAME', $table)->pluck('COLUMN_NAME')->all();
        foreach (array_diff($required, $actual) as $column) $missingColumns[] = $table.'.'.$column;
    }
    $paths = ['/clients', '/clients/create', '/supplier-companies', '/suppliers', '/dashboard'];
    foreach (App\Models\Client::current()->orderBy('id')->limit(20)->pluck('id') as $id) {
        foreach (['registry', 'policies', 'vehicles', 'documents', 'deadlines', 'network', 'assets', 'history', 'consents'] as $tab) $paths[] = '/clients/'.$id.'?tab='.$tab;
        foreach (['contacts', 'assets', 'interactions', 'consents'] as $section) $paths[] = '/clients/'.$id.'/records/'.$section.'/create';
        $paths[] = '/clients/'.$id.'/duplicates';
    }
    $pages = []; $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    foreach ($paths as $path) {
        $app['auth']->forgetGuards(); $app['auth']->guard('web')->setUser($admin);
        $pages[$path] = $kernel->handle(Illuminate\Http\Request::create('http://localhost'.$path, 'GET'))->getStatusCode();
    }
    $files = $app['migrator']->getMigrationFiles(database_path('migrations'));
    $pending = array_values(array_diff(array_keys($files), $db->table('migrations')->pluck('migration')->all()));
    $retired = json_decode(file_get_contents($root.'/tests/Fixtures/retired-table-names.json'), true, flags: JSON_THROW_ON_ERROR);
    $remainingRetired = array_values(array_intersect($retired, $db->table('information_schema.TABLES')->where('TABLE_SCHEMA', 'r4_insurance')->pluck('TABLE_NAME')->all()));
    $ok = $adminUnchanged && !array_sum($fkChecks) && !array_sum($links) && !$missingFiles && !$missingColumns && !$pending && !$remainingRetired && !array_filter($pages, fn ($status) => $status !== 200);
    $report = compact('ok', 'counts', 'fkChecks', 'links', 'missingFiles', 'missingColumns', 'adminUnchanged', 'pages', 'pending', 'remainingRetired');
} finally { $db->rollBack(); }
file_put_contents(__DIR__.'/registry-live-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($ok ? 0 : 1);
