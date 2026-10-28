<?php

namespace App\Http\Controllers;

use App\Services\{RegistryImportCommitter, RegistryImportPlanner, RegistryImportReader, RegistryImportSchema, RegistryImportStore};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class RegistryImportController extends Controller
{
    public function index(Request $request, RegistryImportStore $store)
    {
        return view('registry-imports.index', ['kinds' => RegistryImportSchema::KINDS, 'recent' => $store->recent($request->user()->id)]);
    }

    public function upload(Request $request, RegistryImportReader $reader, RegistryImportStore $store)
    {
        $data = $request->validate(['kind' => ['required', Rule::in(array_keys(RegistryImportSchema::KINDS))], 'file' => ['required', 'file', 'max:5120', 'extensions:csv,xlsx'],
            'separator' => ['required', 'in:auto,semicolon,comma,tab'], 'encoding' => ['required', 'in:UTF-8,Windows-1252']], ['file.required' => 'Scegli un file da caricare.', 'file.file' => 'Il caricamento del file non è riuscito.', 'file.max' => 'Il file deve essere di massimo 5 MB.', 'file.extensions' => 'Sono ammessi file CSV o Excel .xlsx.']);
        $sheets = $reader->read($request->file('file'), $data['separator'], $data['encoding']);
        $id = $store->create($request->user()->id, $data['kind'], $request->file('file')->getClientOriginalName(), $sheets);
        return redirect()->route('registry-imports.mapping', $id);
    }

    public function mapping(Request $request, string $id, RegistryImportStore $store, RegistryImportPlanner $planner)
    {
        $batch = $store->load($request->user()->id, $id);
        if (isset($batch['result'])) return redirect()->route('registry-imports.preview', $id);
        $data = $request->validate(['sheet' => ['nullable', 'integer', 'min:0', 'max:9']]);
        $sheet = (int) ($data['sheet'] ?? $batch['preview']['sheet'] ?? 0);
        abort_unless(isset($batch['sheets'][$sheet]), 404);
        $headers = $planner->headers($batch['sheets'][$sheet]);
        $mapping = isset($batch['preview']) && $batch['preview']['sheet'] === $sheet ? $batch['preview']['mapping'] : RegistryImportSchema::suggest($batch['kind'], $headers);
        return view('registry-imports.mapping', compact('batch', 'sheet', 'headers', 'mapping') + ['fields' => RegistryImportSchema::fields($batch['kind'])]);
    }

    public function prepare(Request $request, string $id, RegistryImportStore $store, RegistryImportPlanner $planner)
    {
        $data = $request->validate(['sheet' => ['required', 'integer', 'min:0', 'max:9'], 'mapping' => ['required', 'array'], 'mapping.*' => ['nullable', 'integer', 'min:0', 'max:39']]);
        $store->change($request->user()->id, $id, function (array &$batch) use ($planner, $data) {
            abort_if(isset($batch['result']), 409, 'Importazione già completata.');
            $batch['preview'] = $planner->plan($batch, (int) $data['sheet'], $data['mapping']);
        });
        return redirect()->route('registry-imports.preview', $id);
    }

    public function preview(Request $request, string $id, RegistryImportStore $store)
    {
        $batch = $store->load($request->user()->id, $id);
        if (!isset($batch['preview'])) return redirect()->route('registry-imports.mapping', $id);
        $data = $request->validate(['state' => ['nullable', Rule::in(array_keys(RegistryImportSchema::STATES))], 'search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        $filtered = array_values(array_filter($batch['preview']['rows'], fn ($row) => (empty($data['state']) || $row['state'] === $data['state']) && (empty($data['search']) || str_contains(RegistryImportSchema::key(implode(' ', $row['values'])), RegistryImportSchema::key($data['search'])))));
        $page = (int) ($data['page'] ?? 1);
        $perPage = \App\Support\TableListing::perPage(25);
        $rows = new LengthAwarePaginator(array_slice($filtered, ($page - 1) * $perPage, $perPage), count($filtered), $perPage, $page, ['path' => route('registry-imports.preview', $id), 'query' => $request->query()]);
        return response()->view('registry-imports.preview', compact('batch', 'rows') + ['states' => RegistryImportSchema::STATES, 'fields' => RegistryImportSchema::fields($batch['kind'])])->header('Cache-Control', 'private, no-store');
    }

    public function confirm(Request $request, string $id, RegistryImportStore $store, RegistryImportCommitter $committer)
    {
        $data = $request->validate(['confirm' => ['accepted'], 'hash' => ['required', 'string', 'size:64']], ['confirm.accepted' => 'Conferma di avere controllato l’anteprima prima di importare.', 'hash.*' => 'Anteprima non valida: ricalcolala prima di continuare.']);
        $store->change($request->user()->id, $id, function (array &$batch) use ($request, $data, $committer) {
            $batch['result'] = $committer->confirm($batch, $request->user()->id, $data['hash']);
        });
        return redirect()->route('registry-imports.preview', $id)->with('success', 'Importazione completata. Le voci già presenti sono rimaste invariate.');
    }

    public function discard(Request $request, string $id, RegistryImportStore $store)
    {
        $store->discard($request->user()->id, $id);
        return redirect()->route('registry-imports.index')->with('success', 'File di preparazione rimosso. Nessuna anagrafica è stata eliminata.');
    }

    public function template(string $kind)
    {
        abort_unless(isset(RegistryImportSchema::KINDS[$kind]), 404);
        return $this->csv('modello-'.$kind.'.csv', [array_column(RegistryImportSchema::fields($kind), 'label')]);
    }

    public function report(Request $request, string $id, RegistryImportStore $store)
    {
        $batch = $store->load($request->user()->id, $id);
        abort_unless(isset($batch['preview']), 404);
        $fields = RegistryImportSchema::fields($batch['kind']);
        $rows = [['Riga originale', 'Esito', 'Motivo', ...array_column($fields, 'label')]];
        foreach ($batch['preview']['rows'] as $row) $rows[] = [$row['row'], RegistryImportSchema::ROW_STATES[$row['state']], implode(' ', $row['messages']), ...array_map(fn ($field) => $row['values'][$field] ?? '', array_keys($fields))];
        return $this->csv('controllo-importazione.csv', $rows);
    }

    private function csv(string $filename, array $rows)
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                /** Spreadsheet applications must not interpret imported content as formulas when exporting the review. */
                $safe = array_map(fn ($value) => preg_match('/^[\s\x00-\x1F]*[=+@-]/u', (string) $value) ? "'".$value : (string) $value, $row);
                fputcsv($handle, $safe, ';', '"', '');
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
