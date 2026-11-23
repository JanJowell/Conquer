import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, sep, basename } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync, spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { setTimeout as delay } from 'node:timers/promises';

const root = fileURLToPath(new URL('../../', import.meta.url));
const chrome = [process.env.CHROME_PATH, 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/chromium', '/usr/bin/google-chrome']
    .find(path => path && existsSync(path));

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
        const message = JSON.parse(data);
        if (!message.id) { listeners.get(message.method)?.(message.params); return; }
        const request = pending.get(message.id);
        if (!request) return;
        clearTimeout(request.timer); pending.delete(message.id);
        message.error ? request.reject(new Error(message.error.message)) : request.resolve(message.result);
    });
    return {
        send(method, params = {}) {
            return new Promise((resolve, reject) => {
                const id = ++next;
                const timer = setTimeout(() => { pending.delete(id); reject(new Error('Browser command timed out: ' + method)); }, 10000);
                pending.set(id, { resolve, reject, timer }); socket.send(JSON.stringify({ id, method, params }));
            });
        },
        on(method, callback) { listeners.set(method, callback); },
        close() { for (const request of pending.values()) { clearTimeout(request.timer); request.reject(new Error('Browser closed')); } pending.clear(); socket.close(); },
    };
}

test('event cleanup native confirmations use actual Blade markup and local-only synthetic submissions', { skip: !chrome, timeout: 90000 }, async t => {
    assert.ok(process.env.EVENT_CLEANUP_TEST_APP, 'explicit isolated verification application is required');
    const profile = await mkdtemp(join(tmpdir(), 'racetech-event-cleanup-'));
    const submissions = [], errors = [], fixtures = new Map();
    let browser, client;
    const server = createServer((request, response) => {
        const url = new URL(request.url, 'http://localhost');
        if (request.method === 'POST' && /^\/admin\/events\/7\/(cleanup|archive|restore|cancel)$/.test(url.pathname)) {
            let body = '';
            request.on('data', chunk => { body += chunk; });
            request.on('end', () => { submissions.push({ path: url.pathname, body: new URLSearchParams(body) }); response.writeHead(204).end(); });
        } else if (request.method === 'GET' && url.pathname === '/') {
            response.writeHead(200, { 'Content-Type': 'text/html' }).end(fixtures.get(url.searchParams.get('action')) || '');
        } else if (request.method === 'GET' && url.pathname === '/js/event-cleanup.js') {
            response.writeHead(200, { 'Content-Type': 'text/javascript' }).end(readFileSync(join(root, 'public/js/event-cleanup.js')));
        } else response.writeHead(404).end();
    });
    try {
        await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
        const origin = 'http://127.0.0.1:' + server.address().port;
        for (const action of ['delete', 'archive', 'restore', 'cancel']) {
            fixtures.set(action, execFileSync('php', ['tests/Js/fixtures/event-cleanup.php', origin, action, process.env.EVENT_CLEANUP_TEST_APP],
                { cwd: root, encoding: 'utf8', timeout: 20000 }));
        }
        browser = spawn(chrome, ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-background-networking',
            '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
        let startupError, port;
        browser.on('error', error => { startupError = error; });
        for (let attempt = 0; attempt < 100; attempt++) {
            if (startupError) throw startupError;
            try { port = (await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; } catch { await delay(100); }
        }
        assert.ok(port, 'owned headless browser started');
        const pages = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json();
        client = await connect(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
        client.on('Runtime.exceptionThrown', ({ exceptionDetails }) => errors.push(exceptionDetails.text));
        client.on('Fetch.requestPaused', ({ requestId, request }) => {
            const allowed = request.url.startsWith(origin + '/');
            client.send(allowed ? 'Fetch.continueRequest' : 'Fetch.failRequest', allowed ? { requestId } : { requestId, errorReason: 'BlockedByClient' })
                .catch(error => errors.push(error.message));
        });
        await client.send('Runtime.enable');
        await client.send('Fetch.enable', { patterns: [{ urlPattern: '*' }] });
        const evaluate = async expression => {
            const result = await client.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
            if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
            return result.result.value;
        };
        const until = async condition => { for (let attempt = 0; attempt < 100; attempt++) { if (await condition()) return; await delay(50); } assert.fail('Browser condition not met'); };
        const load = async action => {
            await client.send('Page.navigate', { url: origin + '/?action=' + action });
            await until(() => evaluate("document.readyState === 'complete' && document.getElementById('opener')?.dataset.cleanupInitialized === 'true'"));
        };
        const escape = async () => {
            await client.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
            await client.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
        };
        await load('delete');
        await t.test('dialog starts closed, escapes event titles, and has an accessible name', async () => {
            assert.equal(await evaluate("document.querySelector('dialog').open"), false);
            assert.equal(await evaluate("document.querySelector('event') === null"), true);
            assert.match(await evaluate("document.querySelector('dialog').textContent"), /Synthetic <event> & title/);
            assert.equal(await evaluate("document.getElementById(document.querySelector('dialog').getAttribute('aria-labelledby')).textContent.trim()"), 'Delete empty draft?');
            assert.equal(submissions.length, 0);
        });
        await t.test('cancel and Escape close without a request and return focus to the opener', async () => {
            await evaluate("document.getElementById('opener').focus(); document.getElementById('opener').click(); document.querySelector('[data-event-cleanup-close]').click()");
            await until(() => evaluate("!document.querySelector('dialog').open && document.activeElement.id === 'opener'"));
            await evaluate("document.getElementById('opener').click()"); await escape();
            await until(() => evaluate("!document.querySelector('dialog').open"));
            assert.equal(submissions.length, 0);
        });
        await t.test('native validation blocks an empty title and unchecked acknowledgement', async () => {
            await evaluate("document.getElementById('opener').click(); document.querySelector('form').requestSubmit()");
            assert.equal(await evaluate("document.querySelector('form').checkValidity()"), false);
            await evaluate("document.querySelector('[name=confirmation_title]').value='Synthetic <event> & title'; document.querySelector('form').requestSubmit()");
            await delay(100); assert.equal(submissions.length, 0);
        });
        await t.test('one confirmed delete preserves its method and fingerprint and blocks repeated submission or dismissal', async () => {
            await evaluate("window.cleanupEvents=[]; window.addEventListener('pageshow',()=>cleanupEvents.push('pageshow')); document.querySelector('dialog').addEventListener('cancel',e=>cleanupEvents.push('cancel:'+e.defaultPrevented+':'+document.querySelector('dialog').dataset.cleanupPending)); document.querySelector('dialog').addEventListener('close',()=>cleanupEvents.push('close'))");
            await evaluate("document.querySelector('[name=confirmation]').checked=true; document.querySelector('form').requestSubmit(); document.querySelector('form').requestSubmit()");
            await until(async () => submissions.length === 1);
            assert.equal(submissions[0].body.get('_method'), 'DELETE');
            assert.equal(submissions[0].body.get('confirmation_title'), 'Synthetic <event> & title');
            assert.match(submissions[0].body.get('cleanup_fingerprint'), /^[a-f0-9]{64}$/);
            const beforeDismiss = await evaluate("({open:document.querySelector('dialog').open,pending:document.querySelector('dialog').dataset.cleanupPending})");
            await evaluate("document.querySelector('[data-event-cleanup-close]').click()");
            const afterCancel = await evaluate("({open:document.querySelector('dialog').open,pending:document.querySelector('dialog').dataset.cleanupPending})");
            await escape();
            assert.equal(await evaluate("document.querySelector('dialog').open"), true, JSON.stringify({beforeDismiss,afterCancel,events:await evaluate('cleanupEvents')}));
            await delay(100); assert.equal(submissions.length, 1);
        });
        await t.test('back-forward state and repeated navigation initialization reset safely without duplicate bindings', async () => {
            await evaluate("window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})); document.dispatchEvent(new Event('livewire:navigated')); document.dispatchEvent(new Event('livewire:navigated')); document.querySelector('[data-event-cleanup-close]').click()");
            await until(() => evaluate("!document.querySelector('dialog').open"));
            await evaluate("document.getElementById('opener').click()");
            assert.equal(await evaluate("document.querySelector('[name=confirmation]').checked"), false);
            assert.equal(await evaluate("document.querySelector('[data-event-cleanup-submit]').disabled"), false);
            assert.equal(await evaluate("document.querySelectorAll('dialog[open]').length"), 1);
        });
        for (const action of ['archive', 'restore']) {
            await t.test(action + ' requires acknowledgement and states that history is preserved', async () => {
                await load(action);
                await evaluate("document.getElementById('opener').click(); document.querySelector('form').requestSubmit()");
                assert.equal(await evaluate("document.querySelector('[name=confirmation_title]') === null"), true);
                assert.match(await evaluate("document.querySelector('dialog').textContent"), /history|checkpoint/);
                const count = submissions.length;
                await delay(100); assert.equal(submissions.length, count);
                await evaluate("document.querySelector('[name=confirmation]').checked=true; document.querySelector('form').requestSubmit()");
                await until(async () => submissions.length === count + 1);
                assert.equal(submissions.at(-1).path, '/admin/events/7/' + action);
                assert.equal(submissions.at(-1).body.get('confirmation'), '1');
                assert.equal(submissions.at(-1).body.has('_method'), false);
            });
        }
        await t.test('cancellation keeps title and warning visible but needs only reason and acknowledgement', async () => {
            await load('cancel');
            await evaluate("document.getElementById('opener').click()");
            assert.equal(await evaluate("document.querySelector('[name=confirmation_title]') === null"), true);
            assert.match(await evaluate("document.querySelector('dialog').textContent"), /Synthetic <event> & title/);
            assert.match(await evaluate("document.querySelector('dialog').textContent"), /No automatic refund is issued/);
            assert.equal(await evaluate("document.querySelector('event') === null"), true);
            const count = submissions.length;
            await evaluate("document.querySelector('form').requestSubmit()");
            assert.equal(await evaluate("document.querySelector('form').checkValidity()"), false);
            await evaluate("document.querySelector('[name=cancellation_reason]').value='Synthetic weather reason'; document.querySelector('form').requestSubmit()");
            await delay(100); assert.equal(submissions.length, count);
            await evaluate("document.querySelector('[name=cancellation_reason]').value=''; document.querySelector('[name=confirmation]').checked=true; document.querySelector('form').requestSubmit()");
            await delay(100); assert.equal(submissions.length, count);
            await evaluate("document.querySelector('[name=cancellation_reason]').value='Synthetic weather reason'; document.querySelector('form').requestSubmit(); document.querySelector('form').requestSubmit()");
            await until(async () => submissions.length === count + 1);
            assert.equal(submissions.at(-1).path, '/admin/events/7/cancel');
            assert.equal(submissions.at(-1).body.get('cancellation_reason'), 'Synthetic weather reason');
            assert.equal(submissions.at(-1).body.get('confirmation'), '1');
            assert.match(submissions.at(-1).body.get('cancellation_fingerprint'), /^[a-f0-9]{64}$/);
            assert.equal(submissions.at(-1).body.has('confirmation_title'), false);
            assert.equal(submissions.at(-1).body.has('_method'), false);
        });
        assert.deepEqual(errors, []);
    } finally {
        if (client && browser?.exitCode === null) await client.send('Browser.close').catch(() => {});
        client?.close();
        if (browser && browser.exitCode === null && browser.signalCode === null) {
            await Promise.race([new Promise(resolve => browser.once('exit', resolve)), delay(3000)]);
            if (browser.exitCode === null && browser.signalCode === null) { browser.kill(); await delay(500); }
        }
        server.closeAllConnections(); await new Promise(resolve => server.close(resolve));
        const target = resolve(profile);
        assert.ok(target.startsWith(resolve(tmpdir()) + sep) && basename(target).startsWith('racetech-event-cleanup-'));
        await rm(target, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
});
