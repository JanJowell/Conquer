(() => {
    'use strict';
    document.querySelectorAll('[data-venue-picker]').forEach(root => {
        const mode = root.querySelector('[data-venue-mode]');
        const fields = root.querySelector('[data-venue-new]');
        const area = root.querySelector('[data-venue-area]');
        const city = root.querySelector('[data-venue-city]');
        const barangay = root.querySelector('[data-venue-barangay]');
        const status = root.querySelector('[data-venue-status]');
        const retry = root.querySelector('[data-venue-retry]');
        const preview = root.querySelector('[data-venue-preview]');
        const name = root.querySelector('[data-venue-name]');
        const cache = new Map();
        let generation = 0;
        let controller;
        let previous = { area: root.dataset.area, city: root.dataset.city, barangay: root.dataset.barangay };
        let retryAction;
        const reset = (select, text) => {
            select.replaceChildren(new Option(text, ''));
            select.disabled = true;
        };
        const describe = () => {
            if (mode.value !== 'new') {
                preview.textContent = '';
                return;
            }
            const parts = [barangay.value && barangay.selectedOptions[0]?.textContent,
                city.value && city.selectedOptions[0]?.textContent, area.value && area.selectedOptions[0]?.textContent].filter(Boolean);
            preview.textContent = name.value.trim() ? name.value.trim() + (parts.length ? ' — ' + parts.join(', ') : '') : '';
        };
        const begin = () => {
            controller?.abort();
            controller = new AbortController();
            status.textContent = '';
            retry.hidden = true;
            return ++generation;
        };
        async function load(select, level, parent, selected, token) {
            reset(select, 'Loading locations…');
            const cacheKey = level + ':' + (parent || '');
            let rows = cache.get(cacheKey);
            if (!rows) {
                const url = new URL(root.dataset.locationsUrl, window.location.origin);
                if (url.origin !== window.location.origin) throw new Error('The location endpoint must belong to this website.');
                url.searchParams.set('level', level);
                if (parent) url.searchParams.set('parent', parent);
                const response = await fetch(url, {
                    credentials: 'same-origin', signal: controller.signal,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (response.redirected || [401, 403, 419].includes(response.status)) {
                    throw new Error('Your session or access has changed. Refresh the page and sign in again before saving.');
                }
                if (!response.ok) throw new Error('Locations could not be loaded. Retry or refresh the page.');
                const payload = await response.json();
                rows = payload.data;
                if (!Array.isArray(rows) || rows.length > 2000 || rows.some(row => !/^\d{10}$/.test(row.code) || typeof row.name !== 'string')) {
                    throw new Error('The location response is invalid. Refresh the page before continuing.');
                }
                cache.set(cacheKey, rows);
            }
            if (token !== generation || mode.value !== 'new') return false;
            select.replaceChildren(new Option('Select ' + ({ areas: 'province or area', cities: 'city / municipality', barangays: 'barangay' }[level]), ''),
                ...rows.map(row => new Option(row.name, row.code)));
            select.disabled = false;
            if (selected && rows.some(row => row.code === selected)) select.value = selected;
            if (!rows.length) {
                select.disabled = true;
                status.textContent = 'No locations are listed for this selection. Choose another parent location.';
            }
            describe();
            return true;
        }
        async function run(action) {
            retryAction = action;
            const token = begin();
            try { await action(token); }
            catch (error) {
                if (token !== generation || error.name === 'AbortError') return;
                status.textContent = error.message || 'Locations could not be loaded.';
                retry.hidden = false;
                [area, city, barangay].forEach(select => {
                    if (select.disabled) reset(select, 'Choose parent location or retry');
                });
            }
        }
        const loadAll = token => (async () => {
            reset(city, 'Select province or area first');
            reset(barangay, 'Select city or municipality first');
            if (!await load(area, 'areas', null, previous.area, token) || !area.value) return;
            if (!await load(city, 'cities', area.value, previous.city, token) || !city.value) return;
            await load(barangay, 'barangays', city.value, previous.barangay, token);
        })();
        const setEditing = isNew => {
            begin();
            mode.value = isNew ? 'new' : 'keep';
            fields.hidden = !isNew;
            fields.disabled = !isNew;
            root.querySelector('[data-venue-change]').hidden = isNew;
            root.querySelector('[data-venue-cancel]').hidden = !isNew;
            if (isNew) run(loadAll);
            describe();
        };
        root.querySelector('[data-venue-change]')?.addEventListener('click', () => setEditing(true));
        root.querySelector('[data-venue-cancel]')?.addEventListener('click', () => setEditing(false));
        area.addEventListener('change', () => {
            previous = { area: area.value, city: '', barangay: '' };
            reset(city, 'Select province or area first');
            reset(barangay, 'Select city or municipality first');
            describe();
            if (area.value) run(token => load(city, 'cities', area.value, '', token));
            else begin();
        });
        city.addEventListener('change', () => {
            previous = { area: area.value, city: city.value, barangay: '' };
            reset(barangay, 'Select city or municipality first');
            describe();
            if (city.value) run(token => load(barangay, 'barangays', city.value, '', token));
            else begin();
        });
        barangay.addEventListener('change', () => {
            previous.barangay = barangay.value;
            describe();
        });
        retry.addEventListener('click', () => { if (retryAction) run(retryAction); });
        name.addEventListener('input', describe);
        root.closest('form')?.addEventListener('submit', event => {
            if (mode.value === 'new' && (fields.disabled || [area, city, barangay].some(select => select.disabled || !select.value))) {
                event.preventDefault();
                status.textContent = 'Select a valid province/area, city/municipality, and barangay before saving.';
                (area.disabled ? retry : (!area.value ? area : !city.value ? city : barangay)).focus();
            }
        });
        if (mode.value === 'new' && !fields.disabled) run(loadAll);
        describe();
    });
})();
