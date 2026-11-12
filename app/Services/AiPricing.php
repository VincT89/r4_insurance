<?php

namespace App\Services;

class AiPricing
{
    public const VERIFIED_ON = '2026-11-10';
    public const SOURCE = 'https://developers.openai.com/api/docs/pricing';
    public const MODELS = ['gpt-6.1-terra'=>['name'=>'GPT-6.1 Terra','input'=>1000000,'cached'=>50000,'output'=>5000000], 'gpt-6.1-sol'=>['name'=>'GPT-6.1 Sol','input'=>2000000,'cached'=>100000,'output'=>10000000]];

    public static function rates(string $model, int $input): array
    {
        $rates=self::MODELS[$model] ?? throw new \InvalidArgumentException('Modello AI non configurato.');
        if($input>272000) { $rates['input']*=2; $rates['cached']*=2; $rates['output']=(int)($rates['output']*3/2); }
        return $rates;
    }

    public static function cost(array $rates, int $input, int $output, int $cached=0): int
    {
        /** One final integer rounding preserves cached-input discounts without floating-point monetary totals. */
        $amount=($input-min($input,$cached))*$rates['input']+min($input,$cached)*$rates['cached']+$output*$rates['output'];
        return intdiv($amount+999999,1000000);
    }

    public static function format(?int $microusd, int $decimals=4): string
    {
        return $microusd===null ? 'Da verificare' : number_format($microusd/1000000,$decimals,',','.').' USD';
    }

    public static function dollars(string $amount): int
    {
        if(!preg_match('/^\d{1,5}(?:\.\d{1,6})?$/D',$amount)) throw new \InvalidArgumentException('Importo USD non valido.');
        [$whole,$decimal]=array_pad(explode('.',$amount,2),2,'');
        return (int)$whole*1000000+(int)str_pad($decimal,6,'0');
    }
}
