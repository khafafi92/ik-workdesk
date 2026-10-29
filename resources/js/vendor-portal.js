import initializeVendorAddress from './vendor-address';

const form = document.querySelector('[data-vp-form]');

if (form) {
    const origin = form.querySelector('[name="answers[company_origin]"]');
    const basis = form.querySelector('[name="answers[document_basis]"]');
    const country = form.querySelector('[name="answers[country]"]');
    const savedAnswers = JSON.parse(form.dataset.savedAnswers || '{}');
    const relevant = (element, show) => {
        element.hidden = !show;
        element.querySelectorAll('input, select, textarea').forEach(input => {
            input.disabled = !show;
        });
    };
    const update = () => {
        if (origin) {
            const foreign = origin.value === 'foreign';
            relevant(form.querySelector('[data-vp-basis]'), !foreign);
            if (foreign) basis.value = 'company';
            if (country) {
                country.readOnly = !foreign;
                if (!foreign) country.value = 'Indonesia';
                else if (country.value.trim().toLowerCase() === 'indonesia') country.value = '';
            }
            form.querySelectorAll('[data-vp-company-legal]').forEach(element => relevant(element, !foreign && basis.value !== 'npwp_ktp'));
        }
        form.querySelectorAll('[data-vp-condition]').forEach(element => {
            const input = form.elements.namedItem(`answers[${element.dataset.vpCondition}]`);
            relevant(element, input?.value === element.dataset.vpValue);
        });
        const documents = document.querySelector('[data-vp-documents]');
        if (documents) {
            const changed = Array.from(form.elements).some(input => {
                if (!input.name?.startsWith('answers[') || input.disabled) return false;
                const key = input.name.slice(8, -1);
                const saved = key === 'company_origin' ? form.dataset.savedOrigin : (key === 'document_basis' ? form.dataset.savedBasis : (savedAnswers[key] ?? ''));
                return (input.value ?? '') !== saved;
            });
            documents.querySelector('[data-vp-save-first]').hidden = !changed;
            documents.querySelector('[data-vp-document-list]').hidden = changed;
        }
    };
    form.addEventListener('change', update);
    form.addEventListener('input', update);
    update();
    initializeVendorAddress(form, update);
}

document.querySelectorAll('form').forEach(element => {
    element.addEventListener('submit', event => {
        const button = event.submitter || element.querySelector('button[type="submit"]');
        if (button) {
            button.setAttribute('aria-busy', 'true');
            button.classList.add('vp-submitting');
            button.textContent = 'Menyimpan...';
        }
    });
});
