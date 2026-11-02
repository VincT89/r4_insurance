export default function scopePdfViewer() {
    return {
        postcssPlugin: 'scope-pdf-viewer',
        Once(root) {
            if (!/[/\\]pdfjs-dist[/\\]web[/\\]pdf_viewer\.css$/.test(root.source?.input.file ?? '')) return;
            /** Le classi generiche di PDF.js, come sidebar, non devono modificare il gestionale. */
            root.walkRules(rule => {
                for (let parent = rule.parent; parent && parent !== root; parent = parent.parent) {
                    if (parent.type === 'rule' || (parent.type === 'atrule' && /keyframes$/i.test(parent.name))) return;
                }
                rule.selectors = rule.selectors.map(selector => selector.includes(':root')
                    ? selector.replaceAll(':root', '.document-preview')
                    : `.document-preview ${selector}`);
            });
        },
    };
}
