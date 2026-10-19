<?php

namespace Database\Seeders;

use App\Models\{Client, InsuranceBranch, InsurancePolicy, SupplierAgency, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InsuranceDemoSeeder extends Seeder
{
    public const CLIENT_NAMES = [
        'Alessandro Varesi', 'Giulia Montelari', 'Lorenzo Bellandi', 'Martina Rinalvesi', 'Matteo Selvanti',
        'Chiara Valdieri', 'Davide Ferrazzi', 'Elena Costarini', 'Simone Ravelli', 'Federica Santelmi',
        'Andrea Colletti', 'Sara Fontanelli', 'Riccardo Laverni', 'Valentina Malpieri', 'Marco Trevisani',
        'Francesca Bellori', 'Stefano Vignali', 'Alessia Rossetti', 'Luca Maravelli', 'Silvia Castaldi',
        'Paolo Orlandini', 'Ilaria Gentilesi', 'Roberto Falchetti', 'Camilla Portanovi', 'Gabriele Venturi',
        'Beatrice Salviati', 'Nicola Ferranti', 'Alice Morellini', 'Enrico Tavolieri', 'Serena Gualdani',
        'Tommaso Lamberti', 'Claudia Balestrieri', 'Filippo Marinelli', 'Marta Rosignoli', 'Daniele Fortunati',
        'Officine Valdoro S.r.l.', 'Falegnameria Collevento S.n.c.', 'Trasporti Rivasole S.r.l.',
        'Pasticceria Fior di Cedro S.a.s.', 'Edilizia Borgoluce S.r.l.', 'Agricola Fontechiara S.r.l.',
        'Ristorante La Quercia Blu S.a.s.', 'Impianti Nordalba S.r.l.', 'Autocarrozzeria Valserena S.n.c.',
        'Cartotecnica Montefiore S.r.l.', 'Logistica Pratolungo S.r.l.', 'Panificio Grano di Luna S.n.c.',
        'Arredi Pietralieve S.r.l.', 'Centro Ottico Bellariva S.a.s.', 'Informatica Altevie S.r.l.',
    ];

    public static function clientIdentity(int $index): array
    {
        $name = self::CLIENT_NAMES[$index - 1] ?? throw new \InvalidArgumentException('Indice cliente non previsto.');
        return ['name' => $name, 'slug' => Str::slug($name), 'company_name' => $index > 35 ? $name : null];
    }

    public function run(): void
    {
        if (Client::exists() || InsurancePolicy::exists()) throw new \RuntimeException('Il seeder richiede clienti e polizze vuoti. Non cancella dati esistenti.');
        $branches = InsuranceBranch::where('status', 'active')->where('needs_review', false)->with(['subbranches' => fn ($q) => $q->where('status', 'active')->where('needs_review', false)->orderBy('id')])->orderBy('id')->get();
        $pairs = SupplierAgency::where('status', 'active')->with(['companies' => fn ($q) => $q->where('supplier_companies.status', 'active')])->orderBy('id')->get()->flatMap(fn ($agency) => $agency->companies->map(fn ($company) => [$agency, $company]));
        $admin = User::where('role', 'admin')->where('status', 'active')->orderBy('id')->first();
        if ($branches->isEmpty() || $pairs->isEmpty() || !$admin) throw new \RuntimeException('Servono rami confermati, mandati agenzia/compagnia attivi e un amministratore.');
        DB::transaction(function () use ($branches, $pairs, $admin) {
            for ($i=1; $i<=50; $i++) {
                $suffix = str_pad((string)$i, 3, '0', STR_PAD_LEFT);
                $companyClient = $i > 35;
                $identity = self::clientIdentity($i);
                $created = $i<=10 ? now()->startOfDay()->addHours(8)->min(now()) : now()->subDays($i);
                $client = Client::create($identity + ['client_type'=>$companyClient?'company':'person', 'email'=>'cliente'.$suffix.'@example.test', 'status'=>'active', 'commercial_user_id'=>$admin->id, 'notes'=>'Dati fittizi per il collaudo. Nessun cliente reale.']);
                $client->forceFill(['created_at'=>$created, 'updated_at'=>$created])->saveQuietly();
                $branch = $branches[($i-1)%$branches->count()];
                $sub = $branch->subbranches->isEmpty()?null:$branch->subbranches[($i-1)%$branch->subbranches->count()];
                [$agency, $company] = $pairs[($i-1)%$pairs->count()];
                $auto = preg_match('/auto|veicol|motocicl/ui', $branch->name.' '.$sub?->name) === 1;
                $vehicle = $auto ? $client->vehicles()->create(['plate'=>'GB'.$suffix.'XZ', 'make'=>['Fiat','Ford','Renault','Toyota','Opel'][($i-1)%5], 'notes'=>'Targa fittizia per il collaudo, non riferita a un veicolo reale.']) : null;
                $status = ['active','active','active','renewed','active','expired','suspended','replaced','cancelled','ceased'][($i-1)%10];
                $expiry = $status==='expired' ? today()->subDays($i) : today()->addDays([7,15,30,45,60][($i-1)%5]);
                $frequency = $i%2===0 ? 'Semestrale' : 'Annuale';
                $premium = 300+$i*10;
                $policy = $client->policies()->create([
                    'number'=>'SA-'.today()->format('Y').'-'.$suffix, 'category'=>$branch->name, 'subcategory'=>$sub?->name, 'insurance_branch_id'=>$branch->id, 'insurance_subbranch_id'=>$sub?->id,
                    'company'=>$company->name, 'supplier_company_id'=>$company->id, 'supplier_agency_id'=>$agency->id, 'vehicle_id'=>$vehicle?->id,
                    'starts_on'=>$expiry->copy()->subYear(), 'expires_on'=>$expiry, 'status'=>$status, 'premium'=>$premium,
                    'payment_frequency'=>$frequency, 'installment_premium'=>$frequency==='Semestrale'?$premium/2:$premium, 'payment_method'=>InsurancePolicy::PAYMENT_METHODS[($i-1)%count(InsurancePolicy::PAYMENT_METHODS)],
                    'payment_status'=>$status==='expired'?'unpaid':null, 'tacit_renewal'=>true, 'ad_horas'=>$i%7===0,
                    'merit_class_cu'=>$auto?(($i-1)%18)+1:null, 'merit_class_internal'=>$auto?(string)((($i-1)%18)+1):null,
                    'notes'=>'Dati fittizi per il collaudo: importi, date e classi di prova. Nessun contratto o incasso reale.',
                ]);
                $policy->forceFill(['created_at'=>$created, 'updated_at'=>$created])->saveQuietly();
            }
        });
    }
}
