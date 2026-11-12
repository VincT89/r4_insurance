<?php

namespace App\Services;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Schema;

class AiConfiguration
{
    public function settings(): ?AiSetting { return Schema::hasTable('ai_settings') ? AiSetting::find(1) : null; }
    public function ready(): bool { $setting=$this->settings(); return $setting && $setting->enabled && filled($setting->api_key); }
    public function publicValues(): array
    {
        $setting=$this->settings();
        return ['enabled'=>$setting?->enabled ?? false,'key_saved'=>filled($setting?->api_key),'model'=>$setting?->model ?? 'gpt-6.1-terra','monthly_budget'=>number_format(($setting?->monthly_budget_microusd ?? 10000000)/1000000,2,'.',''),'document_budget'=>number_format(($setting?->document_budget_microusd ?? 250000)/1000000,2,'.','')];
    }
}
