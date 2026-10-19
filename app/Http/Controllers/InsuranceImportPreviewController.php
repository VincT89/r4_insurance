<?php

namespace App\Http\Controllers;

use App\Services\InsuranceImportPreview;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InsuranceImportPreviewController extends Controller
{
    public function index(Request $request, InsuranceImportPreview $preview)
    {
        $sections = ['overview' => 'Riepilogo', 'registry' => 'Esito anagrafiche', 'policies' => 'Esito polizze', 'branches' => 'Rami e sottorami', 'providers' => 'Compagnie e agenzie', 'issues' => 'Controlli', 'mapping' => 'Campi da importare'];
        $section = $request->validate(['section' => ['nullable', Rule::in(array_keys($sections))]])['section'] ?? 'overview';
        $registryCounts = \App\Models\RegistryImportEntry::where('kind', '!=', 'lock')->get(['kind','status'])->groupBy('kind')->map(fn ($entries) => $entries->countBy('status')->all())->all();
        $registryEntries = null;
        $policyCounts = \Illuminate\Support\Facades\Schema::hasTable('insurance_policy_imports') ? \App\Models\InsurancePolicyImport::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all() : [];
        $policyEntries = null;
        if ($section === 'policies' && $policyCounts) {
            $filters = $request->validate(['result' => ['nullable', 'in:imported,held'], 'search' => ['nullable', 'string', 'max:255']]);
            $policyEntries = \App\Models\InsurancePolicyImport::with(['client:id,name', 'policy:id,client_id,status,starts_on,expires_on'])
                ->when($filters['result'] ?? null, fn ($q, $value) => $q->where('status', $value))
                ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$value.'%')->orWhere('client_name', 'like', '%'.$value.'%')->orWhere('company', 'like', '%'.$value.'%')->orWhere('agency', 'like', '%'.$value.'%')))
                ->orderBy('status')->orderBy('id')->paginate(\App\Support\TableListing::perPage(25), ['id', 'client_id', 'insurance_policy_id', 'number', 'company', 'agency', 'client_name', 'status', 'source_rows', 'issues'])->withQueryString();
        }
        if ($section === 'registry') {
            $filters = $request->validate(['kind'=>['nullable','in:client,company,agency'],'result'=>['nullable','in:imported,held,linked']]);
            $registryEntries = \App\Models\RegistryImportEntry::where('kind', '!=', 'lock')
                ->when($filters['kind'] ?? null, fn ($q,$v) => $q->where('kind',$v))
                ->when($filters['result'] ?? null, fn ($q,$v) => $q->where('status',$v))
                ->orderBy('status')->orderBy('source_name')->paginate(\App\Support\TableListing::perPage(25))->withQueryString();
        }
        try {
            $summary = $preview->summary();
            if ($summary && $section === 'branches') {
                $summary['taxonomy'] = app(\App\Services\InsuranceCatalog::class)->previewTaxonomy($summary['taxonomy']);
            }
        } catch (\RuntimeException | \JsonException $e) {
            return response()->view('imports.preview', compact('sections', 'section') + ['summary' => null, 'error' => 'L’anteprima è incompleta o è stata modificata. Rigenerare il lotto prima di proseguire.'], 409);
        }
        return response()->view('imports.preview', compact('sections', 'section', 'summary', 'registryCounts', 'registryEntries', 'policyCounts', 'policyEntries'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function download(InsuranceImportPreview $preview)
    {
        abort_unless(is_file($preview->directory().'/manifest.json'), 404);
        try {
            $file = $preview->verifiedFile('review.xlsx');
        } catch (\RuntimeException | \JsonException $e) {
            abort(409, 'Prospetto non disponibile o modificato. Rigenerare il lotto.');
        }
        return response()->download($file, 'Preparazione-importazione-R4.xlsx', ['Cache-Control' => 'private, no-store']);
    }

    public function compare(\App\Models\RegistryImportEntry $entry, \App\Services\RegistryImportComparison $comparison)
    {
        abort_unless($entry->kind === 'client' && in_array($entry->source, ['clients', 'policies'], true), 404);
        try {
            $data = $comparison->forEntry($entry);
        } catch (\RuntimeException | \JsonException $e) {
            abort(409, 'La fonte del confronto è incompleta o modificata. Aggiornare la verifica.');
        }
        return response()->view('imports.compare', $data)->header('Cache-Control', 'private, no-store');
    }

    public function policySource(\App\Models\InsurancePolicyImport $entry)
    {
        $entry->load(['policy', 'auditLogs' => fn ($q) => $q->where('action', \App\Services\ApprovedEquivalentPolicyImport::ACTION)->with('user')->latest()]);
        $fields = ['Protocollo', 'Contraente', 'Cod. Fiscale / P. IVA', 'Compagnia', 'Agenzia', 'Numero Polizza', 'Ramo', 'Sottoramo', 'Polizze', 'Efetto', 'Scadenza', 'Interesse Assic.', 'Premio annuale', 'Premio Rata', 'Data Rata Attuale', 'Data Rata Futura', 'Frazionamento', 'Tacito Rinnovo', 'Documenti Polizza', 'Doc. Compagnia', 'Note'];
        return response()->view('imports.policy-source', compact('entry', 'fields'))->header('Cache-Control', 'private, no-store');
    }
}
