<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($winner): ?>
    <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => $winner->status === 'converted' ? ($case->purpose === 'addition' ? 'Coperture aggiuntive registrate' : 'Polizza registrata') : 'Proposta approvata dal cliente','padded' => true,'id' => $activeQuote?->id === $winner->id ? null : 'proposal-'.$winner->id]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($winner->status === 'converted' ? ($case->purpose === 'addition' ? 'Coperture aggiuntive registrate' : 'Polizza registrata') : 'Proposta approvata dal cliente'),'padded' => true,'id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($activeQuote?->id === $winner->id ? null : 'proposal-'.$winner->id)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

        <div class="proposal-outcome">
            <div>
                <strong><?php echo e($winner->company->name); ?> · <?php echo e($winner->agency?->name ?: 'Rapporto diretto'); ?></strong>
                <p><?php echo e($winner->product); ?> · <?php echo e($winner->premium_cents !== null ? \App\Support\Money::format($winner->premium_cents) : 'Premio non indicato'); ?> · <?php echo e($winner->payment_frequency); ?></p>
                <p class="u-text-meta">Proposta <?php echo e($winner->reference); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($winner->approved_on): ?> · Approvata il <?php echo e($winner->approved_on->format('d/m/Y')); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
                <?php
                    $proof = $winner->documents->where('document_category', 'quote_approval');
                ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($proof->count() === 1): ?><a href="<?php echo e($proof->first()->openUrl()); ?>" target="_blank" rel="noopener">Apri prova di approvazione</a>
                <?php elseif($proof->isNotEmpty()): ?><a href="<?php echo e($proposalUrl($winner)); ?>">Prove di approvazione (<?php echo e($proof->count()); ?>)</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="proposal-actions">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($winner->policy): ?>
                    <a class="btn btn-p" href="<?php echo e(route('clients.policies.show', [$case->client_id, $winner->policy])); ?>">Apri polizza <?php echo e($winner->policy->number); ?></a>
                    <?php if (isset($component)) { $__componentOriginal38cc9becf2286c48e96bdd0a061c14cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal38cc9becf2286c48e96bdd0a061c14cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.policy-contract-link','data' => ['policy' => $winner->policy,'button' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('policy-contract-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['policy' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($winner->policy),'button' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal38cc9becf2286c48e96bdd0a061c14cf)): ?>
<?php $attributes = $__attributesOriginal38cc9becf2286c48e96bdd0a061c14cf; ?>
<?php unset($__attributesOriginal38cc9becf2286c48e96bdd0a061c14cf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal38cc9becf2286c48e96bdd0a061c14cf)): ?>
<?php $component = $__componentOriginal38cc9becf2286c48e96bdd0a061c14cf; ?>
<?php unset($__componentOriginal38cc9becf2286c48e96bdd0a061c14cf); ?>
<?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($winner->policyChange): ?>
                    <a class="btn btn-g" href="<?php echo e(route('clients.policy-changes.show', [$case->client_id, $winner->policyChange])); ?>">Apri variazione registrata</a>
                <?php elseif($winner->status === 'approved' && $case->status === 'approved' && $canManage): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$winner->valid_until): ?>
                        <span>Verifica la validità della proposta prima di procedere.</span>
                    <?php elseif($winner->valid_until->lt(today())): ?>
                        <span>Proposta scaduta il <?php echo e($winner->valid_until->format('d/m/Y')); ?>. Per procedere serve una proposta valida.</span>
                    <?php elseif($case->purpose === 'new'): ?>
                        <a class="btn btn-p" href="<?php echo e(route('policies.create', ['quote_id'=>$winner->id])); ?>">Carica contratto</a>
                    <?php elseif($case->currentPolicy): ?>
                        <a class="btn btn-p" href="<?php echo e(route('clients.policies.changes.create', [$case->client_id,$case->current_policy_id,'kind'=>$case->purpose,'quote_id'=>$winner->id])); ?>"><?php echo e($case->purpose === 'replacement' ? 'Carica contratto sostitutivo' : 'Registra coperture aggiuntive'); ?></a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $attributes = $__attributesOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $component = $__componentOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__componentOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeQuote): ?>
    <?php echo $__env->make('quotes.proposal-editor', ['quote'=>$activeQuote], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($responses->isNotEmpty()): ?>
    <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Confronto proposte','id' => 'confronto-proposte']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Confronto proposte','id' => 'confronto-proposte']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

        <div class="client-table-scroll" role="region" aria-label="Confronto proposte delle compagnie" tabindex="0">
            <table class="t-table proposal-comparison" data-list-details="proposals" data-export-only>
                <thead><tr><th>Compagnia</th><th data-list-detail>Agenzia</th><th>Prodotto</th><th data-list-detail>Riferimento</th><th><?php echo e($case->purpose === 'addition' ? 'Premio aggiuntivo' : 'Premio del periodo'); ?></th><th data-list-detail>Frazionamento</th><th>Copertura</th><th data-list-detail>Validità preventivo</th><th>Stato</th></tr></thead>
                <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $responses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $quote): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $offerDocs = $quote->documents->where('document_category', 'quote');
                        $expired = $quote->valid_until?->lt(today());
                    ?>
                    <tr data-no-list-details <?php if($activeQuote?->id !== $quote->id && $winner?->id !== $quote->id): ?> id="proposal-<?php echo e($quote->id); ?>" <?php endif; ?>>
                        <td><strong><?php echo e($quote->company->name); ?></strong><small data-table-export-ignore><?php echo e($quote->agency?->name ?: 'Rapporto diretto'); ?></small></td>
                        <td><?php echo e($quote->agency?->name ?: 'Rapporto diretto'); ?></td>
                        <td><?php echo e($quote->product ?: 'Non indicato'); ?><small data-table-export-ignore>Rif. <?php echo e($quote->reference ?: 'non indicato'); ?></small>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offerDocs->count() === 1): ?>
                                <a data-table-export-ignore href="<?php echo e($offerDocs->first()->openUrl()); ?>" target="_blank" rel="noopener" aria-label="Apri PDF della proposta <?php echo e($quote->reference); ?> (nuova scheda)">Apri PDF</a>
                            <?php elseif($offerDocs->isNotEmpty()): ?>
                                <a data-table-export-ignore href="<?php echo e($proposalUrl($quote)); ?>">Vedi <?php echo e($offerDocs->count()); ?> PDF</a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td><?php echo e($quote->reference); ?></td>
                        <td><strong><?php echo e($quote->premium_cents !== null ? \App\Support\Money::format($quote->premium_cents) : 'Non indicato'); ?></strong><small data-table-export-ignore><?php echo e($quote->payment_frequency); ?></small></td>
                        <td><?php echo e($quote->payment_frequency); ?></td>
                        <td><?php echo e($quote->starts_on && $quote->expires_on ? $quote->starts_on->format('d/m/Y').' – '.$quote->expires_on->format('d/m/Y') : 'Periodo non indicato'); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($quote->valid_until): ?><small data-table-export-ignore><?php if (isset($component)) { $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-text','data' => ['alert' => $expired && $case->status==='open']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-text'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['alert' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($expired && $case->status==='open')]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e($expired ? 'Scaduta il' : 'Valida fino al'); ?> <?php echo e($quote->valid_until->format('d/m/Y')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $attributes = $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $component = $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td><?php echo e($quote->valid_until?->format('d/m/Y')); ?></td>
                        <td><strong><?php echo e($case->purpose==='addition' && $quote->status==='converted' ? 'Aggiunta registrata' : \App\Models\InsuranceQuote::STATUSES[$quote->status]); ?></strong>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mayWork && $quote->status === 'received' && !$expired): ?>
                                <form method="POST" action="<?php echo e(route('quotes.change',[$quote,'submit'])); ?>"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="confirmed" value="1">
                                    <button class="btn btn-g btn-sm" type="submit" aria-label="Segna <?php echo e($quote->reference); ?> come presentata al cliente">Segna come presentata</button>
                                </form>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <a data-table-export-ignore class="proposal-row-link" href="<?php echo e($proposalUrl($quote)); ?>"><?php echo e($mayWork && $quote->status==='pending' ? 'Registra approvazione' : 'Dettagli proposta'); ?></a>
                        </td>
                    </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </tbody>
            </table>
        </div>
        <details class="proposal-export"><summary>Esporta confronto</summary><div data-export-controls></div></details>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $attributes = $__attributesOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $component = $__componentOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__componentOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($waiting->isNotEmpty() || $responses->isEmpty()): ?>
    <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => $case->quotes->isEmpty() && $mayWork ? 'Richiesta pronta da inviare' : ($case->status==='open' ? 'Risposte attese' : 'Richieste senza risposta'),'id' => 'risposte-attese','padded' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($case->quotes->isEmpty() && $mayWork ? 'Richiesta pronta da inviare' : ($case->status==='open' ? 'Risposte attese' : 'Richieste senza risposta')),'id' => 'risposte-attese','padded' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $waiting; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $quote): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="awaiting-proposal" <?php if($activeQuote?->id !== $quote->id): ?> id="proposal-<?php echo e($quote->id); ?>" <?php endif; ?>>
                <div><strong><?php echo e($quote->request_recipient['company_name'] ?? $quote->company->name); ?></strong> · <?php echo e($quote->request_recipient ? ($quote->request_recipient['agency_name'] ?? 'Rapporto diretto') : ($quote->agency?->name ?: 'Rapporto diretto')); ?>

                    <small>Richiesta del <?php echo e($quote->requested_at->format('d/m/Y')); ?></small>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mayWork): ?><a class="btn btn-g" href="<?php echo e($proposalUrl($quote)); ?>" aria-label="Registra risposta di <?php echo e($quote->company->name); ?> · <?php echo e($quote->agency?->name ?: 'Rapporto diretto'); ?>">Registra risposta</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($case->quotes->isEmpty() && $mayWork): ?>
                <p>Scegli <strong>Prepara email</strong> per controllare destinatari, messaggio e allegati prima dell’invio.</p>
            <?php else: ?>
                <p>Nessuna risposta registrata. Qui ritroverai le richieste inviate alle compagnie e alle agenzie.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $attributes = $__attributesOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $component = $__componentOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__componentOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/quotes/proposals.blade.php ENDPATH**/ ?>