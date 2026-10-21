<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\FirstAccessController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientMaterialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Livewire\Dashboard\UserDailyNotes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));
Route::view('/privacy-policy', 'legal.privacy-policy')->name('legal.privacy-policy');
Route::view('/terms-of-service', 'legal.terms-of-service')->name('legal.terms-of-service');

Route::get('/media/{path}', function (string $path) {
    abort_if(str_contains($path, '..') || str_contains($path, '\\'), 404);
    abort_unless(str_starts_with($path, 'clients/logos/'), 404);
    abort_unless(Storage::disk('public')->exists($path), 404);
    abort_unless(in_array(Storage::disk('public')->mimeType($path), ['image/jpeg', 'image/png', 'image/webp'], true), 404);

    return Storage::disk('public')->response($path);
})->where('path', '.*')->middleware(['signed', 'throttle:client-media'])->name('media.public');

Route::middleware('auth')->group(function () {
    Route::get('/password/setup', [FirstAccessController::class, 'show'])->name('password.setup');
    Route::post('/password/setup', [FirstAccessController::class, 'update'])->name('password.setup.update');
});

Route::middleware(['auth', 'force.password.change'])->group(function () {
    Route::prefix('claims')->name('claims.')->group(function () {
        Route::get('/',[\App\Http\Controllers\InsuranceClaimController::class,'index'])->name('index');
        Route::get('/create',[\App\Http\Controllers\InsuranceClaimController::class,'create'])->name('create');
        Route::post('/',[\App\Http\Controllers\InsuranceClaimController::class,'store'])->middleware('throttle:30,1')->name('store');
        Route::get('/{claim}',[\App\Http\Controllers\InsuranceClaimController::class,'show'])->name('show');
        Route::get('/{claim}/edit',[\App\Http\Controllers\InsuranceClaimController::class,'edit'])->name('edit');
        Route::get('/{claim}/activities/{activity}',[\App\Http\Controllers\InsuranceClaimController::class,'activity'])->name('activity');
        Route::post('/{claim}/activities/{activity}',[\App\Http\Controllers\InsuranceClaimController::class,'updateActivity'])->name('activity.update');
        Route::post('/{claim}/{action}',[\App\Http\Controllers\InsuranceClaimController::class,'change'])->where('action','updated|status|note|activity_added|activity_completed|activity_rescheduled|activity_cancelled')->middleware('throttle:60,1')->name('change');
    });
    Route::prefix('settings/forms')->name('form-templates.')->middleware('can:system.admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\InsuranceFormController::class,'templates'])->name('index');
        Route::get('/create', [\App\Http\Controllers\InsuranceFormController::class,'templateForm'])->name('create');
        Route::post('/', [\App\Http\Controllers\InsuranceFormController::class,'templateSave'])->name('store');
        Route::get('/{template}', [\App\Http\Controllers\InsuranceFormController::class,'templateForm'])->name('edit');
        Route::patch('/{template}', [\App\Http\Controllers\InsuranceFormController::class,'templateSave'])->name('update');
        Route::post('/{template}/approve', [\App\Http\Controllers\InsuranceFormController::class,'activate'])->name('approve');
        Route::post('/{template}/retire', [\App\Http\Controllers\InsuranceFormController::class,'retire'])->name('retire');
        Route::get('/{template}/preview', [\App\Http\Controllers\InsuranceFormController::class,'preview'])->middleware('throttle:20,1')->name('preview');
    });
    Route::prefix('clients/{client}/forms')->name('clients.forms.')->group(function () {
        Route::get('/', [\App\Http\Controllers\InsuranceFormController::class,'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\InsuranceFormController::class,'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\InsuranceFormController::class,'store'])->middleware('throttle:20,1')->name('store');
        Route::get('/{package}', [\App\Http\Controllers\InsuranceFormController::class,'show'])->name('show');
        Route::post('/{package}/documents/{document}/signatures', [\App\Http\Controllers\InsuranceFormController::class,'submit'])->name('submit');
        Route::post('/{package}/signatures/{submission}/review', [\App\Http\Controllers\InsuranceFormController::class,'review'])->name('review');
    });
    Route::get('clients/{client}/policies/{policy}/coverage-checks',[\App\Http\Controllers\InsuranceCoverageCheckController::class,'index'])->name('clients.policies.coverage');
    Route::post('clients/{client}/policies/{policy}/coverage-checks',[\App\Http\Controllers\InsuranceCoverageCheckController::class,'store'])->name('clients.policies.coverage.store');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/quotes',[\App\Http\Controllers\InsuranceQuoteController::class,'index'])->name('quotes.index');
    Route::get('/quotes/create',[\App\Http\Controllers\InsuranceQuoteController::class,'create'])->name('quotes.create');
    Route::post('/quotes',[\App\Http\Controllers\InsuranceQuoteController::class,'store'])->name('quotes.store');
    Route::get('/quotes/{quoteCase}',[\App\Http\Controllers\InsuranceQuoteController::class,'show'])->name('quotes.show');
    Route::post('/quotes/{quoteCase}/documents',[\App\Http\Controllers\InsuranceQuoteController::class,'documents'])->middleware('throttle:10,1')->name('quotes.documents.store');
    Route::post('/quotes/{quoteCase}/requests',[\App\Http\Controllers\InsuranceQuoteController::class,'request'])->name('quotes.request');
    Route::get('/quotes/{quoteCase}/emails/create',[\App\Http\Controllers\QuoteRequestEmailController::class,'create'])->name('quotes.emails.create');
    Route::post('/quotes/{quoteCase}/emails/review',[\App\Http\Controllers\QuoteRequestEmailController::class,'review'])->middleware('throttle:10,1')->name('quotes.emails.review');
    Route::get('/quote-emails/{message}',[\App\Http\Controllers\QuoteRequestEmailController::class,'show'])->name('quote-emails.show');
    Route::post('/quote-emails/{message}/send',[\App\Http\Controllers\QuoteRequestEmailController::class,'send'])->middleware('throttle:10,1')->name('quote-emails.send');
    Route::post('/quote-emails/{message}/resolve',[\App\Http\Controllers\QuoteRequestEmailController::class,'resolve'])->name('quote-emails.resolve');
    Route::post('/quote-proposals/{quote}/{action}',[\App\Http\Controllers\InsuranceQuoteController::class,'change'])->where('action','receive|submit|approve|reject')->name('quotes.change');
    Route::get('/titles',[\App\Http\Controllers\InsuranceLedgerController::class,'index'])->name('titles.index');
    Route::get('/titles/{title}',[\App\Http\Controllers\InsuranceLedgerController::class,'show'])->name('titles.show');
    Route::post('/titles/{title}/collections',[\App\Http\Controllers\InsuranceLedgerController::class,'collect'])->name('titles.collect');
    Route::post('/titles/{title}/documents',[\App\Http\Controllers\InsuranceLedgerController::class,'document'])->name('titles.documents');
    Route::post('/titles/{title}/cancel',[\App\Http\Controllers\InsuranceLedgerController::class,'cancel'])->middleware('can:ledger.manage')->name('titles.cancel');
    Route::get('/collections',[\App\Http\Controllers\InsuranceLedgerController::class,'collections'])->name('collections.index');
    Route::post('/collections/{collection}/void',[\App\Http\Controllers\InsuranceLedgerController::class,'void'])->middleware('can:ledger.manage')->name('collections.void');
    Route::prefix('/policies/{policy}/titles')->middleware('can:ledger.manage')->group(function(){
        Route::get('/schedule',[\App\Http\Controllers\InsuranceLedgerController::class,'schedule'])->name('titles.schedule');
        Route::post('/schedule',[\App\Http\Controllers\InsuranceLedgerController::class,'generate'])->name('titles.generate');
        Route::get('/create',[\App\Http\Controllers\InsuranceLedgerController::class,'create'])->name('titles.create');
        Route::post('/',[\App\Http\Controllers\InsuranceLedgerController::class,'store'])->name('titles.store');
        Route::get('/renewal',[\App\Http\Controllers\InsuranceLedgerController::class,'renewal'])->name('titles.renewal');
        Route::post('/renewal/preview',[\App\Http\Controllers\InsuranceLedgerController::class,'renewalPreview'])->name('titles.renewal-preview');
        Route::post('/renewal',[\App\Http\Controllers\InsuranceLedgerController::class,'renew'])->name('titles.renew');
    });
    Route::get('/policies', [\App\Http\Controllers\PolicyPortfolioController::class, 'index'])->name('policies.index');
    Route::get('/policies/create', [\App\Http\Controllers\PolicyPortfolioController::class, 'create'])->name('policies.create');
    Route::post('/policies', [\App\Http\Controllers\PolicyPortfolioController::class, 'store'])->name('policies.store');
    Route::get('/clients/{client}/policy-context', [\App\Http\Controllers\PolicyPortfolioController::class, 'clientContext'])->name('clients.policy-context');
    Route::get('/reports', \App\Http\Controllers\InsuranceReportController::class)->middleware('can:system.admin')->name('reports.index');
    Route::middleware('can:system.admin')->group(function () {
        Route::get('/reports/commissions', [\App\Http\Controllers\InsuranceCommissionController::class, 'index'])->name('commissions.index');
        Route::post('/reports/commissions/calculate', [\App\Http\Controllers\InsuranceCommissionController::class, 'calculate'])->name('commissions.calculate');
        Route::get('/titles/{title}/commission', [\App\Http\Controllers\InsuranceCommissionController::class, 'show'])->name('commissions.show');
        Route::post('/titles/{title}/commission/calculate', [\App\Http\Controllers\InsuranceCommissionController::class, 'prepare'])->name('commissions.prepare');
        Route::post('/titles/{title}/commission/movements/{kind}', [\App\Http\Controllers\InsuranceCommissionController::class, 'record'])->where('kind', 'accrual|settlement')->name('commissions.record');
        Route::post('/titles/{title}/commission/reverse/{movement}', [\App\Http\Controllers\InsuranceCommissionController::class, 'reverse'])->name('commissions.reverse');
    });
    Route::get('/agency-communications', [\App\Http\Controllers\AgencyCommunicationController::class, 'index'])->middleware('can:system.admin')->name('agency-communications.index');
    Route::get('/agency-communications/collections',[\App\Http\Controllers\AgencyCollectionCommunicationController::class,'index'])->middleware('can:system.admin')->name('agency-communications.collections');
    Route::post('/agency-communications/{agency}/collections-review',[\App\Http\Controllers\AgencyCollectionCommunicationController::class,'review'])->middleware(['can:system.admin','throttle:30,1'])->name('agency-communications.collections-review');
    Route::post('/agency-communications/{agency}/draft', [\App\Http\Controllers\AgencyCommunicationController::class, 'draft'])->middleware('can:system.admin')->name('agency-communications.draft');
    Route::get('/agency-communications/history', [\App\Http\Controllers\AgencyCommunicationController::class, 'history'])->middleware('can:system.admin')->name('agency-communications.history');
    Route::post('/agency-communications/{agency}/review', [\App\Http\Controllers\AgencyCommunicationController::class, 'review'])->middleware(['can:system.admin','throttle:30,1'])->name('agency-communications.review');
    Route::get('/agency-communications/messages/{communication}', [\App\Http\Controllers\AgencyCommunicationController::class, 'show'])->middleware('can:system.admin')->name('agency-communications.show');
    Route::post('/agency-communications/messages/{communication}/documents', [\App\Http\Controllers\AgencyCommunicationController::class, 'documents'])->middleware(['can:system.admin','throttle:10,1'])->name('agency-communications.documents');
    Route::get('/agency-communications/messages/{communication}/documents/{document}', [\App\Http\Controllers\AgencyCommunicationController::class, 'download'])->middleware('can:system.admin')->name('agency-communications.documents.download');
    Route::post('/agency-communications/messages/{communication}/send', [\App\Http\Controllers\AgencyCommunicationController::class, 'send'])->middleware(['can:system.admin','throttle:10,1'])->name('agency-communications.send');
    Route::post('/agency-communications/messages/{communication}/resolve', [\App\Http\Controllers\AgencyCommunicationController::class, 'resolve'])->middleware(['can:system.admin','throttle:10,1'])->name('agency-communications.resolve');
    Route::get('/settings/email', [\App\Http\Controllers\EmailSettingsController::class, 'edit'])->middleware('can:system.admin')->name('email-settings.edit');
    Route::patch('/settings/email', [\App\Http\Controllers\EmailSettingsController::class, 'update'])->middleware(['can:system.admin','throttle:10,1'])->name('email-settings.update');
    Route::get('/deadlines', [\App\Http\Controllers\DeadlineController::class, 'index'])->name('deadlines.index');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/settings/appearance', [\App\Http\Controllers\AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::patch('/settings/appearance', [\App\Http\Controllers\AppearanceController::class, 'update'])->name('appearance.update');
    Route::delete('/settings/appearance', [\App\Http\Controllers\AppearanceController::class, 'destroy'])->name('appearance.destroy');
    Route::get('/settings/insurance-import', [\App\Http\Controllers\InsuranceImportPreviewController::class, 'index'])->middleware('can:system.admin')->name('insurance-import.index');
    Route::get('/settings/insurance-import/review', [\App\Http\Controllers\InsuranceImportPreviewController::class, 'download'])->middleware('can:system.admin')->name('insurance-import.download');
    Route::get('/settings/insurance-import/entries/{entry}', [\App\Http\Controllers\InsuranceImportPreviewController::class, 'compare'])->middleware('can:system.admin')->name('insurance-import.compare');
    Route::get('/settings/insurance-import/policies/{entry}', [\App\Http\Controllers\InsuranceImportPreviewController::class, 'policySource'])->middleware('can:system.admin')->name('insurance-import.policy-source');

    Route::prefix('/suppliers/{supplier}/commissions')->name('suppliers.commissions.')->group(function () {
        Route::get('/',[\App\Http\Controllers\AgencyCommissionController::class,'index'])->name('index');
        Route::get('/create',[\App\Http\Controllers\AgencyCommissionController::class,'create'])->name('create');
        Route::post('/',[\App\Http\Controllers\AgencyCommissionController::class,'store'])->name('store');
        Route::get('/{schedule}',[\App\Http\Controllers\AgencyCommissionController::class,'show'])->name('show');
        Route::get('/{schedule}/edit',[\App\Http\Controllers\AgencyCommissionController::class,'edit'])->name('edit');
        Route::patch('/{schedule}',[\App\Http\Controllers\AgencyCommissionController::class,'update'])->name('update');
        Route::post('/{schedule}/void',[\App\Http\Controllers\AgencyCommissionController::class,'void'])->name('void');
    });
    Route::resource('suppliers', \App\Http\Controllers\SupplierAgencyController::class);
    Route::get('/settings/insurance-catalog', [\App\Http\Controllers\InsuranceCatalogController::class, 'index'])->name('insurance-catalog.index');
    Route::prefix('/settings/insurance-catalog/{kind}')->where(['kind'=>'branch|subbranch'])->name('insurance-catalog.')->group(function () {
        Route::get('/create', [\App\Http\Controllers\InsuranceCatalogController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\InsuranceCatalogController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [\App\Http\Controllers\InsuranceCatalogController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::patch('/{id}', [\App\Http\Controllers\InsuranceCatalogController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\InsuranceCatalogController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });
    Route::resource('supplier-companies', \App\Http\Controllers\SupplierCompanyController::class)->parameters(['supplier-companies' => 'company']);
    Route::middleware(\App\Http\Middleware\UseCurrentClient::class)->group(function () {
    Route::resource('clients', ClientController::class);
    Route::get('clients/{client}/duplicates', [\App\Http\Controllers\ClientMergeController::class, 'duplicates'])->name('clients.duplicates');
    Route::get('clients/{client}/merge', [\App\Http\Controllers\ClientMergeController::class, 'preview'])->name('clients.merge.preview');
    Route::post('clients/{client}/merge', [\App\Http\Controllers\ClientMergeController::class, 'store'])->name('clients.merge.store');
    Route::resource('clients.policies', \App\Http\Controllers\InsurancePolicyController::class)->except('index');
    Route::get('clients/{client}/policies/{policy}/changes/create', [\App\Http\Controllers\InsurancePolicyChangeController::class,'create'])->name('clients.policies.changes.create');
    Route::post('clients/{client}/policies/{policy}/changes', [\App\Http\Controllers\InsurancePolicyChangeController::class,'store'])->name('clients.policies.changes.store');
    Route::get('clients/{client}/policy-changes/{change}', [\App\Http\Controllers\InsurancePolicyChangeController::class,'show'])->name('clients.policy-changes.show');
    Route::post('clients/{client}/policy-changes/{change}/documents', [\App\Http\Controllers\InsurancePolicyChangeController::class,'documents'])->middleware('throttle:10,1')->name('clients.policy-changes.documents');
    Route::post('clients/{client}/policies/{policy}/close-work', [\App\Http\Controllers\InsurancePolicyWorkflowController::class, 'close'])->name('clients.policies.close-work');
    Route::post('clients/{client}/policies/{policy}/corrections', [\App\Http\Controllers\InsurancePolicyWorkflowController::class, 'report'])->middleware('throttle:20,1')->name('clients.policies.corrections.store');
    Route::post('clients/{client}/policies/{policy}/corrections/{correction}/resolve', [\App\Http\Controllers\InsurancePolicyWorkflowController::class, 'resolve'])->name('clients.policies.corrections.resolve');
    Route::resource('clients.vehicles', \App\Http\Controllers\ClientVehicleController::class)->except(['index', 'show']);
    Route::resource('clients.documents', \App\Http\Controllers\ClientDocumentController::class)->except(['index', 'show', 'destroy']);
    Route::post('clients/{client}/documents/{document}/validate', [\App\Http\Controllers\ClientDocumentController::class, 'validateDocument'])->name('clients.documents.validate');
    Route::get('clients/{client}/document-requests/create', [\App\Http\Controllers\ClientDocumentRequestController::class, 'create'])->name('clients.document-requests.create');
    Route::post('clients/{client}/document-requests', [\App\Http\Controllers\ClientDocumentRequestController::class, 'store'])->name('clients.document-requests.store');
    Route::post('clients/{client}/document-requests/checklist', [\App\Http\Controllers\ClientDocumentRequestController::class, 'apply'])->name('clients.document-requests.apply');
    Route::get('clients/{client}/document-requests/{documentRequest}/edit', [\App\Http\Controllers\ClientDocumentRequestController::class, 'edit'])->name('clients.document-requests.edit');
    Route::patch('clients/{client}/document-requests/{documentRequest}', [\App\Http\Controllers\ClientDocumentRequestController::class, 'update'])->name('clients.document-requests.update');
    Route::post('clients/{client}/document-requests/{documentRequest}/files', [\App\Http\Controllers\ClientDocumentRequestController::class, 'link'])->name('clients.document-requests.link');
    Route::delete('clients/{client}/document-requests/{documentRequest}', [\App\Http\Controllers\ClientDocumentRequestController::class, 'destroy'])->name('clients.document-requests.destroy');
    Route::get('clients/{client}/materials', [ClientMaterialController::class, 'index'])->name('clients.materials.index');
    Route::post('clients/{client}/materials', [ClientMaterialController::class, 'store'])->name('clients.materials.store');
    Route::get('clients/{client}/records/{section}/create', [\App\Http\Controllers\ClientRecordController::class, 'create'])->name('clients.records.create');
    Route::post('clients/{client}/records/{section}', [\App\Http\Controllers\ClientRecordController::class, 'store'])->name('clients.records.store');
    Route::get('clients/{client}/records/{section}/{record}/edit', [\App\Http\Controllers\ClientRecordController::class, 'edit'])->whereNumber('record')->name('clients.records.edit');
    Route::patch('clients/{client}/records/{section}/{record}', [\App\Http\Controllers\ClientRecordController::class, 'update'])->whereNumber('record')->name('clients.records.update');
    Route::delete('clients/{client}/records/{section}/{record}', [\App\Http\Controllers\ClientRecordController::class, 'destroy'])->whereNumber('record')->name('clients.records.destroy');
    Route::get('clients/{client}/relationships/create', [\App\Http\Controllers\ClientNetworkController::class, 'relationshipForm'])->name('clients.relationships.create');
    Route::post('clients/{client}/relationships', [\App\Http\Controllers\ClientNetworkController::class, 'saveRelationship'])->name('clients.relationships.store');
    Route::get('clients/{client}/relationships/{relationship}/edit', [\App\Http\Controllers\ClientNetworkController::class, 'relationshipForm'])->name('clients.relationships.edit');
    Route::patch('clients/{client}/relationships/{relationship}', [\App\Http\Controllers\ClientNetworkController::class, 'saveRelationship'])->name('clients.relationships.update');
    Route::delete('clients/{client}/relationships/{relationship}', [\App\Http\Controllers\ClientNetworkController::class, 'deleteRelationship'])->name('clients.relationships.destroy');
    Route::get('clients/{client}/households/create', [\App\Http\Controllers\ClientNetworkController::class, 'householdForm'])->name('clients.households.create');
    Route::post('clients/{client}/households', [\App\Http\Controllers\ClientNetworkController::class, 'saveHousehold'])->name('clients.households.store');
    Route::get('clients/{client}/households/{household}/edit', [\App\Http\Controllers\ClientNetworkController::class, 'householdForm'])->name('clients.households.edit');
    Route::patch('clients/{client}/households/{household}', [\App\Http\Controllers\ClientNetworkController::class, 'saveHousehold'])->name('clients.households.update');
    Route::delete('clients/{client}/households/{household}', [\App\Http\Controllers\ClientNetworkController::class, 'deleteHousehold'])->name('clients.households.destroy');
    });
    Route::get('/api/clients/search', [ClientController::class, 'search'])->name('api.clients.search');
    Route::post('/api/clients/quick-store', [ClientController::class, 'quickStore'])->name('api.clients.quick-store');

    Route::resource('users', UserController::class)->except(['show']);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');

    Route::post('/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('can:system.admin')->name('audit-logs.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsReadAndRedirect'])->name('notifications.read');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::get('/daily-notes', UserDailyNotes::class)->name('daily-notes.index');
});

require __DIR__.'/auth.php';
