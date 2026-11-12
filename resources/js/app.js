import './password-fields.js';
import './form-fields.js';
import './dialog-accessibility.js';
import './ui-helpers.js';
import './detail-navigation.js';
import './policy-sections.js';
import './policy-desk.js';
import './document-preview-loader.js';
import './tablet-signing-loader.js';
import './template-editor-loader.js';
import './shared-checklist.js';
import './appearance.js';
import './table-tools.js';
import './collection-totals.js';
import './collection-summary.js';
import './title-summary.js';
import './communication-selection.js';
import './policy-client-picker.js';
import './policy-contract.js';
import './document-expiry.js';
import './quote-vehicle.js';
import './quote-recipients.js';
import './quote-comparison.js';
import './client-fiscal-check.js';
import './page-guide.js';
import './ai-configuration.js';
import './signing-stations.js';
import { initShell } from './app-shell.js';
import { createIcons } from 'lucide';
import { appIcons } from './app-icons.js';

import { initBgCanvas } from './bg-canvas.js';

document.addEventListener('livewire:navigating', () => {
    const root = document.querySelector('.page-transition-root');
    if (root) root.classList.add('is-navigating');
});

document.addEventListener('livewire:navigated', () => {
    const root = document.querySelector('.page-transition-root');
    if (root) root.classList.remove('is-navigating');
    
    initBgCanvas('bg-canvas');
    initShell();
    createIcons({ icons: appIcons });
});

// Fallback per non livewire pages
document.addEventListener('DOMContentLoaded', () => {
    initShell();
    createIcons({ icons: appIcons });
    if (typeof window.Livewire === 'undefined') {
        initBgCanvas('bg-canvas');
    }
});

document.addEventListener('livewire:initialized', () => {
    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            queueMicrotask(() => {
                createIcons({ icons: appIcons });
            })
        })
    });
});
