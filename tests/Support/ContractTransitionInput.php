<?php

namespace Tests\Support;

use App\Models\InsurancePolicy;
use App\Services\PolicyContractTransitions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

trait ContractTransitionInput
{
    private function transitionInput(InsurancePolicy $policy, array $values): array
    {
        $source=$policy->fresh();
        return array_replace($source->only(['number','company','supplier_agency_id','category','subcategory','product','payment_method','payment_frequency']),[
            'source_revision'=>app(PolicyContractTransitions::class)->revision($source),'adjustment'=>'none',
            'contract_reviewed'=>1,'request_key'=>(string)Str::uuid(),
            'contract'=>UploadedFile::fake()->createWithContent('contratto-collaudo.pdf',"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF"),
        ],$values);
    }
}
