const rgb = hex => hex.slice(1).match(/../g).map(value => parseInt(value, 16));
const luminance = hex => rgb(hex).map(value => {
    value /= 255;
    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
}).reduce((sum, value, index) => sum + value * [0.2126, 0.7152, 0.0722][index], 0);
const contrast = (a, b) => (Math.max(luminance(a), luminance(b)) + 0.05) / (Math.min(luminance(a), luminance(b)) + 0.05);
const readable = (preferred, background, minimum = 4.5) => contrast(preferred, background) >= minimum ? preferred : (contrast('#000000', background) >= contrast('#ffffff', background) ? '#000000' : '#ffffff');
const mix = (a, b, weight) => '#' + rgb(a).map((value, index) => Math.round(value * weight + rgb(b)[index] * (1 - weight)).toString(16).padStart(2, '0')).join('');
const validColor = value => /^#[0-9a-f]{6}$/i.test(value);

/** Il contrasto dell’anteprima deve coincidere con quello calcolato da Support/Appearance. */
function variables(colors) {
    colors = { ...colors, section: colors.section || mix(colors.primary, colors.panel, 0.16), section_end: colors.section_end || mix(colors.primary, colors.panel, 0.04) };
    const vars = {};
    for (const key of Object.keys(colors).filter(key => !['primary', 'primary_hover', 'heading', 'link_hover', 'text'].includes(key))) {
        const background = colors[key];
        const prefix = '--ap-' + key.replaceAll('_', '-');
        const text = readable(colors.text, background);
        vars[prefix] = background;
        vars[prefix + '-text'] = text;
        vars[prefix + '-muted'] = readable(mix(text, background, 0.72), background);
        vars[prefix + '-line'] = mix(text, background, 0.2);
        vars[prefix + '-border'] = readable(mix(text, background, 0.55), background, 3);
        vars[prefix + '-soft'] = mix(background, text, 0.94);
        vars[prefix + '-heading'] = readable(colors.heading, background);
        vars[prefix + '-link-hover'] = readable(colors.link_hover, background);
        vars[prefix + '-accent'] = readable(colors.primary, background);
        for (const [name, color] of Object.entries({ red: '#b42323', green: '#147a52', blue: '#2459b8', orange: '#a33b00', purple: '#6d4db7', teal: '#0f766e', yellow: '#854d0e' })) {
            vars[prefix + '-' + name] = readable(color, background);
        }
    }
    vars['--ap-primary'] = colors.primary;
    vars['--ap-primary-text'] = readable('#ffffff', colors.primary);
    vars['--ap-primary-hover'] = colors.primary_hover;
    vars['--ap-primary-hover-text'] = readable(vars['--ap-primary-text'], vars['--ap-primary-hover']);
    vars['--ap-primary-rgb'] = rgb(colors.primary).join(', ');
    vars['--ap-section-gradient-end'] = contrast(vars['--ap-section-heading'], colors.section_end) >= 4.5 ? colors.section_end : colors.section;
    return vars;
}

function initAppearance() {
    const form = document.querySelector('[data-appearance-editor]');
    if (!form || form.dataset.initialized) return;
    form.dataset.initialized = 'true';
    const saved = JSON.parse(form.dataset.saved);
    const defaults = JSON.parse(form.dataset.defaults);
    const values = [...form.querySelectorAll('[data-color-value]')];
    const status = form.querySelector('[data-appearance-status]');
    const apply = colors => Object.entries(variables(colors)).forEach(([name, value]) => document.body.style.setProperty(name, value));
    const preview = () => {
        const colors = {};
        let valid = true;
        for (const input of values) {
            const ok = validColor(input.value);
            input.setCustomValidity(ok ? '' : 'Usa un colore nel formato #123abc.');
            if (!ok) { valid = false; continue; }
            colors[input.dataset.colorValue] = input.value.toLowerCase();
            form.querySelector(`[data-color-picker="${input.dataset.colorValue}"]`).value = input.value;
        }
        if (!valid) {
            status.textContent = 'Completa i codici colore per aggiornare l’anteprima.';
            return;
        }
        apply(colors);
        status.textContent = values.every(input => colors[input.dataset.colorValue] === saved[input.dataset.colorValue]) ? 'Colori salvati.' : 'Anteprima non salvata. Usa Salva aspetto per conservarla.';
    };
    const setColors = colors => {
        form.dataset.previousPrimary = colors.primary;
        form.dataset.previousPanel = colors.panel;
        values.forEach(input => { input.value = colors[input.dataset.colorValue]; });
        preview();
    };
    form.addEventListener('input', event => {
        const key = event.target.dataset.colorPicker;
        const changed = key || event.target.dataset.colorValue;
        if (['primary','panel'].includes(changed) && validColor(event.target.value)) {
            const previousPrimary = form.dataset.previousPrimary || saved.primary;
            const previousPanel = form.dataset.previousPanel || saved.panel;
            const nextPrimary = changed === 'primary' ? event.target.value : previousPrimary;
            const nextPanel = changed === 'panel' ? event.target.value : previousPanel;
            for (const [shade, weight] of [['section',0.16],['section_end',0.04]]) {
                const input = form.querySelector(`[data-color-value="${shade}"]`);
                if (input && input.value.toLowerCase() === mix(previousPrimary,previousPanel,weight)) input.value = mix(nextPrimary,nextPanel,weight);
            }
            form.dataset.previousPanel = nextPanel;
        }
        if (changed === 'primary' && validColor(event.target.value)) {
            const border = form.querySelector('[data-color-value="form_border"]');
            const previous = form.dataset.previousPrimary || saved.primary;
            if (border && border.value.toLowerCase() === previous.toLowerCase()) border.value = event.target.value;
            form.dataset.previousPrimary = event.target.value;
        }
        if (key) form.querySelector(`[data-color-value="${key}"]`).value = event.target.value;
        if (key || event.target.dataset.colorValue) preview();
    });
    form.querySelector('[data-appearance-cancel]').addEventListener('click', () => setColors(saved));
    form.querySelector('[data-appearance-defaults]').addEventListener('click', () => setColors(defaults));
    const restore = () => apply(saved);
    document.addEventListener('livewire:navigating', restore, { once: true });
    preview();
}

document.addEventListener('DOMContentLoaded', initAppearance);
document.addEventListener('livewire:navigated', initAppearance);
