<?php

namespace App\Services;

use RuntimeException;

class BackupFiles
{
    public function normalized(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    public function inside(string $path, string $root): bool
    {
        $path = $this->normalized($path); $root = $this->normalized($root);
        if (PHP_OS_FAMILY === 'Windows') { $path = strtolower($path); $root = strtolower($root); }
        return $path === $root || str_starts_with($path, $root.'/');
    }

    public function absolute(string $path): string
    {
        if (!preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~D', $path) || preg_match('~(?:^|[\\\\/])\\.\\.(?:[\\\\/]|$)~', $path)) {
            throw new RuntimeException('Richiesto un percorso assoluto senza risalite.');
        }
        $cursor = $path;
        while (!file_exists($cursor)) {
            if (is_link($cursor) || dirname($cursor) === $cursor) throw new RuntimeException('Percorso non disponibile.');
            $cursor = dirname($cursor);
        }
        $check = $cursor;
        while (dirname($check) !== $check) {
            if (is_link($check)) throw new RuntimeException('I collegamenti simbolici non sono ammessi nelle destinazioni.');
            $check = dirname($check);
        }
        $real = realpath($cursor);
        if ($real === false) throw new RuntimeException('Percorso non risolvibile.');
        return $this->normalized($real).substr($this->normalized($path), strlen($this->normalized($cursor)));
    }

    public function relative(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, ':') || str_contains($path, "\0") || str_starts_with($path, '/')) {
            throw new RuntimeException('Percorso documento non valido.');
        }
        foreach (explode('/', $path) as $part) if ($part === '' || $part === '.' || $part === '..') throw new RuntimeException('Percorso documento non valido.');
        return $path;
    }

    public function external(string $path, array $excluded): string
    {
        $path = $this->absolute($path);
        foreach ($excluded as $root) {
            $root = $this->absolute($root);
            if ($this->inside($path, $root) || $this->inside($root, $path)) throw new RuntimeException('La destinazione deve essere separata dal progetto e dagli archivi.');
        }
        return $path;
    }

    public function copyTree(string $source, string $destination, $manifest, string $prefix, bool $linkFiles=false): array
    {
        $count=0; $bytes=0;
        if (!is_dir($destination) && !mkdir($destination, 0700, true)) throw new RuntimeException('Cartella di lavoro non disponibile.');
        if (!is_dir($source)) return compact('count', 'bytes');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($entry->isLink() || !$entry->isFile()) throw new RuntimeException('Archivio con collegamenti o file non regolari.');
            $path = $entry->getPathname();
            $relative = $this->relative(substr($this->normalized($path), strlen($this->normalized($source))+1));
            /** Resolve every source so a junction cannot copy files outside the configured archive. */
            if (!$this->inside((string)realpath($path), $source)) throw new RuntimeException('Documento esterno alla cartella configurata.');
            $target = $destination.'/'.$relative;
            if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
            $before = hash_file('sha256', $path);
            /** With all writers stopped, hard links avoid copying unchanged files; other volumes fall back to copying. */
            $linked = $linkFiles && @link($path, $target);
            if ($before === false || (!$linked && !copy($path, $target)) || !hash_equals($before, (string)hash_file('sha256', $target))
                || !hash_equals($before, (string)hash_file('sha256', $path))) throw new RuntimeException('Documento cambiato o non leggibile durante il backup.');
            /** Permissions and timestamps on a hard link belong to the original too: never change them here. */
            if (!$linked) {
                chmod($target, 0600);
                touch($target, $entry->getMTime());
            }
            $size=filesize($target);
            fwrite($manifest, json_encode(['path'=>$prefix.'/'.$relative,'bytes'=>$size,'sha256'=>$before], JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
            $count++; $bytes+=$size;
        }
        return compact('count', 'bytes');
    }

    public function verify(string $payload, array $manifest): array
    {
        foreach (['database.sql'=>'database_sha256','recovery.json'=>'recovery_sha256','files.jsonl'=>'files_sha256'] as $file=>$key) {
            $path=$payload.'/'.$file;
            if (!is_file($path) || is_link($path) || !hash_equals($manifest[$key] ?? '', (string)hash_file('sha256',$path))) throw new RuntimeException('Backup incompleto o alterato.');
        }
        $handle=fopen($payload.'/files.jsonl','rb'); $count=0; $bytes=0;
        try {
            while (($line=fgets($handle)) !== false) {
                $entry=json_decode($line,true,512,JSON_THROW_ON_ERROR);
                $relative=$this->relative($entry['path']);
                if (!str_starts_with($relative,'disks/')) throw new RuntimeException('Documento fuori archivio.');
                $path=$payload.'/'.$relative;
                if (!is_file($path) || is_link($path) || !$this->inside((string)realpath($path),$payload)
                    || filesize($path)!==$entry['bytes'] || !hash_equals($entry['sha256'],(string)hash_file('sha256',$path))) {
                    throw new RuntimeException('Un documento ripristinato manca o non corrisponde.');
                }
                $count++; $bytes+=$entry['bytes'];
            }
        } finally { fclose($handle); }
        if ($count!==$manifest['files'] || $bytes!==$manifest['file_bytes']) throw new RuntimeException('Conteggio documenti non corrispondente.');
        return compact('count','bytes');
    }

    public function removePayload(string $work): void
    {
        $work=$this->absolute($work); $target=$work.'/payload';
        if (!is_dir($target)) return;
        if (is_link($target) || !$this->inside((string)realpath($target),$work)) throw new RuntimeException('Pulizia della cartella di lavoro interrotta.');
        $entries=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($target,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($entries as $entry) {
            if (!$this->inside($entry->getPathname(),$target)) throw new RuntimeException('Percorso di pulizia inatteso.');
            if ($entry->isLink() || $entry->isFile()) { if (!unlink($entry->getPathname())) throw new RuntimeException('File temporaneo non eliminato.'); }
            elseif (!rmdir($entry->getPathname())) throw new RuntimeException('Cartella temporanea non eliminata.');
        }
        if (!rmdir($target)) throw new RuntimeException('Cartella temporanea non eliminata.');
    }
}
