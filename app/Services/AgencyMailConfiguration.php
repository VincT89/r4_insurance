<?php

namespace App\Services;

use App\Models\OutboundMailSetting;
use Illuminate\Validation\ValidationException;

class AgencyMailConfiguration
{
    public function values(): array
    {
        return OutboundMailSetting::find(1)?->credentials ?? [
            'enabled'=>false, 'host'=>'', 'port'=>587, 'encryption'=>'starttls', 'username'=>'', 'password'=>'',
            'from_address'=>'', 'from_name'=>config('branding.wordmark'),
        ];
    }

    public function publicValues(): array
    {
        $values = $this->values();
        $values['password_saved'] = filled($values['password'] ?? null);
        unset($values['password']);
        return $values;
    }

    public function ready(): bool
    {
        $v = $this->values();
        return (bool)($v['enabled'] ?? false) && filled($v['host'] ?? null) && filled($v['username'] ?? null)
            && filled($v['password'] ?? null) && (bool)filter_var($v['from_address'] ?? '', FILTER_VALIDATE_EMAIL)
            && in_array($v['encryption'] ?? null, ['starttls','tls'], true);
    }

    public function sender(): array
    {
        $values = $this->values();
        return ['address'=>$values['from_address'] ?? '', 'name'=>$values['from_name'] ?? config('branding.wordmark')];
    }

    public function transport(): array
    {
        if (!$this->ready()) throw ValidationException::withMessages(['email'=>'Configura e abilita la casella nelle Impostazioni email prima di inviare.']);
        $v = $this->values();
        /** Only authenticated, encrypted SMTP is allowed; no log/array fallback may report a false delivery. */
        return ['transport'=>'smtp', 'scheme'=>$v['encryption'] === 'tls' ? 'smtps' : 'smtp', 'host'=>$v['host'],
            'port'=>(int)$v['port'], 'username'=>$v['username'], 'password'=>$v['password'],
            'timeout'=>15, 'auto_tls'=>true, 'require_tls'=>true, 'verify_peer'=>true];
    }
}
