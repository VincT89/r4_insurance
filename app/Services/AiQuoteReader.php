<?php

namespace App\Services;

class AiQuoteReader extends AiContractReader
{
    protected function schema(): AiContractSchema { return app(AiQuoteSchema::class); }
    protected function feature(): string { return 'insurance_quote'; }
}
