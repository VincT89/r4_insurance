<?php
namespace App\Support;

class Money
{
    public static function cents(string|int $value): int
    {
        $value=str_replace(',','.',trim((string)$value));
        if (!preg_match('/\A\d{1,10}(?:\.\d{1,2})?\z/',$value)) throw \Illuminate\Validation\ValidationException::withMessages(['amount'=>'Inserisci un importo valido, con al massimo due decimali.']);
        [$whole,$fraction]=array_pad(explode('.',$value),2,'');
        return ((int)$whole)*100+(int)str_pad($fraction,2,'0');
    }
    public static function decimal(int $cents): string { return ($cents < 0 ? '-' : '').intdiv(abs($cents),100).'.'.str_pad((string)(abs($cents)%100),2,'0',STR_PAD_LEFT); }
    public static function format(int $cents): string { return ($cents < 0 ? '-' : '').number_format(intdiv(abs($cents),100),0,',','.').','.str_pad((string)(abs($cents)%100),2,'0',STR_PAD_LEFT).' €'; }
}
