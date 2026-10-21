<?php

namespace Database\Seeders;

use App\Models\{AgencyCommunication, Client, InsurancePolicy};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

class CapacitySeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('testing') || !preg_match('/^r4_capacity_[a-z0-9_]{8,40}$/D', DB::connection()->getDatabaseName())
            || DB::table('clients')->exists() || DB::table('users')->exists()) {
            throw new \RuntimeException('Seeder riservato a un database di collaudo vuoto.');
        }
        $renderer = new \Dompdf\Dompdf;
        $renderer->loadHtml('<h1>Documento di collaudo</h1><p>Dati sintetici per verificare archivio e ripristino.</p>');
        $renderer->render();
        $pdf = $renderer->output();
        $now = now()->toDateTimeString();
        DB::table('users')->insert([
            'id'=>1, 'name'=>'Responsabile collaudo', 'email'=>'capacity@example.test',
            'password'=>password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost'=>4]),
            'status'=>'active', 'role'=>'admin', 'password_changed_at'=>$now, 'created_at'=>$now, 'updated_at'=>$now,
        ]);
        $branches = ['RC auto', 'Infortuni', 'Abitazione', 'RC persone', 'Incendio'];
        for ($i=1; $i<=5; $i++) {
            DB::table('insurance_branches')->insert(['id'=>$i,'name'=>$branches[$i-1],'name_key'=>hash('sha256',$branches[$i-1]),'created_at'=>$now,'updated_at'=>$now]);
            DB::table('insurance_subbranches')->insert(['id'=>$i,'insurance_branch_id'=>$i,'name'=>'Copertura base','name_key'=>hash('sha256','Copertura base'),'created_at'=>$now,'updated_at'=>$now]);
        }
        for ($i=1; $i<=12; $i++) DB::table('supplier_companies')->insert(['id'=>$i,'name'=>'Compagnia collaudo '.$i,'created_at'=>$now,'updated_at'=>$now]);
        for ($i=1; $i<=20; $i++) {
            DB::table('supplier_agencies')->insert(['id'=>$i,'name'=>'Agenzia collaudo '.$i,'email'=>'agency'.$i.'@example.test','created_at'=>$now,'updated_at'=>$now]);
            for ($j=1; $j<=12; $j++) DB::table('supplier_agency_company')->insert(['supplier_agency_id'=>$i,'supplier_company_id'=>$j]);
        }
        $buffers = [];
        $append = function (string $table, array $row) use (&$buffers) {
            $buffers[$table][] = $row;
            if (count($buffers[$table]) >= 250) { DB::table($table)->insert($buffers[$table]); $buffers[$table]=[]; }
        };
        $flush = function () use (&$buffers) {
            foreach ($buffers as $table=>$rows) if ($rows) { DB::table($table)->insert($rows); $buffers[$table]=[]; }
        };
        $file = function (int $id, int $client, ?int $policy, string $date) use ($pdf, $append) {
            $path = 'capacity/'.intdiv($id,1000).'/'.$id.'.pdf';
            if (!Storage::disk('attachments')->put($path, $pdf."\n% Record ".$id."\n")) throw new \RuntimeException('Documento non salvato.');
            $append('attachments', [
                'id'=>$id,'attachable_type'=>(new Client)->getMorphClass(),'attachable_id'=>$client,'uploaded_by'=>1,
                'insurance_policy_id'=>$policy,'disk'=>'attachments','path'=>$path,'original_name'=>'documento-'.$id.'.pdf',
                'stored_name'=>$id.'.pdf','mime_type'=>'application/pdf','extension'=>'pdf','size'=>strlen($pdf."\n% Record ".$id."\n"),
                'document_category'=>$policy ? 'policy' : 'identity','expires_on'=>$policy ? null : '2030-12-31',
                'created_at'=>$date,'updated_at'=>$date,
            ]);
        };
        for ($i=1; $i<=10000; $i++) {
            $append('clients', ['id'=>$i,'name'=>($i%5===0?'Officine Valserena ':'Andrea Bianchi ').$i,'slug'=>'capacity-client-'.$i,
                'client_type'=>$i%5===0?'company':'person','email'=>'client'.$i.'@example.test','commercial_user_id'=>1,'status'=>'active','created_at'=>$now,'updated_at'=>$now]);
        }
        $flush();
        for ($i=1; $i<=10000; $i++) {
            $append('client_vehicles',['id'=>$i,'client_id'=>$i,'plate'=>'QA'.str_pad((string)$i,5,'0',STR_PAD_LEFT),'created_at'=>$now,'updated_at'=>$now]);
            $file($i,$i,null,$now);
        }
        $flush();
        $policies = [];
        for ($i=1; $i<=40000; $i++) {
            $client=($i-1)%10000+1;
            $start=CarbonImmutable::create(2017+intdiv($i-1,4000),1,1)->addDays(($i*37)%365);
            $end=$start->addYearNoOverflow(); $stamp=$start->setTime(10,0)->toDateTimeString();
            $status=$i%97===0?'cancelled':($end->lt(today())?'expired':'active');
            $branch=($i-1)%5+1; $company=($i-1)%12+1;
            $policies[]=['id'=>$i,'client_id'=>$client,'vehicle_id'=>$branch===1?$client:null,'insurance_branch_id'=>$branch,'insurance_subbranch_id'=>$branch,
                'category'=>$branches[$branch-1],'subcategory'=>'Copertura base','company'=>'Compagnia collaudo '.$company,
                'supplier_company_id'=>$company,'supplier_agency_id'=>($i-1)%20+1,'number'=>sprintf('SA-%d-%06d',$start->year,$i),
                'starts_on'=>$start->toDateString(),'expires_on'=>$end->toDateString(),'premium'=>'840.00',
                'status'=>$status,'payment_status'=>$i%17===0?'unpaid':'paid','payment_frequency'=>$i%2===0?'Semestrale':'Annuale',
                'payment_method'=>'Bonifico a Intermediario','tacit_renewal'=>true,'created_at'=>$stamp,'updated_at'=>$stamp];
            if (count($policies)===250) { DB::table('insurance_policies')->insert($policies); $policies=[]; }
        }
        $titles=[]; $collections=[]; $commissions=[]; $movements=[]; $titleId=0; $collectionId=0; $movementId=0;
        $flushLedger = function () use (&$titles,&$collections,&$commissions,&$movements) {
            foreach (['insurance_titles'=>$titles,'insurance_collections'=>$collections,'insurance_commissions'=>$commissions,'insurance_commission_movements'=>$movements] as $table=>$rows) {
                if ($rows) DB::table($table)->insert($rows);
            }
            $titles=$collections=$commissions=$movements=[];
        };
        for ($i=1; $i<=40000; $i++) {
            $client=($i-1)%10000+1;
            $start=CarbonImmutable::create(2017+intdiv($i-1,4000),1,1)->addDays(($i*37)%365);
            $parts=$i%2===0?2:1; $amount=84000/$parts;
            $stamp=$start->setTime(10,0)->toDateTimeString();
            for ($part=0; $part<$parts; $part++) {
                $titleId++; $from=$start->addMonthsNoOverflow($part*6); $to=$parts===2?$from->addMonthsNoOverflow(6):$from->addYearNoOverflow();
                $cancelled=$i%97===0; $paid=!$cancelled && $i%17!==0 && $from->lte(today());
                $titles[]=['id'=>$titleId,'insurance_policy_id'=>$i,'kind'=>$part?'quietance':'emission',
                    'due_on'=>$from->toDateString(),'coverage_from'=>$from->toDateString(),'coverage_to'=>$to->toDateString(),
                    'amount_cents'=>$amount,'collected_cents'=>$paid?$amount:0,'payment_method'=>'Bonifico a Intermediario',
                    'payment_frequency'=>$parts===2?'Semestrale':'Annuale','cancelled_at'=>$cancelled?$stamp:null,'created_at'=>$stamp,'updated_at'=>$stamp];
                $commission=(int)($amount/10); $settled=$paid && $from->lt(today()->subMonth());
                $commissions[]=['id'=>$titleId,'insurance_title_id'=>$titleId,'base_cents'=>$amount,'expected_cents'=>$commission,
                    'accrued_cents'=>$paid?$commission:0,'settled_cents'=>$settled?$commission:0,'locked_at'=>$stamp,
                    'rule_snapshot'=>json_encode(['basis'=>'gross_premium','rate_units'=>100000,'accrual_event'=>'collection','collection_mode'=>'proportional']),
                    'created_at'=>$stamp,'updated_at'=>$stamp];
                if ($paid) {
                    $collectionId++;
                    $collections[]=['id'=>$collectionId,'insurance_title_id'=>$titleId,'received_on'=>$from->toDateString(),'amount_cents'=>$amount,
                        'payment_method'=>'Bonifico a Intermediario','recipient'=>$i%3===0?'company':'our_agency','document_type'=>'transfer',
                        'idempotency_key'=>(string)Str::uuid(),'request_fingerprint'=>hash('sha256','capacity-'.$titleId),'created_by'=>1,'consultant_id'=>1,
                        'created_at'=>$from->setTime(10,0)->toDateTimeString(),'updated_at'=>$stamp];
                    foreach ($settled?['accrual','settlement']:['accrual'] as $kind) {
                        $movements[]=['id'=>++$movementId,'insurance_commission_id'=>$titleId,'kind'=>$kind,'amount_cents'=>$commission,
                            'effective_on'=>$from->toDateString(),'reason'=>'Movimento sintetico di collaudo','created_by'=>1,'created_at'=>$stamp];
                    }
                }
            }
            if (count($titles)>=200) $flushLedger();
            $file(10000+$i,$client,$i,$stamp);
            $append('audit_logs',['user_id'=>1,'action'=>'created','auditable_type'=>InsurancePolicy::class,'auditable_id'=>$i,'description'=>'Inserimento sintetico di collaudo','created_at'=>$stamp]);
            if ($i%4000===0) { $flush(); echo "Polizze preparate: $i / 40000\n"; }
        }
        $flushLedger(); $flush();
        AgencyCommunication::create(['supplier_agency_id'=>1,'user_id'=>1,'status'=>'review',
            'payload'=>['subject'=>'Verifica cifratura','body'=>'Contenuto sintetico riservato di collaudo','rows'=>[]],
            'fingerprint'=>hash('sha256','capacity'),'review_expires_at'=>now()->addDay()]);
        foreach (['clients','insurance_policies','insurance_titles','insurance_collections','insurance_commissions','insurance_commission_movements','attachments','audit_logs'] as $table) {
            echo $table.': '.DB::table($table)->count()."\n";
        }
    }
}
