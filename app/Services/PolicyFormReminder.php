<?php

namespace App\Services;

use App\Models\{InsurancePolicy, InsurancePolicyChange};
use Illuminate\Notifications\DatabaseNotification;
use Ramsey\Uuid\Uuid;

class PolicyFormReminder
{
    public const TYPE = 'insurance_policy_forms';

    private static function notificationId(InsurancePolicy $policy): string
    {
        return (string) Uuid::uuid5(Uuid::NAMESPACE_URL, self::TYPE.'|'.$policy->id);
    }

    public static function key(InsurancePolicy $policy, ?InsurancePolicyChange $change): string
    {
        return (string) Uuid::uuid5(Uuid::NAMESPACE_URL, self::TYPE.'|'.$policy->id.'|'.($change?->id ?? 'initial'));
    }

    public static function url(InsurancePolicy $policy, ?InsurancePolicyChange $change = null): string
    {
        return route('clients.forms.create', [$policy->client_id, 'policy'=>$policy->id, 'change'=>$change?->id, 'reminder'=>self::key($policy,$change)], false);
    }

    public static function record(InsurancePolicy $policy, ?InsurancePolicyChange $change = null): void
    {
        $user = auth()->user();
        /** Una sola attività per polizza. La chiave dell’operazione impedisce a una vecchia scheda di chiudere il promemoria del rinnovo successivo. */
        DatabaseNotification::updateOrCreate(['id'=>self::notificationId($policy)], [
            'type'=>self::TYPE, 'notifiable_type'=>$user->getMorphClass(), 'notifiable_id'=>$user->id, 'read_at'=>null,
            'data'=>['type'=>self::TYPE, 'client_id'=>$policy->client_id, 'policy_id'=>$policy->id, 'change_id'=>$change?->id,
                'request_key'=>self::key($policy,$change), 'title'=>$change && !$change->isCompanyChange() ? 'Moduli del rinnovo' : 'Moduli della nuova polizza',
                'message'=>'Polizza '.$policy->number.' · Scegli i moduli da generare.', 'url'=>self::url($policy,$change)],
        ]);
    }

    public static function find(InsurancePolicy $policy): ?DatabaseNotification
    {
        return DatabaseNotification::where('type', self::TYPE)->find(self::notificationId($policy));
    }

    public static function clear(InsurancePolicy $policy): void
    {
        DatabaseNotification::where('type', self::TYPE)->whereKey(self::notificationId($policy))->delete();
    }
}
