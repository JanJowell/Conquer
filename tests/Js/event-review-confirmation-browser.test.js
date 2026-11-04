import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { basename, join, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync, spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { setTimeout as delay } from 'node:timers/promises';

const root = fileURLToPath(new URL('../../', import.meta.url));
const catalog = JSON.parse(readFileSync(join(root, 'tests/Fixtures/venue-locations.json'), 'utf8'));
const chrome = [process.env.CHROME_PATH, 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/chromium', '/usr/bin/google-chrome',
].find((path) => path && existsSync(path));

async function connect(url) {
    const socket = new WebSocket(url);
    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });
    let next = 0;
    const pending = new Map();
    const listeners = new Map();
    socket.addEventListener('message', ({ data }) => {
        const response = JSON.parse(data);
        if (!response.id) { listeners.get(response.method)?.(response.params); return; }
        const request = pending.get(response.id);
        if (!request) return;
        clearTimeout(request.timeout);
        pending.delete(response.id);
        response.error ? request.reject(new Error(response.error.message)) : request.resolve(response.result);
    });
    return {
        send(method, params = {}) {
            return new Promise((resolve, reject) => {
                const id = ++next;
                const timeout = setTimeout(() => { pending.delete(id); reject(new Error('Browser command timed out: ' + method)); }, 10000);
                pending.set(id, { resolve, reject, timeout });
                socket.send(JSON.stringify({ id, method, params }));
            });
        },
        on(method, callback) { listeners.set(method, callback); },
        close() {
            for (const request of pending.values()) { clearTimeout(request.timeout); request.reject(new Error('Test browser closed')); }
            pending.clear();
            socket.close();
        },
    };
}

test('event review confirmation preserves validated setup and submits only once (local fixtures only)', { skip: !chrome, timeout: 120000 }, async (t) => {
    const profile = await mkdtemp(join(tmpdir(), 'racetech-review-confirmation-'));
    const submissions = [];
    let browser, client, html;
    const server = createServer((request, response) => {
        const url = new URL(request.url, 'http://localhost');
        if (url.pathname === '/submission' && request.method === 'POST') {
            let body = '';
            request.on('data', (chunk) => { body += chunk; });
            request.on('end', () => {
                submissions.push(body);
                // Exercise real browser serialization, without leaving the
                // synthetic page or writing anything to Laravel/the database.
                response.writeHead(204).end();
            });
            return;
        }
        if (url.pathname === '/') { response.setHeader('Content-Type', 'text/html'); response.end(html); return; }
        if (url.pathname === '/admin/venue-locations') {
            const level = url.searchParams.get('level');
            const parent = url.searchParams.get('parent');
            const parentKey = { cities: 'area_code', barangays: 'city_code' }[level];
            const locations = level === 'barangays' && /^\d{10}$/.test(parent)
                ? JSON.parse(readFileSync(join(root, 'tests/Fixtures', catalog.barangay_directory, parent + '.json'), 'utf8'))
                : catalog[level];
            response.writeHead(200, { 'Content-Type': 'application/json' })
                .end(JSON.stringify({ data: Object.values(locations || {}).filter((row) => !parentKey || row[parentKey] === parent) }));
            return;
        }
        if (url.pathname === '/css/event-review-submit.css' || /^\/js\/[a-z0-9-]+\.js$/.test(url.pathname) || /^\/build\/assets\/[a-zA-Z0-9_-]+\.(css|js)$/.test(url.pathname)) {
            const asset = join(root, 'public', url.pathname);
            if (existsSync(asset)) {
                response.setHeader('Content-Type', asset.endsWith('.css') ? 'text/css' : 'text/javascript');
                response.end(readFileSync(asset)); return;
            }
        }
        response.writeHead(404).end();
    });
    try {
        await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
        const origin = 'http://127.0.0.1:' + server.address().port;
        browser = spawn(chrome, ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-background-networking',
            '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
        let startupError, port;
        browser.on('error', (error) => { startupError = error; });
        for (let attempt = 0; attempt < 100; attempt++) {
            if (startupError) throw startupError;
            try { port = (await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; }
            catch { await delay(100); }
        }
        assert.ok(port, 'temporary headless browser started');
        const pages = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json();
        client = await connect(pages.find((page) => page.type === 'page').webSocketDebuggerUrl);
        const errors = [];
        client.on('Runtime.exceptionThrown', ({ exceptionDetails }) => errors.push(exceptionDetails.text));
        client.on('Fetch.requestPaused', ({ requestId, request }) => {
            const allowed = request.url.startsWith(origin + '/');
            client.send(allowed ? 'Fetch.continueRequest' : 'Fetch.failRequest', allowed ? { requestId } : { requestId, errorReason: 'BlockedByClient' })
                .catch((error) => errors.push(error.message));
        });
        await client.send('Runtime.enable');
        await client.send('Fetch.enable', { patterns: [{ urlPattern: '*' }] });
        const evaluate = async (expression) => {
            const result = await client.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
            if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
            return result.result.value;
        };
        const until = async (condition, description = condition) => {
            for (let attempt = 0; attempt < 100; attempt++) {
                if (await evaluate(condition)) return;
                await delay(50);
            }
            assert.fail('Condition not met: ' + description);
        };
        const click = async (selector) => {
            const point = await evaluate(`(() => {
                const button = document.querySelector(${JSON.stringify(selector)});
                button.scrollIntoView({block:'center'});
                const rect = button.getBoundingClientRect();
                return {x:rect.left + rect.width/2, y:rect.top + rect.height/2};
            })()`);
            await client.send('Input.dispatchMouseEvent', { type: 'mousePressed', button: 'left', clickCount: 1, ...point });
            await client.send('Input.dispatchMouseEvent', { type: 'mouseReleased', button: 'left', clickCount: 1, ...point });
        };
        const submit = '[name="intent"][value="submit"]';
        const dialog = '[data-event-review-confirmation]';
        const confirm = '[data-event-review-confirm]';
        const cancel = '[data-event-review-cancel]';
        const open = () => evaluate(`document.querySelector('${dialog}').open`);
        const load = async (mode) => {
            html = execFileSync('php', ['tests/Js/fixtures/cutoff-duration.php', origin, mode], { cwd: root, encoding: 'utf8', timeout: 20000 });
            await client.send('Page.navigate', { url: origin + '/?mode=' + mode });
            await until("document.readyState === 'complete' && document.querySelector('form')?.dataset.eventReviewSubmitBound === 'true'");
            await until("document.querySelector('[data-venue-mode]')?.value === 'keep' || document.querySelector('[data-venue-barangay]')?.value === '0402103001'");
            await evaluate(`(() => {
                const form = document.querySelector('form');
                form.method = 'POST'; form.action = ${JSON.stringify(origin + '/submission')}; form.enctype = 'multipart/form-data';
                window.fillSetup = () => {
                    const values = {
                        title:'Synthetic confirmation event', description:'Synthetic assembly details',
                        event_date:'2026-12-01', event_end_date:'2026-12-01', registration_deadline:'2026-11-30',
                        start_time:'06:00', end_time:'12:00',
                        'categories[0][category_type]':'open', 'categories[0][distance_option]':'5',
                        'categories[0][scheduled_start_date]':'2026-12-01', 'categories[0][scheduled_end_date]':'2026-12-01',
                        'categories[0][scheduled_start_time]':'06:00', 'categories[0][scheduled_end_time]':'12:00',
                        'categories[0][slot_limit]':'50'
                    };
                    for (const [name, value] of Object.entries(values)) {
                        const field = document.getElementsByName(name)[0];
                        field.value = value; field.dispatchEvent(new Event('change', {bubbles:true}));
                    }
                    for (const name of ['banner_image_upload','categories[0][checkpoint_map_image_upload]']) {
                        const field = document.getElementsByName(name)[0];
                        const transfer = new DataTransfer();
                        transfer.items.add(new File(['synthetic local test bytes'], 'synthetic.png', {type:'image/png'}));
                        field.files = transfer.files; field.dispatchEvent(new Event('change', {bubbles:true}));
                    }
                };
                window.setupSnapshot = () => [...new FormData(form)].map(([key,value]) => [key, value instanceof File ? [value.name,value.size,value.type] : value]);
            })()`);
        };
        const waitForPosts = async (count) => {
            for (let attempt = 0; attempt < 100 && submissions.length < count; attempt++) await delay(50);
            assert.equal(submissions.length, count);
        };

        await load('payment');
        await t.test('invalid setup shows errors before any confirmation and Save Draft remains direct', async () => {
            await click(submit);
            assert.equal(await open(), false);
            assert.equal(await evaluate("document.getElementById('title').getAttribute('aria-invalid')"), 'true');
            assert.equal(submissions.length, 0);
            await click('[name="intent"][value="draft"]');
            await waitForPosts(1);
            assert.match(submissions[0], /name="intent"\r\n\r\ndraft/);
            assert.equal(await open(), false);
            assert.equal(await evaluate("document.querySelector('form').hasAttribute('aria-busy')"), false);
        });
        await evaluate(`fillSetup(); document.querySelector('[name="payment_choices[]"]').click();
            document.getElementsByName('categories[0][price_amount]')[0].value = '500';
            document.getElementsByName('categories[0][price_amount]')[0].dispatchEvent(new Event('change',{bubbles:true}));`);
        await t.test('Keep Editing and Escape preserve values files and selected payment and restore focus', async () => {
            const before = await evaluate('setupSnapshot()');
            await click(submit);
            assert.equal(await open(), true);
            assert.equal(await evaluate(`document.activeElement.matches('${cancel}')`), true, 'safe action receives initial focus');
            for (let index = 0; index < 3; index++) {
                await client.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
                await client.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
                assert.equal(await evaluate(`Boolean(document.activeElement.closest('${dialog}'))`), true, 'keyboard focus remains in the modal');
            }
            await client.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, modifiers: 8 });
            await client.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, modifiers: 8 });
            assert.equal(await evaluate(`Boolean(document.activeElement.closest('${dialog}'))`), true, 'reverse Tab stays in the dialog');
            assert.match(await evaluate("document.getElementById('event-review-confirmation-description').textContent"), /only be published after Admin approval/);
            assert.equal(submissions.length, 1);
            await click(cancel);
            assert.equal(await open(), false);
            assert.equal(await evaluate(`document.activeElement.matches('${submit}')`), true);
            assert.deepEqual(await evaluate('setupSnapshot()'), before);
            await click(submit);
            await client.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            await client.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            assert.equal(await open(), false);
            assert.deepEqual(await evaluate('setupSnapshot()'), before);
            assert.equal(submissions.length, 1);
        });
        await t.test('confirmation revalidates and honors other guards instead of bypassing them', async () => {
            await click(submit);
            await evaluate("document.getElementById('end_time').value = ''");
            await click(confirm);
            assert.equal(await open(), false);
            assert.equal(await evaluate("document.getElementById('end_time').getAttribute('aria-invalid')"), 'true');
            assert.equal(submissions.length, 1);
            await evaluate("fillSetup(); window.syntheticBlock = event => { if (event.submitter?.value === 'submit') event.preventDefault(); }; document.querySelector('form').addEventListener('submit', syntheticBlock)");
            await click(submit);
            await click(confirm);
            assert.equal(await evaluate("document.querySelector('form').hasAttribute('aria-busy')"), false, 'another guard can still stop the confirmed attempt');
            assert.equal(submissions.length, 1);
            await evaluate("document.querySelector('form').removeEventListener('submit', syntheticBlock)");
        });
        await t.test('Enter also asks for confirmation and confirmed review serializes once with its intent intact', async () => {
            await evaluate("document.getElementById('title').focus()");
            await client.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13, text: '\r', unmodifiedText: '\r' });
            await client.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
            assert.equal(await open(), true);
            await click(confirm);
            await waitForPosts(2);
            assert.match(submissions[1], /name="intent"\r\n\r\nsubmit/);
            assert.match(submissions[1], /name="payment_choices\[\]"\r\n\r\noption:900001:1/);
            assert.match(submissions[1], /name="categories\[0\]\[checkpoint_map_image_upload\]"; filename="synthetic.png"/);
            assert.equal(await evaluate("document.querySelector('form').getAttribute('aria-busy')"), 'true');
            assert.equal(await evaluate(`document.querySelector('${confirm}').disabled`), true);
            assert.equal(await evaluate(`document.querySelector('${submit}').disabled`), false, 'submitter remains serializable');
            assert.equal(await evaluate(`document.querySelector('${submit}').getAttribute('aria-disabled')`), 'true');
            await evaluate(`document.querySelector('${confirm}').click(); document.querySelector('${submit}').click();
                document.querySelector('form').requestSubmit(document.querySelector('${submit}'));
                document.querySelector('form').requestSubmit(document.querySelector('[name="intent"][value="draft"]'));`);
            await delay(100);
            assert.equal(submissions.length, 2, 'repeated clicks and submits cannot create a second request');
        });
        await t.test('back-forward restoration resets the pending UI and Livewire reinitialization does not duplicate it', async () => {
            await evaluate("window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})); document.dispatchEvent(new Event('livewire:navigated')); document.dispatchEvent(new Event('livewire:navigated'));");
            assert.equal(await evaluate("document.querySelector('form').hasAttribute('aria-busy')"), false);
            assert.equal(await evaluate(`document.querySelector('${confirm}').disabled`), false);
            assert.equal(await evaluate(`document.querySelector('${submit}').textContent.trim()`), 'Submit for Review');
            await click(submit);
            assert.equal(await open(), true);
            assert.equal(await evaluate("document.querySelectorAll('dialog[open]').length"), 1);
            await click(cancel);
            await evaluate("document.querySelector('form').requestSubmit()");
            assert.equal(await open(), true, 'programmatic submission without a submitter still asks for confirmation');
            await click(cancel);
        });
        for (const [mode, revision] of [['saved-empty', false], ['confirmation-revision', true]]) {
            await t.test(mode + ' uses the right copy and works at narrow widths', async () => {
                await load(mode);
                await evaluate('fillSetup()');
                await click(submit);
                assert.equal(await open(), true);
                assert.match(await evaluate("document.getElementById('event-review-confirmation-title').textContent"), revision ? /Submit changes for review/ : /Submit event for review/);
                if (revision) assert.match(await evaluate("document.getElementById('event-review-confirmation-description').textContent"), /published event will stay unchanged/);
                for (const width of [320, 375, 1280]) {
                    await client.send('Emulation.setDeviceMetricsOverride', { width, height: 800, deviceScaleFactor: 1, mobile: false });
                    assert.equal(await evaluate(`(() => { const box = document.querySelector('${dialog}').getBoundingClientRect(); return box.left >= 0 && box.right <= innerWidth && box.bottom <= innerHeight; })()`), true);
                }
                await click(cancel);
                await client.send('Emulation.clearDeviceMetricsOverride');
            });
        }
        await t.test('older-browser fallback still requires a decision and retains review intent', async () => {
            await evaluate(`document.querySelector('${dialog}').showModal = undefined; window.fallbackMessages = [];
                window.confirm = message => { fallbackMessages.push(message); return false; };
                document.querySelector('form').requestSubmit();`);
            assert.equal(submissions.length, 2);
            assert.equal(await open(), false);
            assert.match(await evaluate('fallbackMessages[0]'), /published event will stay unchanged/);
            await evaluate("window.fallbackConfirmCount = 0; window.confirm = () => { fallbackConfirmCount++; return true; }; document.querySelector('form').requestSubmit(); document.querySelector('form').requestSubmit()");
            await waitForPosts(3);
            assert.equal(await evaluate('fallbackConfirmCount'), 1);
            assert.match(submissions[2], /name="intent"\r\n\r\nsubmit/);
            assert.match(submissions[2], /name="review_version"\r\n\r\n0/);
        });
        assert.deepEqual(errors, []);
    } finally {
        // Graceful protocol shutdown releases Chrome's profile before its
        // ChildProcess handle; kill only our own child if it does not exit.
        if (client && browser?.exitCode === null) await client.send('Browser.close').catch(() => {});
        client?.close();
        if (browser && browser.exitCode === null && browser.signalCode === null) {
            await Promise.race([new Promise((resolve) => browser.once('exit', resolve)), delay(3000)]);
            if (browser.exitCode === null && browser.signalCode === null) {
                browser.kill();
                await Promise.race([new Promise((resolve) => browser.once('exit', resolve)), delay(5000)]);
            }
        }
        server.closeAllConnections();
        await new Promise((resolve) => server.close(resolve));
        const target = resolve(profile);
        assert.ok(target.startsWith(resolve(tmpdir()) + sep) && basename(target).startsWith('racetech-review-confirmation-'));
        await rm(target, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
});
