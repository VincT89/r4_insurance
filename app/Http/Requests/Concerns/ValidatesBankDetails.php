<?php

namespace App\Http\Requests\Concerns;

use App\Rules\BankIban;
use App\Support\Iban;

trait ValidatesBankDetails
{
    private function bankRules(): array
    {
        return [
            'iban'=>['bail','nullable','string','max:34',new BankIban],
            'account_holder'=>['required_with:iban','nullable','string','max:255'],
            'bank_name'=>['nullable','string','max:255'],
            'bic'=>['nullable','string','regex:/^[A-Z0-9]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/D'],
            'billing_email'=>['nullable','email','max:255'],
            'sdi_code'=>['nullable','string','regex:/^[A-Z0-9]{7}$/D'],
        ];
    }

    private function normalizeBankDetails(): void
    {
        foreach (['iban','bic','sdi_code'] as $field) {
            if (is_string($this->input($field))) $this->merge([$field=>Iban::normalize($this->input($field)) ?: null]);
        }
        if (is_string($this->input('billing_email'))) $this->merge(['billing_email'=>strtolower(trim($this->input('billing_email')))]);
    }

    private function bankMessages(): array
    {
        return ['account_holder.required_with'=>'Indica l’intestatario del conto associato all’IBAN.','bic.regex'=>'Inserisci un BIC/SWIFT di 8 oppure 11 caratteri.','sdi_code.regex'=>'Il codice destinatario SDI deve contenere 7 lettere o cifre.'];
    }

    private function bankAttributes(): array
    {
        return ['iban'=>'IBAN','account_holder'=>'intestatario del conto','bank_name'=>'banca','bic'=>'BIC/SWIFT','billing_email'=>'email amministrativa','sdi_code'=>'codice destinatario SDI'];
    }
}
