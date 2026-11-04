import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawn, execFileSync } from 'node:child_process';
import { createServer } from 'node:http';
import { setTimeout as delay } from 'node:timers/promises';

const root = fileURLToPath(new URL('../../', import.meta.url));
const catalog = JSON.parse(readFileSync(join(root, 'tests/Fixtures/venue-locations.json'), 'utf8'));
const chrome = [process.env.CHROME_PATH, 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/chromium', '/usr/bin/google-chrome',
].find(path => path && existsSync(path));

async function connect(url) {
    const socket = new WebSocket(url);
    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });
    let id = 0;
    const pending = new Map();
    socket.addEventListener('message', ({ data }) => {
        const response = JSON.parse(data);
        const request = pending.get(response.id);
        if (!request) return;
        clearTimeout(request.timeout);
        pending.delete(response.id);
        response.error ? request.reject(new Error(response.error.message)) : request.resolve(response.result);
    });
    return {
        send(method, params = {}) {
            return new Promise((resolve, reject) => {
                const next = ++id;
                const timeout = setTimeout(() => { pending.delete(next); reject(new Error('Browser command timed out')); }, 10000);
                pending.set(next, { resolve, reject, timeout });
                socket.send(JSON.stringify({ id: next, method, params }));
            });
        },
        close() { socket.close(); },
    };
}

// The parent budget covers six isolated PHP fixtures in one Chrome session.
// Each fixture is independently timed below; each browser command stays at 10s.
test('event review and draft actions preserve controls, requirement labels and accessible errors', { skip: !chrome, timeout: 240000 }, async (t) => {
    const profile = await mkdtemp(join(tmpdir(), 'racetech-cutoff-browser-'));
    let browser, client, html;
    const server = createServer((request, response) => {
        const url = new URL(request.url, 'http://localhost');
        const path = url.pathname;
        if (path === '/') { response.setHeader('Content-Type', 'text/html'); response.end(html); return; }
        if (path === '/admin/venue-locations') {
            const level = url.searchParams.get('level');
            const parent = url.searchParams.get('parent');
            const parentKey = { cities: 'area_code', barangays: 'city_code' }[level];
            const locations = level === 'barangays' && /^\d{10}$/.test(parent)
                ? JSON.parse(readFileSync(join(root, 'tests/Fixtures', catalog.barangay_directory, parent + '.json'), 'utf8'))
                : catalog[level];
            response.writeHead(200, { 'Content-Type': 'application/json' })
                .end(JSON.stringify({ data: Object.values(locations).filter(row => !parentKey || row[parentKey] === parent) }));
            return;
        }
        if (path === '/css/event-review-submit.css' || /^\/js\/[a-z0-9-]+\.js$/.test(path) || (path.startsWith('/build/') && !path.includes('..'))) {
            const asset = join(root, 'public' + path);
            if (existsSync(asset)) {
                response.setHeader('Content-Type', path.endsWith('.css') ? 'text/css' : 'text/javascript');
                response.end(readFileSync(asset)); return;
            }
        }
        response.writeHead(404).end();
    });
    try {
        await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
        const origin = 'http://127.0.0.1:' + server.address().port;
        html = execFileSync('php', ['tests/Js/fixtures/cutoff-duration.php', origin], { cwd: root, encoding: 'utf8' });
        browser = spawn(chrome, ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-background-networking',
            '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
        let port;
        for (let i = 0; i < 100; i++) {
            try { port = (await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; }
            catch { await delay(100); }
        }
        assert.ok(port);
        const pages = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json();
        client = await connect(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
        const evaluate = async expression => {
            const result = await client.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true })
                .catch(error => { throw new Error(error.message + ': ' + expression.slice(0, 160), { cause: error }); });
            if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
            return result.result.value;
        };
        await client.send('Page.navigate', { url: origin });
        for (let i = 0; i < 100; i++) {
            if (await evaluate("document.readyState === 'complete' && document.querySelector('[data-venue-barangay]')?.value === '0402103001'")) break;
            await delay(50);
        }
        const select = async (id, value) => evaluate(`document.getElementById(${JSON.stringify(id)}).value=${JSON.stringify(value)};document.getElementById(${JSON.stringify(id)}).dispatchEvent(new Event('change',{bubbles:true}));`);
        const marathon = 'type-detail-marathon-cutoff-time';
        assert.equal(await evaluate(`document.getElementById('${marathon}').value`), '9.5 hours');
        assert.equal(await evaluate(`document.getElementById('${marathon}-hours').disabled`), true);
        assert.equal(await evaluate(`document.getElementById('${marathon}-hours').value`), '9');
        assert.equal(await evaluate(`document.getElementById('${marathon}-minutes').value`), '30');
        await select(marathon, '6 hours');
        assert.equal(await evaluate("new FormData(document.querySelector('form')).get('type_details[Marathon][cutoff_time]')"), '6 hours');
        await select(marathon, '__custom_duration__');
        assert.equal(await evaluate(`document.getElementById('${marathon}-hours').disabled`), false);
        assert.equal(await evaluate(`document.getElementById('${marathon}-minutes').required`), true);
        assert.equal(await evaluate(`document.getElementById('${marathon}').closest('[data-cutoff-duration]').querySelector('[data-duration-custom]').hidden`), false);
        const setNumber = async (suffix, value) => evaluate(`document.getElementById('${marathon}-${suffix}').value='${value}';document.getElementById('${marathon}-${suffix}').dispatchEvent(new Event('input',{bubbles:true}));`);
        await setNumber('hours', '0');
        await setNumber('minutes', '0');
        assert.equal(await evaluate(`document.getElementById('${marathon}-minutes').checkValidity()`), false);
        await setNumber('hours', '9');
        await setNumber('minutes', '30');
        assert.equal(await evaluate(`document.getElementById('${marathon}-minutes').checkValidity()`), true);
        await setNumber('minutes', '60');
        assert.equal(await evaluate(`document.getElementById('${marathon}-minutes').checkValidity()`), false);
        await setNumber('minutes', '1.5');
        assert.equal(await evaluate(`document.getElementById('${marathon}-minutes').checkValidity()`), false);
        await setNumber('minutes', '30');
        await setNumber('hours', '9.5');
        assert.equal(await evaluate(`document.getElementById('${marathon}-hours').checkValidity()`), false);
        await setNumber('hours', '9');
        assert.equal(await evaluate("new FormData(document.querySelector('form')).get('type_details[Marathon][cutoff_time_hours]')"), '9');
        assert.equal(await evaluate("new FormData(document.querySelector('form')).get('type_details[Marathon][cutoff_time_minutes]')"), '30');
        await select('interest_type', 'Trail Run');
        assert.equal(await evaluate(`document.getElementById('${marathon}-hours').disabled`), true);
        assert.equal(await evaluate("new FormData(document.querySelector('form')).has('type_details[Marathon][cutoff_time]')"), false);
        assert.equal(await evaluate("document.getElementById('type-detail-trail-run-cutoff-time-hours').disabled"), true);
        await select('interest_type', 'Marathon');
        assert.equal(await evaluate(`document.getElementById('${marathon}-hours').value`), '9');
        assert.equal(await evaluate(`document.getElementById('${marathon}-minutes').value`), '30');
        assert.equal(await evaluate(`document.getElementById('${marathon}-hours').disabled`), false);
        assert.equal(await evaluate("Boolean(document.querySelector('[data-event-requirement-legend]'))"), false);
        assert.equal(await evaluate("document.body.textContent.includes('* Required fields')"), false);
        assert.equal(await evaluate("document.querySelectorAll('.event-field-invalid').length"), 0, 'fresh form has no red fields');
        for (const id of ['title', 'interest_type', 'event_date', 'event_end_date']) {
            assert.equal(await evaluate(`document.querySelector('label[for="${id}"] [data-field-requirement]').dataset.fieldRequirement`), 'required');
            assert.equal(await evaluate(`document.querySelector('label[for="${id}"] [data-requirement-symbol]').textContent`), '*');
        }
        for (const id of ['description', 'registration_deadline', 'start_time', 'end_time', 'banner_image_upload', marathon]) {
            assert.equal(await evaluate(`document.querySelector('label[for="${id}"] [data-field-requirement]').dataset.fieldRequirement`), 'required');
            assert.equal(await evaluate(`document.getElementById('${id}').required`), false, 'Labels do not add save-time constraints');
        }
        assert.equal(await evaluate("document.querySelector('[data-category-map-requirement] [data-field-requirement]').dataset.fieldRequirement"), 'required');
        assert.equal(await evaluate("document.getElementsByName('categories[0][slot_limit]')[0].required"), false, 'slot completeness is submission-only');
        assert.equal(await evaluate("document.getElementsByName('categories[0][checkpoint_map_image_upload]')[0].required"), false, 'image is an alternative, not individually required');
        assert.equal(await evaluate("document.querySelector('[data-payment-requirement]').dataset.fieldRequirement"), 'optional');
        const setField = async (name, value) => evaluate(`(() => {
            const field = document.getElementsByName(${JSON.stringify(name)})[0];
            field.value = ${JSON.stringify(value)}; field.dispatchEvent(new Event('change', { bubbles: true }));
        })()`);
        const setUpload = async (name, present = true) => evaluate(`(() => {
            const field = document.getElementsByName(${JSON.stringify(name)})[0];
            const transfer = new DataTransfer();
            if (${present}) transfer.items.add(new File(['synthetic browser-only bytes'], 'synthetic-map.png', {type:'image/png'}));
            field.files = transfer.files; field.dispatchEvent(new Event('change', {bubbles:true}));
        })()`);
        const mapName = 'categories[0][course_map]';
        const mapUpload = 'categories[0][checkpoint_map_image_upload]';
        const validCourse = { version: 1, path: [{lat:14.46,lng:120.96},{lat:14.47,lng:120.97}], checkpoints: [] };
        const mapInvalid = () => evaluate("document.getElementsByName('categories[0][checkpoint_map_image_upload]')[0].hasAttribute('aria-invalid')");
        await setField('categories[0][price_amount]', '500');
        assert.equal(await evaluate("document.querySelector('[data-payment-requirement]').dataset.fieldRequirement"), 'required');
        await setField('categories[0][status]', 'closed');
        assert.equal(await evaluate("document.querySelector('[data-payment-requirement]').dataset.fieldRequirement"), 'optional');
        await setField('categories[0][status]', 'open');
        assert.equal(await evaluate("document.querySelector('[data-payment-requirement]').dataset.fieldRequirement"), 'required');
        await setField('categories[0][price_amount]', '0');
        assert.equal(await evaluate("document.querySelector('[data-payment-requirement]').dataset.fieldRequirement"), 'optional');

        // Save Draft bypasses browser completeness checks, but sends its own intent.
        // Backend tests separately enforce the draft and submission rules.
        await evaluate(`(() => {
            const form = document.querySelector('form');
            window.syntheticSubmissions = [];
            form.addEventListener('submit', event => {
                if (event.defaultPrevented) return;
                event.preventDefault();
                window.syntheticSubmissions.push(new FormData(form, event.submitter).get('intent'));
            });
            form.requestSubmit(form.querySelector('[name=intent][value=draft]'));
        })()`);
        assert.deepEqual(await evaluate('window.syntheticSubmissions'), ['draft']);
        assert.equal(await evaluate("document.querySelectorAll('.event-field-invalid').length"), 0, 'Save Draft does not mark missing submission fields red');
        await evaluate("document.querySelector('[name=intent][value=submit]').click()");
        assert.deepEqual(await evaluate('window.syntheticSubmissions'), ['draft'], 'primary action blocks missing required fields');
        assert.equal(await evaluate('document.activeElement.id'), 'title');
        for (const id of ['title', 'description', 'event_date', 'event_end_date', 'start_time', 'end_time', 'registration_deadline', 'banner_image_upload']) {
            assert.equal(await evaluate(`document.getElementById('${id}').classList.contains('event-field-invalid')`), true, id + ' is highlighted');
        }
        await evaluate("Promise.all(document.getElementById('title').getAnimations().map(animation => animation.finished))");
        assert.equal(await evaluate("getComputedStyle(document.getElementById('title')).borderTopColor"), 'rgb(225, 29, 72)', 'red border wins normal and focus styling');
        assert.match(await evaluate("document.querySelector('[data-event-client-errors]').textContent"), /highlighted fields/);
        assert.equal(await evaluate("document.getElementsByName('categories[0][slot_limit]')[0].getAttribute('aria-invalid')"), 'true');
        assert.equal(await mapInvalid(), true, 'missing map alternative is highlighted');
        assert.match(await evaluate("document.querySelector('[data-category-map-requirement] [data-event-inline-error]').textContent"), /Upload a map image or review and apply/);
        assert.equal(await evaluate("document.querySelector('[name=\"categories[0][group_min_members]\"]').hasAttribute('aria-invalid')"), false, 'hidden group fields are ignored');
        assert.equal(await evaluate("document.getElementsByName('categories[0][scheduled_start_time]')[0].getAttribute('aria-invalid')"), 'true');
        await setField('title', '   ');
        assert.equal(await evaluate("document.getElementById('title').getAttribute('aria-invalid')"), 'true', 'whitespace is still missing');
        await setField('title', 'Synthetic complete event');
        assert.equal(await evaluate("document.getElementById('title').hasAttribute('aria-invalid')"), false, 'correction clears border and ARIA state');
        assert.equal(await evaluate("document.getElementById('title').classList.contains('event-field-invalid')"), false);
        for (const [name, value] of Object.entries({
            description: 'Synthetic assembly details', event_date: '2026-12-01', event_end_date: '2026-12-01',
            registration_deadline: '2026-11-30', start_time: '06:00', end_time: '12:00',
            'categories[0][category_type]': 'open', 'categories[0][distance_option]': '5',
            'categories[0][scheduled_start_date]': '2026-12-01',
            'categories[0][scheduled_end_date]': '2026-12-01',
            'categories[0][scheduled_start_time]': '06:00',
            'categories[0][scheduled_end_time]': '12:00',
            'categories[0][slot_limit]': '100',
        })) await setField(name, value);
        await evaluate(`(() => {
            const upload = document.getElementById('banner_image_upload');
            const transfer = new DataTransfer();
            transfer.items.add(new File(['synthetic browser-only bytes'], 'synthetic-banner.png', {type:'image/png'}));
            upload.files = transfer.files;
            upload.dispatchEvent(new Event('change', {bubbles:true}));
        })()`);
        for (const value of ['', '0', '-1', '1.5']) {
            await setField('categories[0][slot_limit]', value);
            assert.equal(await evaluate("document.getElementsByName('categories[0][slot_limit]')[0].getAttribute('aria-invalid')"), 'true', 'slots require a positive integer: ' + value);
        }
        await setField('categories[0][slot_limit]', '100');
        await setField('start_time', '23:00');
        await setField('end_time', '01:00');
        assert.equal(await evaluate("document.getElementById('end_time').hasAttribute('aria-invalid')"), true, 'same-day end must follow start');
        await setField('event_end_date', '2026-12-02');
        assert.equal(await evaluate("document.getElementById('end_time').hasAttribute('aria-invalid')"), false, 'overnight end is valid on the next date');
        await setField('start_time', '06:00');
        await setField('end_time', '12:00');
        await setField('event_end_date', '2026-12-01');
        await evaluate("document.querySelector('[data-course-reviewed]').checked = true; document.querySelector('[data-course-reviewed]').dispatchEvent(new Event('change', {bubbles:true}))");
        assert.equal(await mapInvalid(), true, 'reviewing an unapplied draft is not a saved course');
        for (const invalid of ['{invalid', JSON.stringify({...validCourse, path: [validCourse.path[0]]}),
            JSON.stringify({...validCourse, path: [validCourse.path[0], validCourse.path[0]]}),
            JSON.stringify({...validCourse, checkpoints: [{lat:14.46,lng:120.96,label:''}]})]) {
            await setField(mapName, invalid);
            assert.equal(await mapInvalid(), true, 'invalid applied data cannot satisfy the map requirement');
        }
        await setField(mapName, JSON.stringify(validCourse));
        assert.equal(await mapInvalid(), false, 'valid applied course satisfies the alternative without an image');
        await setField(mapName, '');
        await setUpload(mapUpload);
        assert.equal(await mapInvalid(), false, 'uploaded image satisfies the alternative without a drawn course');
        await setField(mapName, JSON.stringify(validCourse));
        assert.equal(await mapInvalid(), false, 'both map sources can coexist');
        assert.equal(await evaluate("document.getElementsByName('categories[0][checkpoint_map_image_upload]')[0].files.length"), 1, 'using a route does not discard an uploaded image');
        await setUpload(mapUpload, false);
        await setField(mapName, '');
        await evaluate(`(() => {
            for (const name of ['categories[0][slot_limit]', ${JSON.stringify(mapName)}, ${JSON.stringify(mapUpload)}]) {
                const field = document.getElementsByName(name)[0];
                field.disabled = true; field.dispatchEvent(new Event('change', {bubbles:true}));
            }
        })()`);
        assert.equal(await mapInvalid(), false, 'disabled map controls do not create a missing requirement');
        assert.equal(await evaluate("document.getElementsByName('categories[0][slot_limit]')[0].hasAttribute('aria-invalid')"), false);
        await evaluate(`(() => {
            for (const name of ['categories[0][slot_limit]', ${JSON.stringify(mapName)}, ${JSON.stringify(mapUpload)}]) {
                const field = document.getElementsByName(name)[0];
                field.disabled = false; field.dispatchEvent(new Event('change', {bubbles:true}));
            }
        })()`);
        await setField(mapName, JSON.stringify(validCourse));
        assert.deepEqual(await evaluate("[...document.querySelectorAll('.event-field-invalid')].map(field => field.name || field.id)"), [], 'all corrected required fields clear');
        assert.deepEqual(await evaluate("[...document.querySelector('form').elements].filter(el => el.willValidate && !el.checkValidity()).map(el => el.name)"), []);
        await evaluate("document.querySelector('[name=intent][value=submit]').click()");
        assert.equal(await evaluate("document.querySelector('[data-event-review-confirmation]').open"), true);
        assert.deepEqual(await evaluate('window.syntheticSubmissions'), ['draft'], 'valid setup waits for explicit confirmation');
        await evaluate("document.querySelector('[data-event-review-confirm]').click()");
        assert.deepEqual(await evaluate('window.syntheticSubmissions'), ['draft', 'submit']);
        await setField('categories[0][category_type]', 'group');
        assert.equal(await evaluate("document.querySelector('[data-category-row] [data-group-setting]').classList.contains('hidden')"), false);
        assert.match(await evaluate("document.querySelector('[data-category-row] [data-group-setting] label').textContent"), /Required/);
        assert.equal(await evaluate("document.getElementsByName('categories[0][group_min_members]')[0].required"), true);
        await setField('categories[0][category_type]', 'custom');
        assert.equal(await evaluate("document.getElementsByName('categories[0][group_min_members]')[0].disabled"), true);
        assert.equal(await evaluate("document.querySelector('[data-category-row] [data-custom-category-wrapper]').classList.contains('hidden')"), false);
        assert.match(await evaluate("document.querySelector('[data-category-row] [data-custom-category-wrapper] label').textContent"), /Required/);
        assert.equal(await evaluate("document.getElementsByName('categories[0][custom_category_name]')[0].getAttribute('aria-invalid')"), 'true');
        await setField('categories[0][category_type]', 'open');
        assert.equal(await evaluate("document.getElementsByName('categories[0][custom_category_name]')[0].hasAttribute('aria-invalid')"), false, 'hidden custom field clears');
        await setField('categories[0][distance_option]', 'custom');
        assert.equal(await evaluate("document.querySelector('[data-category-row] [data-custom-distance-wrapper]').classList.contains('hidden')"), false);
        assert.match(await evaluate("document.querySelector('[data-category-row] [data-custom-distance-wrapper] label').textContent"), /Required/);
        assert.equal(await evaluate("document.getElementsByName('categories[0][custom_distance_km]')[0].getAttribute('aria-invalid')"), 'true');
        await select('interest_type', 'Hiking');
        assert.match(await evaluate("document.querySelector('[data-category-row] [data-category-type-details=Hiking] label').textContent"), /Required Gear.*Required/s);
        await select('interest_type', 'Triathlon');
        assert.equal(await evaluate("document.querySelector('[data-category-row] [data-standard-distance]').classList.contains('hidden')"), true);
        assert.equal(await evaluate("document.querySelectorAll('[data-category-row] [data-category-type-details=Triathlon] [data-field-requirement=required]').length"), 3);
        assert.equal(await evaluate("document.getElementsByName('categories[0][custom_distance_km]')[0].hasAttribute('aria-invalid')"), false, 'segmented event ignores standard distance');
        assert.equal(await evaluate("document.querySelectorAll('[data-category-row] [data-category-type-details=Triathlon] .event-field-invalid').length"), 3);
        await evaluate("document.querySelector('[data-add-category]').click()");
        assert.equal(await evaluate("document.querySelectorAll('[data-category-list] > [data-category-row]').length"), 2);
        assert.equal(await evaluate("document.getElementById('category-1-currency').value"), 'PHP');
        assert.equal(await evaluate("document.querySelector('label[for=category-1-currency] [data-field-requirement]').dataset.fieldRequirement"), 'required');
        assert.equal(await evaluate("document.querySelector('label[for=category-1-price] [data-field-requirement]').dataset.fieldRequirement"), 'optional');
        assert.equal(await evaluate("document.getElementById('category-1-currency').hasAttribute('aria-invalid')"), false, 'cloned PHP currency is already valid');
        assert.equal(await evaluate("document.getElementsByName('categories[1][slot_limit]')[0].getAttribute('aria-invalid')"), 'true', 'added category needs its own slot limit');
        assert.equal(await evaluate("document.getElementsByName('categories[1][checkpoint_map_image_upload]')[0].getAttribute('aria-invalid')"), 'true', 'another category map cannot satisfy this row');
        await setField('categories[1][status]', 'closed');
        assert.equal(await evaluate("document.getElementsByName('categories[1][checkpoint_map_image_upload]')[0].getAttribute('aria-invalid')"), 'true', 'closed category also needs a map');
        await setField('categories[1][status]', 'open');
        await select('interest_type', 'Marathon');
        await setField('categories[1][category_type]', 'group');
        assert.match(await evaluate("[...document.querySelectorAll('[data-category-list] > [data-category-row]')][1].querySelector('[data-group-setting] label').textContent"), /Required/);
        assert.equal(await evaluate("new FormData(document.querySelector('form')).get('categories[1][price_currency]')"), 'PHP');
        await setField('categories[1][price_amount]', '500');
        assert.equal(await evaluate("document.querySelector('[data-payment-requirement]').dataset.fieldRequirement"), 'required');
        await evaluate("[...document.querySelectorAll('[data-category-list] > [data-category-row]')][1].querySelector('[data-remove-category]').click()");
        assert.equal(await evaluate("document.querySelector('[data-payment-requirement]').dataset.fieldRequirement"), 'optional');
        for (const width of [1280, 375, 320]) {
            await client.send('Emulation.setDeviceMetricsOverride', { width, height: 850, deviceScaleFactor: 1, mobile: false });
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, 'form fits width ' + width);
        }
        await evaluate("document.querySelector('[name=intent][value=draft]').click()");
        assert.equal(await evaluate("document.querySelectorAll('.event-field-invalid').length"), 0, 'Save Draft clears client submission highlights');
        html = execFileSync('php', ['tests/Js/fixtures/cutoff-duration.php', origin, 'errors'], { cwd: root, encoding: 'utf8' });
        await client.send('Page.navigate', { url: origin + '/?errors=1' });
        for (let i = 0; i < 100; i++) {
            if (await evaluate("document.readyState === 'complete' && Boolean(document.querySelector('[data-event-errors]')) && Boolean(document.querySelector('form').dataset.eventReviewSubmitBound)")) break;
            await delay(50);
        }
        assert.equal(await evaluate("document.querySelector('[data-event-errors]').getAttribute('role')"), 'alert');
        assert.equal(await evaluate("document.querySelectorAll('[data-event-error-field]').length"), 5);
        assert.equal(await evaluate("Boolean(window.syntheticUnsafe)"), false);
        for (const [key, name] of [['title', 'title'], ['categories.0.scheduled_start_time', 'categories[0][scheduled_start_time]']]) {
            await evaluate(`document.querySelector('[data-event-error-field="${key}"]').click()`);
            assert.equal(await evaluate('document.activeElement.name'), name);
            assert.equal(await evaluate("document.activeElement.getAttribute('aria-invalid')"), 'true');
            assert.match(await evaluate("document.activeElement.getAttribute('aria-describedby')"), /event-inline-error-/);
        }
        await setField('title', 'Corrected synthetic title');
        assert.equal(await evaluate("document.getElementById('title').hasAttribute('aria-invalid')"), false, 'server-error highlight clears after correction');
        assert.equal(await evaluate("document.querySelector('[data-event-error-field=title]').closest('li').hidden"), true);
        await evaluate("document.querySelector('[data-event-error-field=\"categories.0.course_map\"]').click()");
        assert.equal(await evaluate('document.activeElement.name'), mapUpload, 'hidden course errors target a visible map input');
        assert.equal(await mapInvalid(), true);
        await setField(mapName, JSON.stringify(validCourse));
        assert.equal(await mapInvalid(), false, 'applying a course clears the server map error');
        assert.equal(await evaluate("document.querySelector('[data-event-error-field=\"categories.0.course_map\"]').closest('li').hidden"), true);
        await evaluate("document.querySelector('[data-event-error-field=\"payment_choices.0\"]').click()");
        assert.match(await evaluate('document.activeElement.textContent'), /Manage payment options/);
        assert.match(await evaluate("document.querySelector('[data-event-error-field=review_version]').textContent"), /Reload this changed draft/);
        await client.send('Emulation.setDeviceMetricsOverride', { width: 320, height: 850, deviceScaleFactor: 1, mobile: false });
        assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, 'error summary fits narrow screens');
        for (const mode of ['saved-banner', 'payment', 'saved-image', 'saved-course', 'invalid-course', 'saved-empty']) {
            await t.test('saved form fixture: ' + mode, { timeout: 60000 }, async () => {
                html = execFileSync('php', ['tests/Js/fixtures/cutoff-duration.php', origin, mode], { cwd: root, encoding: 'utf8' });
                assert.ok(html.includes('name="intent" value="submit"'), 'real form renders in fixture mode ' + mode);
                await client.send('Page.navigate', { url: origin + '/?mode=' + mode });
                for (let i = 0; i < 100; i++) {
                    if (await evaluate("document.readyState === 'complete' && Boolean(document.querySelector('form')?.dataset.eventReviewSubmitBound)")) break;
                    await delay(50);
                }
                if (mode === 'saved-banner') {
                    await evaluate("document.querySelector('[name=intent][value=submit]').click()");
                    assert.equal(await evaluate("document.getElementById('banner_image_upload').hasAttribute('aria-invalid')"), false, 'existing banner does not need a replacement');
                    assert.equal(await evaluate("document.getElementById('banner_image_upload').hasAttribute('aria-required')"), false);
                } else if (mode === 'payment') {
                    await setField('categories[0][price_amount]', '500');
                    await evaluate("document.querySelector('[name=intent][value=submit]').click()");
                    assert.equal(await evaluate("document.querySelector('[name=\"payment_choices[]\"]').getAttribute('aria-invalid')"), 'true');
                    assert.equal(await evaluate("document.querySelector('[data-payment-requirement] [data-requirement-symbol]').textContent"), '*');
                    await evaluate("document.querySelector('[name=\"payment_choices[]\"]').click()");
                    assert.equal(await evaluate("document.querySelector('[name=\"payment_choices[]\"]').hasAttribute('aria-invalid')"), false);
                    assert.deepEqual(await evaluate("new FormData(document.querySelector('form')).getAll('payment_choices[]')"), ['option:900001:1']);
                    assert.match(await evaluate("document.querySelector('[name=\"payment_choices[]\"]').getAttribute('aria-describedby')"), /event-payment-selection-help/);
                    await setField('categories[0][price_amount]', '0');
                    assert.equal(await evaluate("document.querySelector('[data-payment-requirement] [data-requirement-symbol]').textContent"), '(Optional)');
                    assert.equal(await evaluate("document.querySelector('[name=\"payment_choices[]\"]').checked"), true, 'optional state does not change selected payment data');
                } else {
                    await evaluate("document.querySelector('[name=intent][value=submit]').click()");
                    assert.equal(await evaluate("Boolean(document.getElementsByName('categories[0][checkpoint_map_image_upload]')[0])"), true, 'existing category can upload inline');
                    assert.equal(await mapInvalid(), !['saved-image', 'saved-course'].includes(mode), mode + ' map eligibility');
                    if (mode === 'saved-image') {
                        assert.equal(await evaluate("Boolean(document.querySelector('[data-category-map-requirement] img'))"), true, 'saved preview remains visible');
                        assert.equal(await evaluate("new FormData(document.querySelector('form')).has('categories[0][checkpoint_map_image]')"), false, 'saved path is not trusted from a client field');
                    }
                    if (mode === 'saved-course') {
                        await setField(mapName, '');
                        assert.equal(await mapInvalid(), true, 'removing saved route restores the requirement');
                    }
                    if (mode === 'saved-empty') {
                        await setUpload(mapUpload);
                        assert.equal(await mapInvalid(), false, 'existing category can satisfy missing map without leaving form');
                    }
                }
            });
        }
        t.diagnostic('All event-form control, map-alternative, schedule, and saved-fixture assertions completed.');
    } finally {
        if (client && browser?.exitCode === null) await client.send('Browser.close').catch(() => {});
        client?.close();
        if (browser && browser.exitCode === null) {
            const exited = new Promise(resolve => browser.once('exit', resolve));
            if (process.platform === 'win32') {
                // Chrome's helper processes can otherwise keep this test's
                // private profile locked after the parent process exits.
                try { execFileSync('taskkill', ['/pid', String(browser.pid), '/t', '/f'], { windowsHide: true, stdio: 'ignore' }); }
                catch { browser.kill(); }
            } else browser.kill();
            await Promise.race([exited, delay(5000)]);
        }
        server.closeAllConnections();
        await new Promise(resolve => server.close(resolve));
        assert.equal(resolve(profile).startsWith(resolve(tmpdir()) + (process.platform === 'win32' ? '\\' : '/')), true);
        try { await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 500 }); }
        catch (error) {
            // Cleanup must not hide an assertion failure. Windows may retain a
            // Chrome lock briefly even after this test's process tree exits.
            if (process.platform !== 'win32' || error.code !== 'EBUSY') throw error;
            t.diagnostic('Chrome temporary profile is still locked; retained for cleanup: ' + profile);
        }
    }
});
