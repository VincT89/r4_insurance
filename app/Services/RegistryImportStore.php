<?php

namespace App\Services;

use Illuminate\Support\Facades\{Crypt, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistryImportStore
{
    public function create(int $userId, string $kind, string $filename, array $sheets): string
    {
        $recent = $this->recent($userId);
        if (count($recent) >= 10) throw ValidationException::withMessages(['file' => 'Hai già 10 file in preparazione. Rimuovi un caricamento precedente prima di continuare.']);
        $id = (string) Str::uuid();
        $batch = ['id' => $id, 'owner' => $userId, 'kind' => $kind, 'filename' => mb_substr(basename(str_replace('\\', '/', $filename)), 0, 180),
            'created_at' => now()->toIso8601String(), 'expires_at' => now()->addDay()->timestamp, 'sheets' => $sheets];
        if (!Storage::disk('local')->put($this->path($userId, $id), Crypt::encryptString(json_encode($batch, JSON_THROW_ON_ERROR)))) throw new \RuntimeException('Impossibile salvare il file in preparazione.');
        return $id;
    }

    public function load(int $userId, string $id): array
    {
        return $this->access($userId, $id, fn (array &$batch) => $batch, false);
    }

    public function change(int $userId, string $id, callable $callback): mixed
    {
        return $this->access($userId, $id, $callback, true);
    }

    private function access(int $userId, string $id, callable $callback, bool $write): mixed
    {
        $path = Storage::disk('local')->path($this->path($userId, $id));
        abort_unless(is_file($path), 404);
        $handle = fopen($path, 'r+b');
        if (!$handle) abort(404);
        try {
            if (!flock($handle, $write ? LOCK_EX : LOCK_SH)) throw new \RuntimeException('File temporaneamente occupato. Riprova.');
            try { $batch = json_decode(Crypt::decryptString(stream_get_contents($handle)), true, 512, JSON_THROW_ON_ERROR); }
            catch (\Throwable) { abort(404, 'Caricamento non disponibile.'); }
            abort_unless(($batch['owner'] ?? null) === $userId && ($batch['id'] ?? null) === $id && ($batch['expires_at'] ?? 0) > now()->timestamp, 404, 'Caricamento scaduto: ricarica il file.');
            $result = $callback($batch);
            if ($write) {
                $text = Crypt::encryptString(json_encode($batch, JSON_THROW_ON_ERROR));
                rewind($handle);
                if (fwrite($handle, $text) !== strlen($text) || !ftruncate($handle, strlen($text)) || !fflush($handle)) throw new \RuntimeException('Impossibile aggiornare il caricamento.');
            }
            return $result;
        } finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    public function recent(int $userId): array
    {
        $items = [];
        foreach (Storage::disk('local')->files('registry-imports/'.$userId) as $file) {
            /** Only this feature's expired private staging files are removed; business records are never involved. */
            if (Storage::disk('local')->lastModified($file) < now()->subDay()->timestamp) { Storage::disk('local')->delete($file); continue; }
            $id = pathinfo($file, PATHINFO_FILENAME);
            try { $batch = $this->load($userId, $id); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException) { continue; }
            $items[] = array_intersect_key($batch, array_flip(['id', 'kind', 'filename', 'created_at', 'result']));
        }
        usort($items, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $items;
    }

    public function discard(int $userId, string $id): void
    {
        $this->load($userId, $id);
        Storage::disk('local')->delete($this->path($userId, $id));
    }

    private function path(int $userId, string $id): string
    {
        abort_unless(Str::isUuid($id) && $userId > 0, 404);
        return 'registry-imports/'.$userId.'/'.$id.'.json';
    }
}
