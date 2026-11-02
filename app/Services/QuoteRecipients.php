<?php

namespace App\Services;

use App\Models\{InsuranceQuoteCase, SupplierCompany};
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class QuoteRecipients
{
    public const MAX_RECIPIENTS = 50;

    public static function key(int $companyId, ?int $agencyId = null): string
    {
        return $agencyId ? 'agency:'.$companyId.':'.$agencyId : 'company:'.$companyId;
    }

    public static function quoteKey(?int $agencyId): string
    {
        return $agencyId ? 'agency:'.$agencyId : 'direct';
    }

    public static function rules(): array
    {
        return ['recipient_keys' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_RECIPIENTS], 'recipient_keys.*' => ['required', 'string', 'distinct', 'max:60', 'regex:/^(company:[1-9][0-9]*|agency:[1-9][0-9]*:[1-9][0-9]*)$/D']];
    }

    public function resolve(array $keys, bool $email = false, bool $active = true, ?InsuranceQuoteCase $case = null): Collection
    {
        validator(['recipient_keys' => $keys], self::rules(), ['recipient_keys.required' => 'Seleziona almeno una compagnia o un’agenzia.', 'recipient_keys.min' => 'Seleziona almeno una compagnia o un’agenzia.'])->validate();
        $parsed = collect($keys)->map(function ($key) {
            $parts = explode(':', $key);
            $companyId = filter_var($parts[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $agencyId = isset($parts[2]) ? filter_var($parts[2], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
            if (!$companyId || $agencyId === false) $this->fail('Seleziona i destinatari presenti nell’elenco.');
            return ['key' => $key, 'company_id' => $companyId, 'agency_id' => $agencyId];
        });
        $companies = SupplierCompany::with('agencies')->whereIn('id', $parsed->pluck('company_id')->unique())->get()->keyBy('id');
        return $parsed->map(function ($target) use ($companies, $email, $active, $case) {
            $company = $companies->get($target['company_id']);
            if (!$company || ($active && $company->status !== 'active')) $this->fail('Una compagnia selezionata non è più disponibile. Controlla la selezione.');
            if ($case?->purpose === 'addition' && (int) $case->currentPolicy?->supplier_company_id !== $company->id) $this->fail('Per un’aggiunta scegli la compagnia della polizza attuale e le sue agenzie.');
            $agency = $target['agency_id'] ? $company->agencies->firstWhere('id', $target['agency_id']) : null;
            if ($target['agency_id'] && (!$agency || ($active && $agency->status !== 'active'))) $this->fail('Un’agenzia selezionata non è attiva o non è collegata alla compagnia indicata. Controlla la selezione.');
            $recipient = $agency ?? $company;
            if ($email && !filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) $this->fail('Completa l’indirizzo email di '.$recipient->name.' nella sua anagrafica prima di inviare la richiesta.');
            return $target + ['company' => $company, 'agency' => $agency, 'name' => $recipient->name, 'email' => $recipient->email];
        });
    }

    public function catalog(?InsuranceQuoteCase $case = null, bool $activeOnly = false): Collection
    {
        return SupplierCompany::with(['agencies' => fn ($q) => $q->orderBy('name')])->when($activeOnly, fn ($q) => $q->where('status', 'active'))
            ->when($case?->purpose === 'addition', fn ($q) => $q->whereKey($case->currentPolicy?->supplier_company_id ?? 0))->orderBy('name')->get();
    }

    public function snapshot(array $target): array
    {
        return ['key' => $target['key'], 'company_id' => $target['company_id'], 'company_name' => $target['company']->name, 'agency_id' => $target['agency_id'], 'agency_name' => $target['agency']?->name];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['recipient_keys' => $message]);
    }
}
