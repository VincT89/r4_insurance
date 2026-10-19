<?php
$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($root.'/public/'.$path);
if ($path !== '/' && $file && str_starts_with($file, realpath($root.'/public').DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
define('LARAVEL_START', microtime(true));
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || dirname(config('database.connections.sqlite.database')) !== __DIR__) {
    throw new RuntimeException('Configurazione di prova inattesa.');
}
if (getenv('AUDIT_FIXED_DATE') && app()->environment('testing')) Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse(getenv('AUDIT_FIXED_DATE'), 'Europe/Rome'));
if (getenv('WORKFLOW_QA') === '1' && app()->environment('testing')) {
    Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse(file_get_contents(config('database.connections.sqlite.database').'.clock'), 'Europe/Rome'));
}
if (! is_dir(__DIR__.'/sessions')) mkdir(__DIR__.'/sessions');
config(['filesystems.disks.attachments.root' => __DIR__.'/attachments', 'session.files' => __DIR__.'/sessions']);
if (getenv('AGENCY_EMAIL_QA') === '1' && app()->environment('testing')) {
    $app->bind(App\Services\AgencyEmailTransport::class, fn () => new class extends App\Services\AgencyEmailTransport {
        public function send(array $payload, array $configuration): string { return parent::send($payload, ['transport'=>'array']); }
    });
}
if (getenv('QUOTE_EMAIL_QA') === '1' && app()->environment('testing')) {
    /** Isolated browser checks build the actual message while preventing every external SMTP connection. */
    $app->bind(App\Services\QuoteRequestEmailTransport::class, fn () => new class extends App\Services\QuoteRequestEmailTransport {
        public function send(array $payload, array $configuration, array $files): string { return parent::send($payload, ['transport'=>'array'], $files); }
    });
}
if (getenv('POLICY_IMPORT_QA') === '1' || getenv('EQUIVALENT_IMPORT_QA') === '1') config(['insurance-import.directory' => config('database.connections.sqlite.database').'.imports']);
// Use the compiled local bundle; a developer's Vite server belongs to the real app.
$app->make(Illuminate\Foundation\Vite::class)->useHotFile(__DIR__.'/unused-hot');
$app->handleRequest(Illuminate\Http\Request::capture());
