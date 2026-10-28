function initQuoteVehicles() {
    document.querySelectorAll('[data-quote-vehicle]').forEach(root => {
        if (root.dataset.bound) return;
        root.dataset.bound = 'true';
        const form = root.closest('form'), vehicle = form.elements.vehicle_id, cu = form.elements.merit_class_cu;
        const internal = form.elements.merit_class_internal, group = form.elements.quote_recipient_group_id;
        const purpose = form.elements.purpose, currentPolicy = form.elements.current_policy_id;
        const branch = form.elements.insurance_branch_id, subbranch = form.elements.insurance_subbranch_id;
        const description = root.querySelector('[data-quote-group-description]');
        const choices = [...group.options].filter(option => option.value).map(option => option.cloneNode(true));
        const quick = root.querySelector('[data-quick-vehicle]'), save = root.querySelector('[data-save-vehicle]');
        function refreshGroups() {
            const previous = group.value, addition = purpose.value === 'addition';
            const matching = choices.filter(option => branch.value && option.dataset.branch === branch.value && (!option.dataset.subbranch || option.dataset.subbranch === subbranch.value) && (!option.dataset.from || (cu.value && +cu.value >= +option.dataset.from && +cu.value <= +option.dataset.to)));
            group.replaceChildren(new Option('Nessun gruppo selezionato',''));
            for (const option of matching) group.add(option.cloneNode(true));
            if (matching.some(option => option.value === previous)) group.value = previous;
            else if (matching.length === 1) group.value = matching[0].value;
            group.disabled = addition;
            group.required = !addition && matching.length > 0;
            vehicle.disabled = addition;
            quick.hidden = addition;
            if (addition) { group.value = ''; description.textContent = 'Le coperture aggiuntive si richiedono alla compagnia della polizza attuale.'; }
            else if (group.value) description.textContent = `Compagnie ammesse: ${group.selectedOptions[0].dataset.companies || 'nessuna compagnia attiva'}. Controlla la selezione prima di aprire la pratica.`;
            else if (matching.length > 1) description.textContent = 'Più gruppi corrispondono a ramo, sottoramo e classe: scegli quello da usare.';
            else description.textContent = branch.value ? 'Nessun gruppo configurato per ramo, sottoramo e classe indicati. Verranno usate le normali priorità dell’agenzia.' : 'Scegli prima il ramo assicurativo. La classe CU serve soltanto per i gruppi che la richiedono.';
        }
        function selectVehicle() {
            const option = vehicle.selectedOptions[0];
            cu.value = option?.dataset.cu || '';
            internal.value = option?.dataset.internal || '';
            refreshGroups();
        }
        function selectPolicy() {
            const option = currentPolicy.selectedOptions[0];
            if (option?.value) {
                vehicle.value = option.dataset.vehicle || '';
                cu.value = option.dataset.cu || '';
                internal.value = option.dataset.internal || '';
            }
            refreshGroups();
        }
        vehicle.addEventListener('change', selectVehicle);
        currentPolicy.addEventListener('change', selectPolicy);
        branch.addEventListener('change', () => requestAnimationFrame(refreshGroups));
        subbranch.addEventListener('change', refreshGroups);
        new MutationObserver(() => requestAnimationFrame(refreshGroups)).observe(subbranch, {childList:true,subtree:true});
        purpose.addEventListener('change', () => { if (purpose.value === 'addition') selectPolicy(); else refreshGroups(); });
        cu.addEventListener('change', refreshGroups);
        group.addEventListener('change', refreshGroups);
        form.addEventListener('client-context-cleared', () => {
            cu.value = ''; internal.value = ''; group.value = ''; quick.open = false;
            root.querySelectorAll('[data-quick-vehicle-field]').forEach(field => field.value = '');
            form.querySelectorAll('input[type="file"]').forEach(input => { input.value = ''; input.dispatchEvent(new Event('change')); });
            refreshGroups();
        });
        save.addEventListener('click', async () => {
            const clientId = form.elements.client_id.value, error = root.querySelector('[data-quick-vehicle-error]');
            if (!clientId) { error.textContent = 'Seleziona o crea prima il cliente.'; return; }
            const data = Object.fromEntries([...root.querySelectorAll('[data-quick-vehicle-field]')].map(field => [field.dataset.quickVehicleField, field.value.trim() || null]));
            data.merit_class_cu = cu.value || null;
            save.disabled = true; error.textContent = '';
            try {
                const response = await fetch(`${root.dataset.clientBase}/${encodeURIComponent(clientId)}/vehicles`, {method:'POST', credentials:'same-origin', headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify(data)});
                const body = await response.json();
                if (!response.ok) { error.textContent = Object.values(body.errors || {}).flat().join(' ') || 'Impossibile salvare il veicolo.'; return; }
                if (form.elements.client_id.value !== clientId) { error.textContent = 'Il veicolo è stato salvato nel cliente precedente. Riseleziona il cliente per ritrovarlo.'; return; }
                const option = new Option([body.plate,body.make,body.model].filter(Boolean).join(' / '),body.id);
                option.dataset.cu = body.merit_class_cu || ''; option.dataset.internal = body.merit_class_internal || ''; option.dataset.plate = body.plate;
                vehicle.add(option); vehicle.value = String(body.id); selectVehicle(); quick.open = false;
            } catch { error.textContent = 'Connessione interrotta: verifica nell’anagrafica se il veicolo è stato salvato prima di riprovare.'; }
            finally { save.disabled = false; }
        });
        if (!cu.value && vehicle.value) selectVehicle(); else refreshGroups();
    });
}
document.addEventListener('DOMContentLoaded', initQuoteVehicles);
document.addEventListener('livewire:navigated', initQuoteVehicles);
