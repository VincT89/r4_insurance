<?php

use App\Models\{Client, InsurancePolicy, User};
use Database\Seeders\InsuranceDemoSeeder;
use Illuminate\Support\Facades\{Auth, Crypt, DB, Storage};

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Database inatteso.');
$admin = User::where('email','admin@admin.it')->firstOrFail();
if (!$admin->isAdmin() || $admin->status !== 'active') throw new RuntimeException('Amministratore attivo richiesto.');
Auth::setUser($admin);
$report = DB::transaction(function () {
    $clients = Client::orderBy('id')->lockForUpdate()->get();
    if ($clients->count() !== 50 || InsurancePolicy::count() !== 50) throw new RuntimeException('Attesi soltanto i 50 clienti e le 50 polizze appena generati.');
    $policies = InsurancePolicy::orderBy('id')->lockForUpdate()->get();
    $vehicles = DB::table('client_vehicles')->orderBy('id')->lockForUpdate()->get();
    $backup = 'insurance-names-backup-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json.enc';
    $json = json_encode(['clients'=>$clients->toArray(),'policies'=>$policies->toArray(),'vehicles'=>$vehicles->all()], JSON_THROW_ON_ERROR);
    if (!Storage::disk('local')->put($backup, Crypt::encryptString($json)) || !hash_equals(hash('sha256',$json),hash('sha256',Crypt::decryptString(Storage::disk('local')->get($backup))))) throw new RuntimeException('Copia di sicurezza non verificata.');
    foreach ($clients as $client) {
        $renamed = false;
        if (preg_match('/^cliente-dimostrativo-([0-9]{3})$/D', $client->slug, $match)) {
            $index = (int)$match[1]; $suffix = $match[1];
        } else {
            $position = array_search($client->name, InsuranceDemoSeeder::CLIENT_NAMES, true);
            if ($position === false) throw new RuntimeException('Cliente già modificato: nessuna sovrascrittura.');
            $index = $position + 1; $suffix = str_pad((string)$index, 3, '0', STR_PAD_LEFT); $renamed = true;
            if ($client->slug !== InsuranceDemoSeeder::clientIdentity($index)['slug']) throw new RuntimeException('Identificativo cliente già modificato.');
        }
        $expectedName = ($index > 35 ? 'Azienda dimostrativa ' : 'Cliente dimostrativo ').$suffix;
        if (!$renamed && $client->name !== $expectedName) throw new RuntimeException('Nome cliente già modificato.');
        $policy = $policies->where('client_id', $client->id)->sole();
        if ($renamed) {
            if ($policy->number !== 'SA-'.$policy->created_at->format('Y').'-'.$suffix) throw new RuntimeException('Numero polizza già modificato.');
            continue;
        }
        if ($policy->number !== 'DEMO-POL-'.$suffix) throw new RuntimeException('Numero polizza già modificato.');
        $client->fill(InsuranceDemoSeeder::clientIdentity($index))->save();
        $policy->fill(['number'=>'SA-'.$policy->created_at->format('Y').'-'.$suffix, 'merit_class_internal'=>$policy->merit_class_internal === 'DEMO-'.$index ? (string)((($index-1)%18)+1) : $policy->merit_class_internal])->save();
        if ($vehicle = $policy->vehicle) {
            if ($vehicle->plate !== 'DEMO'.$suffix || $vehicle->make !== 'Veicolo dimostrativo') throw new RuntimeException('Veicolo già modificato.');
            $vehicle->fill(['plate'=>'GB'.$suffix.'XZ', 'make'=>['Fiat','Ford','Renault','Toyota','Opel'][($index-1)%5]])->save();
        }
    }
    $labels = 0;
    foreach (DB::table('notifications')->where('type', App\Services\DeadlineReminders::TYPE)->get() as $notification) {
        $data = json_decode($notification->data, true, flags: JSON_THROW_ON_ERROR);
        $policy = $policies->firstWhere('id', $data['source_id'] ?? null);
        if (($data['kind'] ?? null) !== 'policy' || !$policy || !str_contains($data['message'] ?? '', 'DEMO-POL-')) continue;
        $client = $clients->firstWhere('id', $policy->client_id);
        $data['message'] = 'Polizza '.$policy->number.' · '.$client->name.' · '.Illuminate\Support\Carbon::parse($data['due_on'])->format('d/m/Y');
        DB::table('notifications')->where('id', $notification->id)->update(['data'=>json_encode($data, JSON_THROW_ON_ERROR)]);
        $labels++;
    }
    return ['clients_renamed'=>$clients->count(), 'policies_renamed'=>$policies->count(), 'notification_labels_updated'=>$labels, 'backup'=>$backup];
});
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
