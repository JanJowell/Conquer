import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, sep } from 'node:path';
import { spawn, execFileSync } from 'node:child_process';
import { createServer } from 'node:http';
import { setTimeout as delay } from 'node:timers/promises';

const root = resolve(import.meta.dirname, '../..');
const chrome = [process.env.CHROME_PATH, 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/chromium', '/usr/bin/google-chrome']
    .find(path => path && existsSync(path));

test('event payment choices deduplicate saved accounts and replace only the same provider', { skip: !chrome, timeout: 60000 }, async () => {
    const profile = await mkdtemp(join(tmpdir(), 'racetech-payment-selection-'));
    let browser, socket;
    const pending = new Map();
    let id = 0;
    const server = createServer((request, response) => {
        const url = new URL(request.url, 'http://127.0.0.1');
        if (url.pathname === '/') {
            const origin = 'http://127.0.0.1:' + server.address().port;
            const mode = url.searchParams.get('mode') || 'saved';
            response.setHeader('Content-Type', 'text/html');
            response.end(execFileSync('php', ['tests/Js/fixtures/event-payment-selection.php', origin, mode], { cwd: root, encoding: 'utf8' }));
            return;
        }
        if (url.pathname === '/js/event-payment-selection.js') {
            response.setHeader('Content-Type', 'text/javascript');
            response.end(readFileSync(join(root, 'public/js/event-payment-selection.js')));
            return;
        }
        response.writeHead(404).end();
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        const next = ++id;
        const timeout = setTimeout(() => { pending.delete(next); reject(new Error('Browser command timed out')); }, 10000);
        pending.set(next, { resolve, reject, timeout });
        socket.send(JSON.stringify({ id: next, method, params }));
    });
    try {
        await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
        browser = spawn(chrome, ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-background-networking',
            '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
        let port;
        for (let attempt = 0; attempt < 100; attempt++) {
            try { port = (await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; }
            catch { await delay(100); }
        }
        assert.ok(port, 'Browser must start');
        const pages = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json();
        socket = new WebSocket(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
        await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
        socket.addEventListener('message', ({ data }) => {
            const message = JSON.parse(data);
            const request = pending.get(message.id);
            if (!request) return;
            clearTimeout(request.timeout); pending.delete(message.id);
            message.error ? request.reject(new Error(message.error.message)) : request.resolve(message.result);
        });
        const evaluate = async expression => {
            const result = await send('Runtime.evaluate', { expression, returnByValue: true });
            if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
            return result.result.value;
        };
        const load = async mode => {
            await send('Page.navigate', { url: 'http://127.0.0.1:' + server.address().port + '/?mode=' + mode });
            for (let attempt = 0; attempt < 100; attempt++) {
                if (await evaluate(`location.search === '?mode=${mode}' && document.readyState === 'complete' && document.querySelector('[data-event-payment-selection]')?.dataset.paymentSelectionBound === 'true'`)) return;
                await delay(50);
            }
            assert.fail('Payment-selection script must initialize');
        };
        const checked = () => evaluate("Array.from(document.querySelectorAll('#event-form input[type=checkbox]:checked'), input => input.id)");
        const click = id => evaluate(`document.getElementById(${JSON.stringify(id)}).click()`);
        await load('saved');
        assert.equal(await evaluate("document.querySelectorAll('#event-form input[type=checkbox]').length"), 3);
        assert.equal(await evaluate("document.getElementById('event-payment-option-1')"), null);
        assert.deepEqual(await checked(), ['event-payment-copy-1']);
        await click('event-payment-option-3');
        assert.deepEqual(await checked(), ['event-payment-copy-1', 'event-payment-option-3']);
        await click('event-payment-option-2');
        assert.deepEqual(await checked(), ['event-payment-option-2', 'event-payment-option-3']);
        assert.equal(await evaluate("document.querySelector('[data-payment-selection-status]').textContent"), 'GCash selection replaced.');
        assert.deepEqual(await evaluate("new FormData(document.querySelector('#event-form')).getAll('payment_choices[]')"), ['option:2:1', 'option:3:1']);
        // Native keyboard activation must follow the same rule as pointer clicks.
        await evaluate("document.getElementById('event-payment-copy-1').focus()");
        await send('Input.dispatchKeyEvent', { type: 'keyDown', key: ' ', code: 'Space', windowsVirtualKeyCode: 32 });
        await send('Input.dispatchKeyEvent', { type: 'keyUp', key: ' ', code: 'Space', windowsVirtualKeyCode: 32 });
        assert.deepEqual(await checked(), ['event-payment-copy-1', 'event-payment-option-3']);
        await click('event-payment-copy-1');
        assert.deepEqual(await checked(), ['event-payment-option-3']);
        // Another selector on the page must not lose its own selection.
        await evaluate(`(() => {
            const form = document.createElement('form'); form.id = 'other-form';
            const section = document.querySelector('[data-event-payment-selection]').cloneNode(true);
            section.removeAttribute('data-payment-selection-bound');
            section.querySelectorAll('input').forEach(input => { input.id = 'other-' + input.id; input.checked = input.dataset.paymentProvider === 'GCash' && input.id.includes('copy'); });
            form.append(section); document.body.append(form);
            document.dispatchEvent(new Event('livewire:navigated'));
            document.dispatchEvent(new Event('livewire:navigated'));
        })()`);
        await evaluate(readFileSync(join(root, 'public/js/event-payment-selection.js'), 'utf8'));
        await click('event-payment-option-2');
        assert.equal(await evaluate("document.getElementById('other-event-payment-copy-1').checked"), true);
        assert.deepEqual(await checked(), ['event-payment-option-2', 'event-payment-option-3']);
        await evaluate("document.getElementById('event-form').reset()");
        assert.deepEqual(await checked(), ['event-payment-copy-1']);
        await click('event-payment-option-2');
        assert.deepEqual(await checked(), ['event-payment-option-2']);
        await load('empty');
        assert.deepEqual(await checked(), []);
        await load('disabled');
        assert.deepEqual(await checked(), []);
        await click('event-payment-copy-1');
        assert.deepEqual(await checked(), ['event-payment-copy-1']);
        await load('library');
        assert.deepEqual(await checked(), ['event-payment-option-1']);
        assert.equal(await evaluate("document.getElementById('event-payment-copy-1')"), null);
        await load('conflict');
        assert.deepEqual(await checked(), ['event-payment-copy-1', 'event-payment-option-1']);
        assert.match(await evaluate("document.querySelector('[role=alert]').textContent"), /Choose only one/);
        await click('event-payment-option-2');
        assert.deepEqual(await checked(), ['event-payment-option-2']);
        await send('Emulation.setScriptExecutionDisabled', { value: true });
        await send('Page.navigate', { url: 'http://127.0.0.1:' + server.address().port + '/?mode=saved' });
        for (let attempt = 0; attempt < 100; attempt++) {
            if (await evaluate("location.search === '?mode=saved' && document.readyState === 'complete' && Boolean(document.querySelector('#event-form'))")) break;
            await delay(50);
        }
        assert.equal(await evaluate("document.querySelectorAll('#event-form input[type=checkbox]').length"), 3);
        assert.deepEqual(await checked(), ['event-payment-copy-1']);
    } finally {
        for (const request of pending.values()) clearTimeout(request.timeout);
        socket?.close();
        if (browser && browser.exitCode === null) {
            const closed = new Promise(resolve => browser.once('exit', resolve));
            browser.kill(); await Promise.race([closed, delay(5000)]);
        }
        await new Promise(resolve => server.close(resolve));
        const resolved = resolve(profile);
        assert.ok(resolved.startsWith(resolve(tmpdir()) + sep) && /^racetech-payment-selection-/.test(resolved.split(sep).at(-1)));
        await rm(resolved, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
});
