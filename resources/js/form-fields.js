let generatedFieldId = 0;

function initFormFields() {
    document.querySelectorAll('[data-form-group], .form-g').forEach((group) => {
        const label = group.querySelector('[data-form-label], label.form-lbl');
        const requiredCheckboxGroup = group.querySelector('[data-required-checkbox-group]');
        const control = group.querySelector('input:not([type="hidden"]), select, textarea');

        if (!label || !control) {
            return;
        }

        if (requiredCheckboxGroup) {
            const checkboxes = [...requiredCheckboxGroup.querySelectorAll('input[type="checkbox"]')];
            const message = requiredCheckboxGroup.dataset.requiredMessage || 'Seleziona almeno un’opzione.';

            if (label.id) {
                requiredCheckboxGroup.setAttribute('aria-labelledby', label.id);
            }
            requiredCheckboxGroup.setAttribute('role', 'group');
            requiredCheckboxGroup.setAttribute('aria-required', 'true');

            const syncCheckboxValidity = () => {
                const valid = checkboxes.some((checkbox) => checkbox.checked);
                checkboxes[0]?.setCustomValidity(valid ? '' : message);
                requiredCheckboxGroup.setAttribute('aria-invalid', valid ? 'false' : 'true');
            };

            checkboxes.forEach((checkbox) => {
                if (checkbox.dataset.requiredGroupBound) return;
                checkbox.dataset.requiredGroupBound = 'true';
                checkbox.addEventListener('change', syncCheckboxValidity);
            });
            syncCheckboxValidity();

            return;
        }

        if (!control.id) {
            const baseId = group.dataset.fieldId || `form-field-${++generatedFieldId}`;
            let fieldId = baseId;

            while (document.getElementById(fieldId) && document.getElementById(fieldId) !== control) {
                fieldId = `${baseId}-${++generatedFieldId}`;
            }

            control.id = fieldId;
        }

        if (!label.contains(control)) {
            label.htmlFor = control.id;
        }

        const error = group.querySelector('.invalid-feedback');
        if (error) {
            error.id ||= `${control.id}-error`;
            control.setAttribute('aria-invalid', 'true');

            const describedBy = new Set((control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
            describedBy.add(error.id);
            control.setAttribute('aria-describedby', [...describedBy].join(' '));
        }
    });
}

document.addEventListener('DOMContentLoaded', initFormFields);
document.addEventListener('livewire:navigated', initFormFields);
document.addEventListener('livewire:initialized', () => {
    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => queueMicrotask(initFormFields));
    });
});

export { initFormFields };
