<?php

namespace App\Http\Controllers;

use App\Models\OutboundMailSetting;
use App\Services\{AgencyMailConfiguration, AuditLogService};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmailSettingsController extends Controller
{
    public function edit(AgencyMailConfiguration $configuration)
    {
        $this->authorize('system.admin');
        return view('settings.email', ['settings'=>$configuration->publicValues(), 'ready'=>$configuration->ready()]);
    }

    public function update(Request $request, AgencyMailConfiguration $configuration)
    {
        $this->authorize('system.admin');
        $data = $request->validate([
            'enabled'=>['required','boolean'], 'host'=>['required','string','max:253','regex:/^[a-z0-9](?:[a-z0-9.\-]*[a-z0-9])?$/i'],
            'port'=>['required','integer','between:1,65535'], 'encryption'=>['required','in:starttls,tls'],
            'username'=>['required','string','max:255','not_regex:/[\r\n]/'], 'smtp_password'=>['nullable','string','max:1000'],
            'from_address'=>['required','email:rfc','max:254'], 'from_name'=>['required','string','max:150','not_regex:/[\r\n]/'],
        ]);
        $data['password'] = filled($data['smtp_password'] ?? null) ? $data['smtp_password'] : ($configuration->values()['password'] ?? '');
        unset($data['smtp_password']);
        if (!$data['password']) throw ValidationException::withMessages(['smtp_password'=>'Inserisci la password della casella o la password applicativa prevista dal servizio.']);
        $data['enabled'] = (bool)$data['enabled'];
        $setting = OutboundMailSetting::updateOrCreate(['id'=>1], ['credentials'=>$data, 'updated_by'=>$request->user()->id]);
        app(AuditLogService::class)->log('email_settings_updated', $setting, description:'Aggiornata la configurazione della casella per le comunicazioni alle agenzie.');
        return to_route('email-settings.edit')->with('success','Configurazione salvata. '.($data['enabled'] ? 'L’invio sarà disponibile dopo il controllo delle polizze selezionate.' : 'L’invio è disabilitato.'));
    }
}
