<?php

namespace App\Services;

use App\Models\{AgencyCommissionSchedule, InsuranceBranch, InsuranceSubbranch, SupplierAgency, SupplierCompany, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgencyCommissionRegistry
{
    private function fail(string $field, string $message): never { throw ValidationException::withMessages([$field=>$message]); }

    private function units(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') return null;
        $value = str_replace(',','.',trim((string)$value));
        if (!preg_match('/\A\d{1,3}(?:\.\d{1,4})?\z/',$value)) $this->fail($field,'Inserisci una percentuale da 0 a 100 con al massimo quattro decimali.');
        [$whole,$fraction] = array_pad(explode('.',$value),2,'');
        $units = (int)$whole*10000+(int)str_pad($fraction,4,'0');
        if ($units > 1000000) $this->fail($field,'La percentuale non può superare 100.');
        return $units;
    }

    public function save(SupplierAgency $agency, User $user, array $data, ?AgencyCommissionSchedule $previous=null): AgencyCommissionSchedule
    {
        abort_unless($user->status === 'active' && $user->canManageSystem(),403);
        return DB::transaction(function () use ($agency,$user,$data,$previous) {
            /** Serialize mandate changes and catalog moves before checking the selected commission scopes. */
            $company = SupplierCompany::whereKey($data['supplier_company_id'])->lockForUpdate()->firstOrFail();
            $agency = SupplierAgency::whereKey($agency->id)->lockForUpdate()->firstOrFail();
            $branches = InsuranceBranch::orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $subbranches = InsuranceSubbranch::orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($previous) {
                $previous = $agency->commissionSchedules()->whereKey($previous->id)->lockForUpdate()->firstOrFail();
                if (!$previous->editable()) $this->fail('commission','Questa versione è già stata sostituita o annullata. Riapri la configurazione corrente.');
                if ((int)$data['supplier_company_id'] !== $previous->supplier_company_id) $this->fail('supplier_company_id','Una revisione deve conservare la compagnia. Crea una configurazione distinta per l’altro mandato.');
            }
            if ($agency->status !== 'active' || $company->status !== 'active') $this->fail('commission','L’agenzia e la compagnia devono essere attive per registrare condizioni.');
            if (!$agency->companies()->whereKey($company->id)->exists()) $this->fail('supplier_company_id','La compagnia non è collegata a questa agenzia.');
            $rows = collect($data['entries'])->filter(fn ($row)=>(bool)($row['selected']??false));
            if ($rows->isEmpty()) $this->fail('entries','Seleziona almeno un ramo o un sottoramo.');
            $values = []; $keys = [];
            foreach ($rows as $index=>$row) {
                $branch = $branches->get($row['branch_id']);
                $sub = !empty($row['subbranch_id']) ? $subbranches->get($row['subbranch_id']) : null;
                $key = $row['branch_id'].':'.($row['subbranch_id']??0);
                $preserved = $previous?->rates()->where('scope_key',$key)->exists();
                if (!$branch || (!$preserved && $branch->status !== 'active') || (!empty($row['subbranch_id']) && (!$sub || (!$preserved && $sub->status !== 'active') || $sub->insurance_branch_id !== $branch->id))) $this->fail('entries.'.$index.'.selected','Seleziona un ramo attivo e un suo sottoramo attivo.');
                if (isset($keys[$key])) $this->fail('entries','La stessa voce è stata selezionata più volte.');
                $keys[$key] = true;
                if (($row['basis']??null) === 'other' && empty(trim($row['basis_note']??''))) $this->fail('entries.'.$index.'.basis_note','Descrivi la base prevista dal mandato.');
                $values[] = ['insurance_branch_id'=>$branch->id,'insurance_subbranch_id'=>$sub?->id,'scope_key'=>$key,'basis'=>$row['basis']??null,'basis_note'=>$row['basis_note']??null,'issue_rate_units'=>$this->units($row['issue_rate']??null,'entries.'.$index.'.issue_rate'),'renewal_rate_units'=>$this->units($row['renewal_rate']??null,'entries.'.$index.'.renewal_rate')];
            }
            /** Two different mandates may share dates; the same mandate and scope must never have ambiguous dates. */
            $overlap = $agency->commissionSchedules()->current()->where('supplier_company_id',$company->id)
                ->when($previous,fn($q)=>$q->where('id','!=',$previous->id))
                ->whereDate('valid_from','<=',$data['valid_to']??'9999-12-31')->where(fn($q)=>$q->whereNull('valid_to')->orWhereDate('valid_to','>=',$data['valid_from']))
                ->whereHas('rates',fn($q)=>$q->whereIn('scope_key',array_keys($keys)))->exists();
            if ($overlap) $this->fail('valid_from','Esistono già condizioni per una delle voci selezionate in questo periodo. Modifica la configurazione esistente oppure scegli un periodo successivo senza sovrapposizioni.');
            $schedule = $agency->commissionSchedules()->create(['supplier_company_id'=>$company->id,'valid_from'=>$data['valid_from'],'valid_to'=>$data['valid_to']??null,'accrual_event'=>$data['accrual_event']??null,'notes'=>$data['notes']??null,'created_by'=>$user->id,'replaces_id'=>$previous?->id,'revision_reason'=>$previous ? $data['revision_reason'] : null]);
            $schedule->rates()->createMany($values);
            if ($previous) $previous->update(['superseded_at'=>now()]);
            $agency->auditLogs()->create(['user_id'=>$user->id,'action'=>$previous ? 'commission_revised' : 'commission_created','description'=>($previous ? 'Revisionate' : 'Configurate').' le condizioni provvigionali di '.$company->name.'. Configurazione #'.$schedule->id.'.','old_values'=>$previous ? ['schedule_id'=>$previous->id] : null,'new_values'=>['schedule_id'=>$schedule->id,'supplier_company_id'=>$company->id,'valid_from'=>$data['valid_from'],'valid_to'=>$data['valid_to']??null,'accrual_event'=>$data['accrual_event']??null,'rates'=>$values,'reason'=>$data['revision_reason']??null],'created_at'=>now()]);
            return $schedule;
        });
    }

    public function void(SupplierAgency $agency, AgencyCommissionSchedule $schedule, User $user, string $reason): void
    {
        abort_unless($user->status === 'active' && $user->canManageSystem(),403);
        DB::transaction(function () use ($agency,$schedule,$user,$reason) {
            $agency = SupplierAgency::whereKey($agency->id)->lockForUpdate()->firstOrFail();
            $schedule = $agency->commissionSchedules()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            if (!$schedule->editable()) $this->fail('commission','La configurazione non è più modificabile.');
            $schedule->update(['voided_at'=>now(),'voided_by'=>$user->id,'void_reason'=>$reason]);
            $agency->auditLogs()->create(['user_id'=>$user->id,'action'=>'commission_voided','description'=>'Annullata la configurazione provvigionale #'.$schedule->id.': '.$reason,'new_values'=>['schedule_id'=>$schedule->id,'reason'=>$reason],'created_at'=>now()]);
        });
    }
}
