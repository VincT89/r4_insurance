<?php

namespace App\Services;

use App\Models\{InsurancePolicy, InsurancePolicyImport};
use App\Support\Money;
use RuntimeException;

class InsuranceImportReconciliation
{
    public const STATES = ['matching'=>'Dati polizza coincidenti','changed'=>'Dati modificati nel gestionale','rates'=>'Rate da verificare','missing'=>'Collegamento mancante','held'=>'Gruppo in sospeso','not_imported'=>'Non importata','source_changed'=>'Fonte da verificare'];

    private function key(mixed $value): string { return mb_strtoupper(preg_replace('/\s+/u',' ',trim((string)$value))); }

    private function cents(mixed $value): ?int
    {
        if ($value === null || trim((string)$value) === '') return null;
        $value = str_replace(['EUR','€',' '],'',trim((string)$value));
        if (str_contains($value,',')) $value = str_replace(',','.',str_replace('.','',$value));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D',$value) || (float)$value > 9999999999.99) return null;
        return Money::cents($value);
    }

    private function date(mixed $value): ?string
    {
        $value=trim((string)$value);
        foreach (['Y-m-d','d/m/Y'] as $format) {
            $date=\DateTimeImmutable::createFromFormat('!'.$format,$value);
            if ($date && $date->format($format)===$value) return $date->format('Y-m-d');
        }
        return null;
    }

    private function rateIssues(array $sources, InsurancePolicy $policy): array
    {
        $expected=[];$issues=[];
        foreach ($sources as $row) {
            $values=$row['values'];
            $rawDate=$values['Data Rata Attuale']??null;$rawAmount=$values['Premio Rata']??null;
            if (!filled($rawDate) && !filled($rawAmount)) continue;
            $date=$this->date($rawDate);$amount=$this->cents($rawAmount);
            if ($date===null || $amount===null) { $issues[]='Riga '.$row['row'].': data o importo della rata da verificare.';continue; }
            $expected[$date][$amount]=true;
        }
        foreach ($expected as $date=>$amounts) {
            $display=\DateTimeImmutable::createFromFormat('!Y-m-d',$date)->format('d/m/Y');
            if (count($amounts)!==1) { $issues[]='Excel: più importi per la rata del '.$display.'.';continue; }
            $amount=(int)array_key_first($amounts);
            $titles=$policy->titles->filter(fn($title)=>$title->kind!=='adjustment' && $title->direction==='receipt' && $title->due_on->toDateString()===$date);
            if ($titles->count()!==1) $issues[]='Rata del '.$display.': '.($titles->isEmpty()?'non presente nel gestionale.':'più titoli da confrontare.');
            elseif ($titles->first()->amount_cents!==$amount) $issues[]='Rata del '.$display.': Excel '.Money::format($amount).', gestionale '.Money::format($titles->first()->amount_cents).'.';
        }
        return array_values(array_unique($issues));
    }

    public function report(): array
    {
        $preview=app(InsuranceImportPreview::class);
        $summary=$preview->summary() ?? throw new RuntimeException('Nessun lotto Excel disponibile.');
        $candidates=json_decode(file_get_contents($preview->verifiedFile('policies.json')),true,512,JSON_THROW_ON_ERROR);
        $sourceFile=collect($summary['sources']??[])->first(fn($source)=>in_array('Numero Polizza',$source['headers']??[],true))['file']??'File Gestionale';
        $sources=[];$stream=fopen($preview->verifiedFile('source-rows.jsonl'),'r');
        try { while (($line=fgets($stream))!==false) { $row=json_decode($line,true,512,JSON_THROW_ON_ERROR);if($row['source']==='policies')$sources[$row['row']]=$row; } }
        finally { fclose($stream); }
        $entries=InsurancePolicyImport::with(['policy.supplierAgency','policy.titles.collections'])->get(['id','source_key','client_id','insurance_policy_id','status','payload_hash','issues'])->keyBy('source_key');
        $linkedCounts=$entries->whereNotNull('insurance_policy_id')->countBy('insurance_policy_id');
        $rows=[];$counts=array_fill_keys(array_keys(self::STATES),0);$sourceTotal=0;$currentTotal=0;$compared=0;$unavailable=0;
        foreach ($candidates as $candidate) {
            $sourceRows=[];
            foreach ($candidate['source_rows'] as $number) $sourceRows[]=$sources[$number]??throw new RuntimeException('Riga Excel mancante dal lotto.');
            if (!$sourceRows) throw new RuntimeException('Gruppo Excel senza righe sorgente.');
            $entry=$entries->get($candidate['key']);$policy=$entry?->policy;
            $state='not_imported';$differences=[];$rateIssues=[];
            $data=$candidate['data'];$raw=$sourceRows[0]['values'];
            if ($entry) {
                $hash=hash('sha256',json_encode(['source_file'=>$sourceFile,'candidate'=>$candidate,'rows'=>$sourceRows],JSON_THROW_ON_ERROR));
                if (!hash_equals($entry->payload_hash,$hash)) {
                    $state='source_changed';$differences[]=['field'=>'Fonte','source'=>'Lotto Excel attuale','current'=>'Il contenuto differisce da quello conservato all’importazione.'];
                } elseif ($entry->status!=='imported') $state='held';
                elseif (!$policy || (int)$policy->client_id!==(int)$entry->client_id || $linkedCounts->get($policy->id)!==1) {
                    $state='missing';$differences[]=['field'=>'Collegamento','source'=>'Una polizza per questo gruppo','current'=>'Scheda assente, cliente diverso o più gruppi sulla stessa polizza.'];
                } else {
                    $state='matching';
                    $expected=['Numero polizza'=>[$data['number'],$policy->number], 'Compagnia'=>[$raw['Compagnia']??$data['company'],$policy->company],
                        'Agenzia'=>[$raw['Agenzia']??'', $policy->supplierAgency?->name??''],
                        'Decorrenza'=>[$data['starts_on']??null,$policy->starts_on?->toDateString()], 'Scadenza'=>[$data['expires_on']??null,$policy->expires_on?->toDateString()]];
                    foreach ($expected as $field=>[$before,$after]) if ($this->key($before)!==$this->key($after)) {
                        if (in_array($field,['Decorrenza','Scadenza'],true)) {
                            $format=fn($value)=>($date=$this->date($value)) ? \DateTimeImmutable::createFromFormat('!Y-m-d',$date)->format('d/m/Y') : $value;
                            [$before,$after]=[$format($before),$format($after)];
                        }
                        $differences[]=['field'=>$field,'source'=>$before,'current'=>$after];
                    }
                    $sourcePremium=$this->cents($data['premium']??null);$currentPremium=$this->cents($policy->premium);
                    if ($sourcePremium!==$currentPremium) $differences[]=['field'=>'Premio del contratto','source'=>$sourcePremium===null?'Non indicato':Money::format($sourcePremium),'current'=>$currentPremium===null?'Non indicato':Money::format($currentPremium)];
                    if ($sourcePremium!==null && $currentPremium!==null) { $sourceTotal+=$sourcePremium;$currentTotal+=$currentPremium;$compared++; } else $unavailable++;
                    $rateIssues=$this->rateIssues($sourceRows,$policy);
                    if ($differences) $state='changed';elseif($rateIssues)$state='rates';
                }
            }
            $payments=$policy?->titles->flatMap->collections;
            $rows[]=['state'=>$state,'label'=>self::STATES[$state],'number'=>$data['number'],'company'=>$raw['Compagnia']??$data['company'],
                'client_name'=>$raw['Contraente']??'Non indicato','source_rows'=>$candidate['source_rows'],'entry_id'=>$entry?->id,
                'policy_id'=>$policy?->id,'client_id'=>$policy?->client_id,'differences'=>$differences,'rate_issues'=>$rateIssues,
                'has_rate_data'=>(bool)array_filter($sourceRows,fn($row)=>filled($row['values']['Data Rata Attuale']??null) || filled($row['values']['Premio Rata']??null)),
                'receipt_cents'=>$payments?->filter(fn($payment)=>!$payment->voided_at && $payment->direction==='receipt')->sum('amount_cents'),
                'refund_cents'=>$payments?->filter(fn($payment)=>!$payment->voided_at && $payment->direction==='refund')->sum('amount_cents')];
            $counts[$state]++;
        }
        return ['rows'=>collect($rows),'counts'=>$counts,'source_file'=>$sourceFile,'compared_premiums'=>$compared,'unavailable_premiums'=>$unavailable,
            'source_premium_cents'=>$sourceTotal,'current_premium_cents'=>$currentTotal,
            'policies_without_import'=>InsurancePolicy::whereDoesntHave('importEntry')->count(),
            'entries_without_source'=>$entries->keys()->diff(collect($candidates)->pluck('key'))->count(),
            'cash_note'=>'Le indicazioni «OK», il mese di pagamento e lo stato dei documenti non identificano un incasso con importo e data effettiva. Gli incassi Excel restano da verificare; i pagamenti del gestionale sono mostrati separatamente e al netto degli storni.'];
    }
}
