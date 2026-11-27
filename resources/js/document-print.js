async function loadPdfRenderer() {
    const [pdf, worker] = await Promise.all([
        import('pdfjs-dist/build/pdf.min.mjs'),
        import('pdfjs-dist/build/pdf.worker.min.mjs?url'),
    ]);
    pdf.GlobalWorkerOptions.workerSrc = worker.default;

    return pdf;
}

async function appendPage(pages, caption, blob) {
    const figure = document.createElement('figure');
    figure.className = 'document-attachment-page';
    const label = document.createElement('figcaption');
    label.textContent = caption;
    const image = document.createElement('img');
    image.alt = caption;
    image.src = URL.createObjectURL(blob);
    try {
        await image.decode();
    } catch (error) {
        URL.revokeObjectURL(image.src);
        throw error;
    }
    figure.append(label, image);
    pages.append(figure);
}

async function appendPdf(pages, caption, response, pdf) {
    const assets = new URL(import.meta.env.DEV ? '/node_modules/pdfjs-dist/' : './pdfjs/', import.meta.url);
    const loading = pdf.getDocument({
        data: await response.arrayBuffer(),
        disableWorkerRendering: true,
        stopAtErrors: true,
        cMapUrl: new URL('cmaps/', assets).href,
        iccUrl: new URL('iccs/', assets).href,
        standardFontDataUrl: new URL('standard_fonts/', assets).href,
        wasmUrl: new URL('wasm/', assets).href,
    });
    try {
        const pdfDocument = await loading.promise;
        for (let number = 1; number <= pdfDocument.numPages; number++) {
            const page = await pdfDocument.getPage(number);
            const size = page.getViewport({ scale: 1 });
            const viewport = page.getViewport({ scale: 2200 / Math.max(size.width, size.height) });
            const canvas = document.createElement('canvas');
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            try {
                await page.render({ canvas, viewport, intent: 'print' }).promise;
                const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', .95));
                if (!blob) throw new Error('Page encoding failed');
                await appendPage(pages, `${caption} (${number}/${pdfDocument.numPages})`, blob);
            } finally {
                canvas.width = canvas.height = 0;
                page.cleanup();
            }
        }
    } finally {
        await loading.destroy();
    }
}

async function prepareAttachments(section) {
    const progress = section.querySelector('[data-attachment-progress]');
    let renderer;
    let failed = false;
    for (const file of section.querySelectorAll('[data-print-attachment]')) {
        const link = file.querySelector('a');
        const pages = document.createDocumentFragment();
        const caption = `${section.dataset.documentNumber} · ${link.textContent}`;
        try {
            const response = await fetch(link.href, { signal: AbortSignal.timeout(30000) });
            if (!response.ok || response.redirected || response.headers.get('Content-Type')?.split(';')[0] !== file.dataset.mime) {
                throw new Error('Attachment could not be read');
            }
            if (file.dataset.mime === 'application/pdf') {
                renderer ??= loadPdfRenderer();
                await appendPdf(pages, caption, response, await renderer);
            } else {
                await appendPage(pages, caption, await response.blob());
            }
            file.querySelector('[data-attachment-pages]').append(pages);
            file.dataset.ready = '';
        } catch {
            file.querySelector('[data-attachment-error]').hidden = false;
            file.dataset.failed = '';
            failed = true;
        }
    }
    progress.hidden = !failed;
    if (failed) progress.textContent = 'Periksa lampiran yang perlu dicetak dari file aslinya.';
    document.querySelector('[data-document-print]').disabled = false;
}

const attachments = document.querySelector('[data-print-attachments]');
if (attachments) prepareAttachments(attachments);
