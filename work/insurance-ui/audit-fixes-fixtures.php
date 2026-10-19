<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = config('database.connections.sqlite.database');
if (!app()->environment('testing') || config('database.default') !== 'sqlite' || dirname($db) !== __DIR__ || !preg_match('/^ui-\d+\.sqlite$/', basename($db))) throw new RuntimeException('Database isolato richiesto.');
config(['filesystems.disks.attachments.root' => __DIR__.'/attachments']);
$admin = App\Models\User::where('email', 'ui-test@example.test')->firstOrFail();
auth()->login($admin);
$policy = App\Models\InsurancePolicy::orderBy('id')->firstOrFail();
$client = $policy->client;
$service = app(App\Services\InsuranceClaims::class);
$claim = $service->create($admin, $policy, ['context_hash' => $service->context($policy), 'request_key' => (string)Illuminate\Support\Str::uuid(), 'number' => 'SIN-2026-731', 'event_on' => today()->subDay()->toDateString(), 'reported_on' => today()->toDateString(), 'assigned_to' => $admin->id, 'description' => 'Pratica per collaudo isolato della paginazione.'], []);
for ($i = 1; $i <= 511; $i++) $claim->events()->create(['created_by' => $admin->id, 'actor_name' => $admin->name, 'kind' => 'note', 'request_key' => (string)Illuminate\Support\Str::uuid(), 'details' => ['nota' => 'Annotazione '.str_pad((string)$i, 3, '0', STR_PAD_LEFT)], 'created_at' => now()->addSeconds($i)]);
for ($i = 1; $i <= 12; $i++) $claim->activities()->create(['label' => 'Controllo documento '.$i, 'due_on' => today()->addDays(20 + $i), 'status' => 'pending']);
$event = $claim->events()->first();
for ($i = 1; $i <= 12; $i++) {
    $file = Illuminate\Http\UploadedFile::fake()->createWithContent('documento-'.$i.'.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
    app(App\Services\PrivateAttachmentBatch::class)->upload($client, [$file], ['type' => 'document', 'document_category' => 'claim', 'insurance_policy_id' => $policy->id, 'insurance_claim_id' => $claim->id, 'insurance_claim_event_id' => $event->id, 'review_status' => 'received']);
}
$editable = $client->policies()->create(['category' => 'RC auto', 'insurance_branch_id' => $policy->insurance_branch_id, 'company' => $policy->company, 'supplier_company_id' => $policy->supplier_company_id, 'number' => 'SA-2026-990', 'starts_on' => today(), 'expires_on' => today()->addYear(), 'status' => 'active', 'premium' => '600.00']);
$vehicle = $client->vehicles()->create(['plate' => 'XY789ZA']);
echo json_encode(['claim_id' => $claim->id, 'event_count' => 512, 'activities_count' => 12, 'files_count' => 12, 'edit_policy_id' => $editable->id, 'unused_vehicle_id' => $vehicle->id, 'clients_count' => App\Models\Client::count()], JSON_THROW_ON_ERROR);

