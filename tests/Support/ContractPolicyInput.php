<?php

namespace Tests\Support;

trait ContractPolicyInput
{
    private function contractInput(array $values): array
    {
        $quote = \App\Models\InsuranceQuote::find($values['insurance_quote_id'] ?? null);
        return array_replace($quote ? app(\App\Services\InsuranceQuoteWorkflow::class)->policyValues($quote) : [], [
            'contract'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('contratto.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF"),
            'contract_reviewed'=>1, 'titles_mode'=>'manual',
        ], $values);
    }
}
