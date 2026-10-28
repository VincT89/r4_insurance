<?php

namespace App\Services;

class RegistryImportSchema
{
    public const KINDS = ['company' => 'Compagnie', 'agency' => 'Agenzie', 'branch' => 'Rami', 'subbranch' => 'Sottorami'];
    public const STATES = ['new' => 'Nuove', 'exists' => 'Già presenti', 'duplicate' => 'Ripetute nel file', 'review' => 'Da correggere'];
    public const ROW_STATES = ['new' => 'Nuova', 'exists' => 'Già presente', 'duplicate' => 'Ripetuta nel file', 'review' => 'Da correggere'];

    public static function fields(string $kind): array
    {
        $fields = ['name' => ['label' => match ($kind) { 'company' => 'Nome compagnia', 'agency' => 'Nome agenzia', 'branch' => 'Nome ramo', default => 'Nome sottoramo' }, 'required' => true, 'max' => in_array($kind, ['branch', 'subbranch']) ? 100 : 255]];
        if ($kind === 'subbranch') $fields['branch'] = ['label' => 'Ramo principale', 'required' => true, 'max' => 100];
        if ($kind === 'agency') $fields += [
            'companies' => ['label' => 'Compagnie collegate', 'required' => true, 'max' => 5000],
            'agency_code' => ['label' => 'Codice agenzia', 'max' => 50, 'identifier' => true],
            'company_name' => ['label' => 'Ragione sociale', 'max' => 255],
            'vat_number' => ['label' => 'Partita IVA', 'max' => 20, 'identifier' => true],
            'tax_code' => ['label' => 'Codice fiscale', 'max' => 20, 'identifier' => true],
        ];
        if (in_array($kind, ['company', 'agency'])) $fields += [
            'email' => ['label' => 'Email', 'max' => 255, 'email' => true],
            'phone' => ['label' => 'Telefono', 'max' => 50, 'identifier' => true],
            'reference_person' => ['label' => 'Referente', 'max' => 255],
        ];
        if ($kind === 'agency') $fields += [
            'pec' => ['label' => 'PEC', 'max' => 255, 'email' => true],
            'mobile' => ['label' => 'Cellulare', 'max' => 50, 'identifier' => true],
            'reference_role' => ['label' => 'Ruolo referente', 'max' => 255],
            'reference_email' => ['label' => 'Email referente', 'max' => 255, 'email' => true],
            'reference_phone' => ['label' => 'Telefono referente', 'max' => 50, 'identifier' => true],
            'address' => ['label' => 'Indirizzo', 'max' => 255],
            'city' => ['label' => 'Comune', 'max' => 100],
            'province' => ['label' => 'Provincia', 'max' => 5],
            'postal_code' => ['label' => 'CAP', 'max' => 10, 'identifier' => true],
            'country' => ['label' => 'Paese', 'max' => 100],
        ];
        return $fields + ['status' => ['label' => 'Stato', 'max' => 30], 'notes' => ['label' => 'Note', 'max' => 10000]];
    }

    public static function key(?string $text): string
    {
        return InsuranceCatalog::normalize($text ?? '');
    }

    public static function suggest(string $kind, array $headers): array
    {
        $mapping = [];
        $aliases = ['name' => ['nome', 'denominazione', match ($kind) { 'company' => 'compagnia', 'agency' => 'agenzia', 'branch' => 'ramo', default => 'sottoramo' }], 'companies' => ['compagnie', 'compagnie collegate', 'compagnia'], 'branch' => ['ramo', 'ramo principale'], 'postal_code' => ['cap'], 'city' => ['citta', 'città', 'comune']];
        foreach (self::fields($kind) as $field => $definition) {
            $names = array_map(self::key(...), [$field, $definition['label'], ...($aliases[$field] ?? [])]);
            $matches = array_keys(array_filter($headers, fn ($header) => in_array(self::key($header), $names, true)));
            if (count($matches) === 1 && !in_array($matches[0], $mapping, true)) $mapping[$field] = $matches[0];
        }
        return $mapping;
    }
}
