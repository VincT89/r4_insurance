<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class RegistryImportReader
{
    public const MAX_ROWS = 2000;
    public const MAX_COLUMNS = 40;
    private int $rows = 0;
    private int $textBytes = 0;

    public function read(UploadedFile $file, string $separator = 'auto', string $encoding = 'UTF-8'): array
    {
        $this->rows = $this->textBytes = 0;
        if (!$file->isValid() || $file->getSize() > 5 * 1024 * 1024) $this->fail('Carica un file CSV o XLSX di massimo 5 MB.');
        return match (strtolower($file->getClientOriginalExtension())) {
            'csv' => $this->csv($file->getRealPath(), $separator, $encoding),
            'xlsx' => $this->xlsx($file->getRealPath()),
            default => $this->fail('Formato non supportato. Salva il file come CSV oppure Excel .xlsx (non .xls).'),
        };
    }

    private function csv(string $path, string $separator, string $encoding): array
    {
        $text = file_get_contents($path);
        if ($encoding === 'Windows-1252') $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        if (!mb_check_encoding($text, 'UTF-8') || str_contains($text, "\0")) $this->fail('Codifica CSV non riconosciuta. Usa UTF-8 oppure seleziona Windows-1252.');
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $delimiters = ['semicolon' => ';', 'comma' => ',', 'tab' => "\t"];
        if ($separator === 'auto') {
            $counts = [];
            foreach ($delimiters as $key => $delimiter) {
                $handle = $this->stream($text);
                $counts[$key] = count(fgetcsv($handle, null, $delimiter, '"', '') ?: []);
                fclose($handle);
            }
            $best = array_keys($counts, max($counts));
            if (count($best) > 1 && max($counts) > 1) $this->fail('Separatore CSV ambiguo: seleziona punto e virgola, virgola o tabulazione.');
            $separator = $best[0];
        }
        $handle = $this->stream($text);
        $rows = [];
        try {
            $number = 0;
            while (($values = fgetcsv($handle, null, $delimiters[$separator], '"', '')) !== false) {
                $number++;
                if (count($values) > self::MAX_COLUMNS) $this->fail('Il file supera il limite di 40 colonne.');
                $cells = array_map(fn ($v) => $this->cell((string) $v, 'text'), $values);
                $this->addRow($rows, $number, $cells);
            }
        } finally { fclose($handle); }
        return [['name' => 'CSV', 'rows' => $this->requireRows($rows)]];
    }

    private function stream(string $text)
    {
        $handle = fopen('php://temp', 'w+b');
        fwrite($handle, $text);
        rewind($handle);
        return $handle;
    }

    private function xlsx(string $path): array
    {
        if (!class_exists(ZipArchive::class)) $this->fail('La lettura Excel non è disponibile sul server. Usa il formato CSV.');
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) $this->fail('Il file non è una cartella Excel XLSX valida.');
        try {
            $total = 0;
            $seen = [];
            if ($zip->numFiles > 300) $this->fail('Cartella Excel troppo complessa: esporta soltanto i fogli da importare.');
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                if (isset($seen[$name]) || preg_match('~(^/|\\\\|(^|/)\.\.(/|$)|vbaProject|externalLinks)~i', $name)) $this->fail('La cartella Excel contiene elementi non consentiti. Salvala come un nuovo XLSX senza collegamenti esterni.');
                $seen[$name] = true;
                $total += $entry['size'];
                if ($entry['size'] > 8 * 1024 * 1024 || $total > 16 * 1024 * 1024) $this->fail('Contenuto Excel troppo grande. Suddividi il file.');
            }
            $book = $this->xml($zip, 'xl/workbook.xml');
            $rels = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
            $targets = [];
            foreach ($rels->query('//*[local-name()="Relationship"]') as $rel) {
                if ($rel->getAttribute('TargetMode') === 'External') $this->fail('I collegamenti esterni non sono supportati.');
                $target = $rel->getAttribute('Target');
                if (str_ends_with($rel->getAttribute('Type'), '/worksheet')) {
                    $target = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
                    if (!preg_match('~^xl/worksheets/[^/\\\\]+\.xml$~', $target)) $this->fail('Percorso di un foglio Excel non valido.');
                    $targets[$rel->getAttribute('Id')] = $target;
                }
            }
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($this->elements($zip, 'xl/sharedStrings.xml', 'si', 1) as $node) {
                    $strings[] = $this->textNodes($node);
                    if (count($strings) > 80400) $this->fail('Troppe celle di testo nella cartella Excel.');
                }
            }
            $sheets = [];
            foreach ($book->query('//*[local-name()="sheets"]/*[local-name()="sheet"]') as $sheet) {
                if (count($sheets) >= 10) $this->fail('Sono ammessi al massimo 10 fogli per file.');
                $relId = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                $target = $targets[$relId] ?? null;
                if (!$target) $this->fail('Un foglio Excel non può essere letto. Salva nuovamente il file come XLSX.');
                $rows = [];
                $previous = 0;
                foreach ($this->elements($zip, $target, 'row', 2) as $row) {
                    $number = (int) $row->getAttribute('r');
                    if ($number <= $previous) $this->fail('Numerazione delle righe Excel non valida.');
                    $previous = $number;
                    $cells = [];
                    foreach ($row->childNodes as $c) {
                        if ($c->localName !== 'c') continue;
                        if (!preg_match('/^([A-Z]+)([1-9][0-9]*)$/', $c->getAttribute('r'), $match) || (int) $match[2] !== $number) $this->fail('Riferimento di cella Excel non valido.');
                        $column = 0;
                        foreach (str_split($match[1]) as $letter) $column = $column * 26 + ord($letter) - 64;
                        $value = ''; $formula = false;
                        foreach ($c->childNodes as $part) {
                            if ($part->localName === 'f') $formula = true;
                            if ($part->localName === 'v') $value = $part->textContent;
                        }
                        $type = $c->getAttribute('t');
                        if ($type === 's') {
                            if (!ctype_digit($value) || !array_key_exists((int) $value, $strings)) $this->fail('Riferimento a testo Excel non valido.');
                            $value = $strings[(int) $value];
                        } elseif ($type === 'inlineStr') $value = $this->textNodes($c);
                        if ($value === '' && !$formula) continue;
                        if ($column > self::MAX_COLUMNS || isset($cells[$column - 1])) $this->fail('Colonne Excel non valide o oltre il limite di 40.');
                        $cells[$column - 1] = $this->cell($value, $formula ? 'formula' : ($type === 'e' ? 'error' : (in_array($type, ['s', 'inlineStr', 'str']) ? 'text' : 'number')));
                    }
                    ksort($cells);
                    $this->addRow($rows, $number, $cells);
                }
                $sheets[] = ['name' => mb_substr($sheet->getAttribute('name'), 0, 100), 'rows' => $rows];
            }
            if (!$sheets || !array_filter($sheets, fn ($s) => count($s['rows']) > 1)) $this->fail('Non sono presenti intestazioni e righe da importare.');
            return $sheets;
        } finally { $zip->close(); }
    }

    private function xml(ZipArchive $zip, string $name): DOMXPath
    {
        $text = $this->xmlText($zip, $name);
        if (strlen($text) > 1024 * 1024) $this->fail('Metadati della cartella Excel troppo grandi.');
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = new DOMDocument;
            if (!$doc->loadXML($text, LIBXML_NONET | LIBXML_COMPACT) || $doc->doctype) $this->fail('Il file Excel contiene XML non valido.');
            return new DOMXPath($doc);
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }

    private function xmlText(ZipArchive $zip, string $name): string
    {
        $text = $zip->getFromName($name);
        /** Never expand entities or load external resources from an uploaded workbook. */
        if ($text === false || str_contains($text, "\0") || preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $text)) $this->fail('Struttura Excel non valida o non consentita.');
        return $text;
    }

    private function elements(ZipArchive $zip, string $name, string $element, int $depth): \Generator
    {
        $previous = libxml_use_internal_errors(true);
        $reader = new \XMLReader;
        try {
            $reader->XML($this->xmlText($zip, $name), null, LIBXML_NONET | LIBXML_COMPACT);
            $reader->setParserProperty(\XMLReader::LOADDTD, false);
            $reader->setParserProperty(\XMLReader::SUBST_ENTITIES, false);
            /** Expand only the current row or shared string, never the full worksheet into a DOM tree. */
            while ($reader->read()) if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === $element && $reader->depth === $depth) {
                $node = $reader->expand(new DOMDocument);
                if (!$node instanceof \DOMElement) $this->fail('Struttura del foglio Excel non valida.');
                yield $node;
            }
            if (array_filter(libxml_get_errors(), fn ($error) => $error->level >= LIBXML_ERR_ERROR)) $this->fail('Il file Excel contiene XML non valido.');
        } finally { $reader->close(); libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }

    private function textNodes(\DOMElement $node): string
    {
        $text = '';
        foreach ($node->getElementsByTagNameNS('*', 't') as $part) $text .= $part->textContent;
        if (mb_strlen($text) > 10000) $this->fail('Una cella supera il limite di 10.000 caratteri.');
        return $text;
    }

    private function cell(string $value, string $type): array
    {
        if (!mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) || mb_strlen($value) > 10000) $this->fail('Una cella contiene caratteri non validi o supera 10.000 caratteri.');
        $this->textBytes += strlen($value);
        if ($this->textBytes > 4 * 1024 * 1024) $this->fail('Troppo testo nel file. Suddividi l’importazione.');
        return ['value' => $value, 'type' => $type];
    }

    private function addRow(array &$rows, int $number, array $cells): void
    {
        if (!array_filter($cells, fn ($c) => trim($c['value']) !== '' || $c['type'] === 'formula')) return;
        if (++$this->rows > self::MAX_ROWS + 10) $this->fail('Sono ammesse al massimo 2.000 righe complessive oltre alle intestazioni.');
        $rows[] = ['number' => $number, 'cells' => $cells];
    }

    private function requireRows(array $rows): array
    {
        if (count($rows) < 2) $this->fail('Il file deve contenere le intestazioni e almeno una riga di dati.');
        if (count($rows) > self::MAX_ROWS + 1) $this->fail('Sono ammesse al massimo 2.000 righe di dati.');
        return $rows;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
