import autoprefixer from 'autoprefixer';
import scopePdfViewer from './scripts/scope-pdf-viewer.mjs';

export default {
    plugins: [scopePdfViewer(), autoprefixer()],
};
