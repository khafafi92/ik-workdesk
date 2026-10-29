export default function initializeVendorAddress(form, onChange) {
    const city = form.querySelector('[name="answers[city]"]');
    if (!city) return;

    const origin = form.querySelector('[name="answers[company_origin]"]');
    const province = form.querySelector('[name="answers[province]"]');
    const postcode = form.querySelector('[name="answers[postcode]"]');
    const cityCode = form.querySelector('[data-vp-city-code]');
    const choices = Array.from(form.querySelector('#vendor-cities').options);
    const status = form.querySelector('#vendor-address-status');
    const note = form.querySelector('#vendor-address-note');
    const retry = form.querySelector('[data-vp-address-retry]');
    const fields = Object.fromEntries(['district', 'village'].map(key => {
        const field = form.querySelector(`[data-vp-address-field="${key}"]`);
        return [key, { field, label: field.querySelector('label'), manual: field.querySelector('input'), select: field.querySelector('select') }];
    }));
    const cache = new Map();
    let controller;
    let generation = 0;
    let loadedCity;
    let active = Boolean(cityCode.value);
    const domestic = () => origin.value !== 'foreign';
    const matchCity = () => choices.find(option => option.value.toLocaleLowerCase('id') === city.value.trim().toLocaleLowerCase('id'));
    const options = (select, entries, placeholder, value = '') => {
        select.replaceChildren(new Option(placeholder, ''), ...Object.entries(entries)
            .sort((a, b) => a[1].name.localeCompare(b[1].name, 'id'))
            .map(([code, item]) => new Option(item.name, code)));
        select.value = value;
    };
    const selectors = enabled => {
        for (const item of Object.values(fields)) {
            item.field.hidden = !domestic();
            item.manual.hidden = enabled;
            item.manual.disabled = enabled || !domestic();
            item.select.hidden = !enabled;
            item.select.disabled = !enabled || !domestic();
            item.select.required = enabled && active;
            item.label.htmlFor = enabled ? item.select.id : item.manual.id;
            item.label.querySelector('[data-vp-region-required]').hidden = !item.select.required;
        }
    };
    const manual = message => {
        cityCode.disabled = true;
        cityCode.value = '';
        province.readOnly = false;
        postcode.readOnly = false;
        selectors(false);
        status.textContent = message;
    };
    const updateVillage = (saved = '') => {
        const district = loadedCity?.districts[fields.district.select.value];
        options(fields.village.select, district?.villages || {}, district ? 'Pilih kelurahan/desa' : 'Pilih kecamatan terlebih dahulu', saved);
        fields.village.select.disabled = !district;
    };
    const updatePostcode = () => {
        const village = loadedCity?.districts[fields.district.select.value]?.villages[fields.village.select.value];
        postcode.value = village?.postcode || '';
        postcode.readOnly = Boolean(village?.postcode);
        status.textContent = !village ? 'Pilih kecamatan dan kelurahan/desa untuk mengisi kode pos.'
            : (village.postcode ? 'Provinsi dan kode pos sudah terisi sesuai wilayah.' : 'Kode pos wilayah ini belum tersedia. Isi kode pos sesuai alamat perusahaan.');
    };
    const loadCity = async (initial = false) => {
        const request = ++generation;
        controller?.abort();
        retry.hidden = true;
        loadedCity = null;
        const choice = domestic() ? matchCity() : null;
        city.setAttribute('list', domestic() ? 'vendor-cities' : '');
        postcode.setAttribute('inputmode', domestic() ? 'numeric' : 'text');
        note.hidden = !domestic();
        if (!initial) {
            active = Boolean(choice);
            cityCode.value = '';
            for (const item of Object.values(fields)) {
                item.select.value = '';
                item.manual.value = '';
            }
            if (domestic()) {
                province.value = '';
                postcode.value = '';
            }
        }
        if (!choice) {
            manual(domestic() ? 'Pilih kota dari daftar, atau isi provinsi dan kode pos secara manual.' : '');
            onChange();
            return;
        }
        province.value = choice.dataset.province;
        province.readOnly = true;
        cityCode.value = choice.dataset.code;
        cityCode.disabled = !active;
        status.textContent = 'Memuat kecamatan dan kelurahan/desa...';
        selectors(true);
        fields.district.select.disabled = fields.village.select.disabled = true;
        controller = new AbortController();
        try {
            let result = cache.get(choice.dataset.code);
            if (!result) {
                const response = await fetch(`${form.dataset.vpAddressUrl}/${choice.dataset.code}`, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Region request failed');
                result = await response.json();
                if (!result.districts) throw new Error('Region data missing');
                cache.set(choice.dataset.code, result);
            }
            if (generation !== request) return;
            loadedCity = result;
            const district = initial ? fields.district.select.dataset.savedCode : '';
            const village = initial ? fields.village.select.dataset.savedCode : '';
            options(fields.district.select, result.districts, 'Pilih kecamatan', district);
            fields.district.select.disabled = false;
            updateVillage(village);
            if (active) updatePostcode();
            else status.textContent = 'Pilih kecamatan dan kelurahan/desa untuk memperbarui kode pos. Alamat tersimpan tetap tersedia.';
            onChange();
        } catch (error) {
            if (error.name === 'AbortError' || generation !== request) return;
            manual('Data wilayah belum dapat dimuat. Coba muat ulang atau isi wilayah dan kode pos secara manual.');
            retry.hidden = false;
            onChange();
        }
    };
    city.addEventListener('input', () => loadCity());
    origin.addEventListener('change', () => loadCity());
    retry.addEventListener('click', () => loadCity());
    fields.district.select.addEventListener('change', () => {
        active = true;
        cityCode.disabled = false;
        selectors(true);
        updateVillage();
        updatePostcode();
        onChange();
    });
    fields.village.select.addEventListener('change', () => {
        updatePostcode();
        onChange();
    });
    loadCity(true);
}
