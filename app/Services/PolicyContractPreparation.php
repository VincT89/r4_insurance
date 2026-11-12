<?php

namespace App\Services;

use App\Models\{Client, InsuranceFormTemplate, InsurancePolicy};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PolicyContractPreparation
{
    public function templates(): array
    {
        return InsuranceFormTemplate::where('status','active')->with(['branch','subbranch'])->orderBy('title')->get()->map(fn($template)=>[
            'id'=>$template->id,'title'=>$template->title,'scope'=>$template->scope,'client_type'=>$template->client_type ?: 'all',
            'branch'=>$template->branch?->name,'subbranch'=>$template->subbranch?->name,'required'=>$template->required,
            'signer'=>$template->needsSigner(),'role'=>str_contains(\App\Support\FormTemplateContent::allText($template->body),'{{qualifica_firmatario}}'),
            'place'=>$template->needsPlace(),'revision'=>$template->editKey(),'preview'=>route('form-templates.preview',[$template,'viewer'=>1]),
        ])->all();
    }

    public function clientContext(Client $client): array
    {
        if(!$client->exists) return [];
        $hash=app(InsuranceForms::class)->contextHash($client,null);
        $prepared=$client->formPackages()->whereNull('insurance_policy_id')->where('context_hash',$hash)->with('documents:id,insurance_form_package_id,insurance_form_template_id')->get()
            ->flatMap(fn($package)=>$package->documents->pluck('insurance_form_template_id'))->unique()->values()->all();
        return ['id'=>$client->id,'name'=>$client->name,'client_type'=>$client->client_type,'reference_person'=>$client->reference_person,'reference_role'=>$client->reference_role,'prepared_templates'=>$prepared];
    }

    public function vehicle(Client $client, array $data): ?int
    {
        if(empty($data)) return null;
        /** The client lock held by policy creation also serializes fleet additions and concurrent matching plates. */
        $vehicle=$client->vehicles()->where('plate',$data['plate'])->lockForUpdate()->first();
        if($vehicle) {
            if(filled($data['vin'] ?? null) && filled($vehicle->vin) && mb_strtoupper($data['vin'])!==mb_strtoupper($vehicle->vin)) throw ValidationException::withMessages(['new_vehicle.vin'=>'Questa targa è già presente con un telaio diverso. Controlla il veicolo nella scheda cliente.']);
            return $vehicle->id;
        }
        return $client->vehicles()->create($data)->id;
    }

    public function prepare(InsurancePolicy $policy, array $data, array &$stored): int
    {
        if(($data['mode'] ?? 'later')!=='prepare' || empty($data['templates'])) {
            if(isset($data['mode']) && ($pending=PolicyFormReminder::find($policy))) $pending->update(['data'=>array_merge($pending->data,['selected_templates'=>array_map('intval',$data['templates'] ?? [])])]);
            return 0;
        }
        $templates=InsuranceFormTemplate::whereIn('id',$data['templates'])->orderBy('id')->lockForUpdate()->get();
        if($templates->count()!==count($data['templates'])) throw ValidationException::withMessages(['contract_forms.templates'=>'Un modello selezionato non è più disponibile. Ricontrolla i moduli.']);
        foreach($templates as $template) if($template->status!=='active' || !in_array($template->scope,['client','policy'],true) || !hash_equals($template->editKey(),$data['revisions'][$template->id] ?? '')) throw ValidationException::withMessages(['contract_forms.templates'=>'Un modello è stato aggiornato dopo l’apertura della pagina. Riapri la pagina e controlla la nuova versione.']);
        $forms=app(InsuranceForms::class); $client=$policy->client;
        foreach($templates->groupBy('scope') as $scope=>$choices) {
            $target=$scope==='client' ? null : $policy;
            $values=\Illuminate\Support\Arr::only($data,['signer_name','signer_role','place','document_date']);
            $values+=['request_key'=>(string)Str::uuid(),'templates'=>$choices->pluck('id')->all(),'reason'=>$target ? 'initial' : 'client','context_hash'=>$forms->contextHash($client,$target)];
            $forms->createPackage($client,$target,$values,$stored);
        }
        PolicyFormReminder::clear($policy);
        return $templates->count();
    }
}
