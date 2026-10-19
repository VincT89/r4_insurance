<?php

namespace App\Services;

use App\Models\{Client, InsuranceClaim, InsuranceClaimActivity, User};
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Support\Str;

class ClaimReminders
{
    public const TYPE = 'insurance_claim_activity';

    public function refresh(User $user): void
    {
        if (Cache::add('claim-reminders:'.$user->id,true,now()->addMinute())) {
            try { $this->forUser($user); }
            catch (\Throwable $e) { Cache::forget('claim-reminders:'.$user->id); throw $e; }
        }
    }

    public function invalidate(InsuranceClaim $claim): void
    {
        foreach (User::where('role','admin')->where('status','active')->pluck('id')->push($claim->assigned_to)->filter()->unique() as $id) Cache::forget('claim-reminders:'.$id);
    }

    public function forUser(User $user): int
    {
        if ($user->status!=='active' || !$user->can('viewAny',Client::class)) return 0;
        $created=0;
        $activities=InsuranceClaimActivity::pending()->visibleTo($user)
            ->whereHas('claim',fn($q)=>$q->when(!$user->isAdmin(),fn($q)=>$q->where('assigned_to',$user->id)))
            ->whereDate('due_on','<=',today()->addDays(3))->with('claim')->get();
        foreach ($activities as $activity) {
            $created+=DB::transaction(function () use ($user,$activity) {
                $client=Client::current()->whereKey($activity->claim->client_id)->lockForUpdate()->first();
                if (!$client || !$user->can('view',$client)) return 0;
                $claim=InsuranceClaim::whereKey($activity->insurance_claim_id)->lockForUpdate()->first();
                $activity=InsuranceClaimActivity::whereKey($activity->id)->lockForUpdate()->first();
                if (!$claim || $claim->isClosed() || (!$user->isAdmin() && $claim->assigned_to!==$user->id) || !$activity || $activity->status!=='pending' || $activity->due_on->gt(today()->addDays(3))) return 0;
                $stage=$activity->due_on->gt(today())?'upcoming':'due';
                $inserted=DB::table('insurance_claim_reminders')->insertOrIgnore(['insurance_claim_activity_id'=>$activity->id,'user_id'=>$user->id,'revision'=>$activity->revision,'stage'=>$stage,'created_at'=>now()]);
                if (!$inserted) return 0;
                /** Notifications contain references only; free text can include sensitive claim information. */
                $user->notifications()->create(['id'=>(string)Str::uuid(),'type'=>self::TYPE,'data'=>[
                    'type'=>self::TYPE,'title'=>$stage==='upcoming'?'Attività sinistro in scadenza':($activity->due_on->lt(today())?'Attività sinistro in arretrato':'Attività sinistro da svolgere oggi'),
                    'client_id'=>$client->id,'claim_id'=>$claim->id,'activity_id'=>$activity->id,'revision'=>$activity->revision,
                    'due_on'=>$activity->due_on->toDateString(),'message'=>'Sinistro #'.$claim->id.' · '.$client->name.' · '.$activity->due_on->format('d/m/Y'),
                    'url'=>route('claims.show',$claim,false).'#activities',
                ]]);
                return 1;
            });
        }
        return $created;
    }
}
