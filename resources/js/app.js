import './password-fields.js';
import './form-fields.js';
import './dialog-accessibility.js';
import './ui-helpers.js';
import './shared-checklist.js';
import './appearance.js';
import './table-tools.js';
import './communication-selection.js';
import './policy-client-picker.js';
import './document-expiry.js';
import './quote-vehicle.js';
import './registration-reader.js';
import { initShell } from './app-shell.js';
import { createIcons, icons } from 'lucide';

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
    createIcons({ icons });
});

// Fallback per non livewire pages
document.addEventListener('DOMContentLoaded', () => {
    initShell();
    createIcons({ icons });
    if (typeof window.Livewire === 'undefined') {
        initBgCanvas('bg-canvas');
    }
});

document.addEventListener('livewire:initialized', () => {
    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            queueMicrotask(() => {
                createIcons({ icons });
            })
        })
    });
});
