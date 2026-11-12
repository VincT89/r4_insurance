<div class="client-table-scroll policy-list-scroll" role="region" aria-label="<?php echo e(isset($documentContextPolicy) ? 'Documenti della polizza' : 'Documenti del cliente'); ?>" tabindex="0">
    <table class="t-table policy-documents-table client-document-table">
        <thead><tr><th>Documento</th><th>Categoria</th><th>Collegamento</th><th>Scadenza</th><th>Verifica e versione</th><th>Azioni</th></tr></thead>
        <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $document): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <tr>
                    <td data-label="Documento"><a href="<?php echo e($document->openUrl()); ?>" <?php if($document->canPreview()): ?> target="_blank" rel="noopener" <?php endif; ?>><?php echo e($document->original_name); ?></a><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->description): ?><div class="u-text-meta"><?php echo e($document->description); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><div class="u-text-meta">Caricato il <?php echo e($document->created_at->format('d/m/Y')); ?> da <?php echo e($document->uploader?->name ?? 'Utente non disponibile'); ?></div></td>
                    <td data-label="Categoria"><?php echo e(\App\Models\Attachment::DOCUMENT_CATEGORIES[$document->document_category] ?? 'Allegato esistente'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->identity_type): ?><div class="u-text-meta"><?php echo e(\App\Models\Attachment::IDENTITY_TYPES[$document->identity_type] ?? ''); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                    <td data-label="Collegamento">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->insurancePolicy): ?><a href="<?php echo e(route('clients.policies.show', [$client, $document->insurancePolicy])); ?>">Polizza <?php echo e($document->insurancePolicy->number); ?></a>
                        <?php elseif($document->vehicle): ?>Veicolo <?php echo e($document->vehicle->plate); ?>

                        <?php else: ?> Anagrafica cliente <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->insurance_title_id): ?><div><a href="<?php echo e(route('titles.show',$document->insurance_title_id)); ?>">Titolo #<?php echo e($document->insurance_title_id); ?></a></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->insurance_form_document_id): ?><div><a href="<?php echo e(route('clients.forms.show',[$client,$document->formDocument->insurance_form_package_id]).'#form-document-'.$document->insurance_form_document_id); ?>">Apri modulo nello storico</a></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->insurance_coverage_check_id): ?><div><a href="<?php echo e(route('clients.policies.coverage',[$client,$document->insurance_policy_id])); ?>">Controllo RCA</a></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->insurance_claim_id): ?><div><a href="<?php echo e(route('claims.show',$document->insurance_claim_id)); ?>">Sinistro #<?php echo e($document->insurance_claim_id); ?></a></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->insurance_quote_email_id): ?><div><a href="<?php echo e(route('quote-emails.show',$document->insurance_quote_email_id)); ?>">Richiesta preventivo</a></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->insurance_quote_case_id): ?><div><a href="<?php echo e(route('quotes.show',$document->insurance_quote_case_id)); ?>">Pratica preventivi</a></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->insurance_quote_id): ?><div><a href="<?php echo e(route('quotes.show',\App\Models\InsuranceQuote::find($document->insurance_quote_id)->insurance_quote_case_id)); ?>">Preventivo e approvazione</a></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td data-label="Scadenza"><?php if (isset($component)) { $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-text','data' => ['alert' => $document->expires_on?->lt(today())]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-text'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['alert' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($document->expires_on?->lt(today()))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e($document->expires_on?->format('d/m/Y') ?? 'Non indicata'); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $attributes = $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $component = $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->document_category === 'identity' || $document->expires_on): ?><div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['client-overdue' => $document->expires_on?->lt(today())]); ?>"><?php echo e($document->validity_label); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                    <td data-label="Verifica e versione">
                        <?php echo e($document->insurance_form_submission_id ? 'Copia' : 'Versione'); ?> <?php echo e($document->version_number); ?><?php echo e($document->isHistoricalDocument() ? ' / storica' : ''); ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->insurance_form_submission_id): ?>
                            <div><?php echo e(['received'=>'Copia da controllare','validated'=>'Firma controllata','rejected'=>'Correzione richiesta'][$document->review_status] ?? 'Copia ricevuta'); ?></div>
                        <?php else: ?>
                            <div><?php echo e(['generated'=>'Originale del modulo','validated'=>'Validato','rejected'=>'Da correggere'][$document->review_status] ?? ($document->insurance_claim_id ? 'Conservato nel fascicolo sinistro' : ($document->insurance_coverage_check_id ? 'Prova del controllo RCA' : 'Ricevuto, da validare'))); ?></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->validated_at): ?><div class="u-text-meta"><?php echo e($document->validated_at->format('d/m/Y H:i')); ?> / <?php echo e($document->validator?->name ?? 'Utente non disponibile'); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td data-label="Azioni"><div class="commercial-actions">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->canPreview()): ?><a class="btn btn-g btn-sm" href="<?php echo e($document->downloadUrl()); ?>" aria-label="Scarica <?php echo e($document->original_name); ?>">Scarica</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document->document_category && !$document->newerVersion && !$document->insurance_quote_case_id && !$document->insurance_quote_id && !$document->insurance_title_id && !$document->insurance_collection_id && !$document->insurance_quote_email_id && !$document->insurance_form_document_id && !$document->insurance_coverage_check_id && !$document->insurance_claim_id): ?>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('updateRegistry', $client)): ?><a href="<?php echo e(route('clients.documents.edit', [$client, $document, 'return_policy'=>($documentContextPolicy ?? null)?->id])); ?>" class="btn btn-g btn-sm" aria-label="Modifica dati di <?php echo e($document->original_name); ?>">Modifica dati</a><a class="btn btn-g btn-sm" href="<?php echo e(route('clients.documents.create', [$client, 'replace'=>$document->id, 'return_policy'=>($documentContextPolicy ?? null)?->id])); ?>">Nuova versione</a><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$document->previous_version_id && !$document->newerVersion && !$document->document_request_id && !$document->checklist_requests_exists && !$document->insurance_quote_case_id && !$document->insurance_quote_id && !$document->insurance_title_id && !$document->insurance_collection_id && !$document->insurance_quote_email_id && !$document->insurance_form_document_id && !$document->insurance_coverage_check_id && !$document->insurance_claim_id): ?>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete', $document)): ?>
                            <?php if (isset($component)) { $__componentOriginalb7eac87efb73c0c2c26fe03ec80faafd = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7eac87efb73c0c2c26fe03ec80faafd = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.delete-modal','data' => ['action' => ''.e(route('attachments.destroy', $document)).'','title' => 'Elimina documento','message' => 'Il file verrà eliminato. Confermi?']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('delete-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => ''.e(route('attachments.destroy', $document)).'','title' => 'Elimina documento','message' => 'Il file verrà eliminato. Confermi?']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                <button type="button" class="btn btn-g btn-sm" aria-label="Elimina <?php echo e($document->original_name); ?>">Elimina</button>
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7eac87efb73c0c2c26fe03ec80faafd)): ?>
<?php $attributes = $__attributesOriginalb7eac87efb73c0c2c26fe03ec80faafd; ?>
<?php unset($__attributesOriginalb7eac87efb73c0c2c26fe03ec80faafd); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7eac87efb73c0c2c26fe03ec80faafd)): ?>
<?php $component = $__componentOriginalb7eac87efb73c0c2c26fe03ec80faafd; ?>
<?php unset($__componentOriginalb7eac87efb73c0c2c26fe03ec80faafd); ?>
<?php endif; ?>
                        <?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div></td>
                </tr>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <tr><td colspan="6" class="u-empty-state">Nessun documento caricato.</td></tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
    </table>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/clients/documents/table.blade.php ENDPATH**/ ?>