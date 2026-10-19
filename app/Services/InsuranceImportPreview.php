<?php

namespace App\Services;

use RuntimeException;

class InsuranceImportPreview
{
    public function directory(): string
    {
        return config('insurance-import.directory', storage_path('app/private/insurance-import'));
    }

    public function summary(): ?array
    {
        if (! is_file($this->directory().'/manifest.json')) return null;
        $summary = json_decode(file_get_contents($this->verifiedFile('summary.json')), true, 512, JSON_THROW_ON_ERROR);
        if (($summary['version'] ?? null) !== 1 || ($summary['mode'] ?? null) !== 'preview_only') {
            throw new RuntimeException('Formato anteprima inatteso.');
        }
        return $summary;
    }

    public function verifiedFile(string $name): string
    {
        if (! in_array($name, ['summary.json', 'clients.json', 'policies.json', 'issues.json', 'excluded.json', 'repeated-policies.json', 'source-rows.jsonl', 'review.xlsx'], true)) {
            throw new RuntimeException('File non previsto.');
        }
        $manifest = json_decode(file_get_contents($this->directory().'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $file = $this->directory().'/'.$name;
        $expected = $manifest['files'][$name] ?? null;
        if (! is_string($expected) || ! is_file($file) || ! hash_equals($expected, hash_file('sha256', $file))) {
            throw new RuntimeException('Anteprima modificata o incompleta: rigenerare il lotto.');
        }
        return $file;
    }
}
