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

test('payment fields switch providers without losing input or submitting hidden account fields', { skip: !chrome, timeout: 60000 }, async () => {
    const profile = await mkdtemp(join(tmpdir(), 'racetech-payment-fields-'));
    const html = execFileSync('php', ['tests/Js/fixtures/payment-option-fields.php'], { cwd: root, encoding: 'utf8' });
    let browser, socket;
    const pending = new Map();
    let id = 0;
    const server = createServer((request, response) => {
        if (request.url === '/') { response.setHeader('Content-Type', 'text/html'); response.end(html); return; }
        if (request.url === '/js/payment-option-fields.js') {
            response.setHeader('Content-Type', 'text/javascript');
            response.end(readFileSync(join(root, 'public/js/payment-option-fields.js'))); return;
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
        await send('Page.navigate', { url: 'http://127.0.0.1:' + server.address().port });
        for (let attempt = 0; attempt < 100; attempt++) {
            if (await evaluate("document.readyState === 'complete' && Boolean(document.querySelector('[data-payment-option-fields]'))")) break;
            await delay(50);
        }
        const select = value => evaluate(`document.querySelector('#option-provider').value=${JSON.stringify(value)};document.querySelector('#option-provider').dispatchEvent(new Event('change'));`);
        const setNumber = value => evaluate(`document.querySelector('#option-account_number').value=${JSON.stringify(value)};`);
        const state = () => evaluate(`(() => {
            const n = document.querySelector('#option-account_number');
            return { hidden: document.querySelector('[data-manual-account]').hidden, disabled: n.disabled,
                required: n.required, valid: n.checkValidity(), number: n.value, type: n.type,
                mode: n.inputMode, max: n.maxLength, pattern: n.getAttribute('pattern'),
                instructionsRequired: document.querySelector('#option-instructions').required,
                submitted: new FormData(document.querySelector('form')).has('account_number') };
        })()`);
        let current = await state();
        assert.equal(current.hidden, true); assert.equal(current.disabled, true); assert.equal(current.submitted, false);
        await select('Bank'); await setNumber('000123456789012345');
        current = await state();
        assert.equal(current.hidden, false); assert.equal(current.required, true); assert.equal(current.pattern, null);
        assert.equal(current.instructionsRequired, true); assert.equal(current.valid, true);
        await select('GCash');
        current = await state();
        assert.equal(current.number, '000123456789012345'); assert.equal(current.valid, false);
        assert.equal(current.max, 11); assert.equal(current.mode, 'numeric'); assert.equal(current.type, 'text');
        assert.equal(current.instructionsRequired, false);
        await setNumber('09171234567'); assert.equal((await state()).valid, true);
        await setNumber('091712345678'); assert.equal((await state()).valid, false);
        await setNumber('0917ABC4567'); assert.equal((await state()).valid, false);
        const paste = (text, start, end) => evaluate(`(() => {
            const field = document.querySelector('#option-account_number');
            field.focus(); field.setSelectionRange(${start}, ${end});
            const data = new DataTransfer(); data.setData('text/plain', ${JSON.stringify(text)});
            const event = new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true });
            field.dispatchEvent(event);
            return { blocked: event.defaultPrevented, value: field.value, valid: field.checkValidity(),
                error: document.querySelector('#option-number-input-error').textContent,
                errorHidden: document.querySelector('#option-number-input-error').hidden };
        })()`);
        await setNumber('09171234567');
        let pasted = await paste('099912345678', 0, 11);
        assert.equal(pasted.blocked, true); assert.equal(pasted.value, '09171234567');
        assert.equal(pasted.valid, false); assert.match(pasted.error, /Nothing was pasted or shortened/);
        assert.equal(pasted.errorHidden, false);
        await evaluate("document.querySelector('#option-account_number').dispatchEvent(new Event('input', {bubbles:true}));");
        assert.equal((await state()).valid, true);
        pasted = await paste('09991234567', 0, 11);
        assert.equal(pasted.blocked, false);
        pasted = await paste('9', 11, 11);
        assert.equal(pasted.blocked, true); assert.equal(pasted.value, '09171234567');
        await evaluate("document.querySelector('#option-account_number').dispatchEvent(new Event('input'));document.querySelector('#option-account_number').focus();document.querySelector('#option-account_number').setSelectionRange(11,11);");
        await send('Input.insertText', { text: '9' });
        assert.equal((await state()).number, '09171234567');
        await select('Maya');
        assert.equal((await state()).max, 11);
        assert.equal((await paste('099912345678', 0, 11)).blocked, true);
        await select('Maya'); await setNumber('09991234567'); assert.equal((await state()).valid, true);
        await select('PayMongo');
        current = await state();
        assert.equal(current.submitted, false); assert.equal(current.required, false); assert.equal(current.number, '09991234567');
        assert.equal(await evaluate("new FormData(document.querySelector('form')).has('account_name')"), false);
        await select('Bank');
        assert.equal((await state()).number, '09991234567'); assert.equal((await state()).submitted, true);
        assert.equal((await state()).max, 255); assert.equal((await state()).valid, true);
        pasted = await paste('000123456789012345', 0, 11);
        assert.equal(pasted.blocked, false);
        await select(''); assert.equal((await state()).disabled, true);
    } finally {
        for (const request of pending.values()) clearTimeout(request.timeout);
        socket?.close();
        if (browser && browser.exitCode === null) {
            const closed = new Promise(resolve => browser.once('exit', resolve));
            browser.kill(); await Promise.race([closed, delay(5000)]);
        }
        await new Promise(resolve => server.close(resolve));
        const resolved = resolve(profile);
        assert.ok(resolved.startsWith(resolve(tmpdir()) + sep) && resolved.split(sep).at(-1).startsWith('racetech-payment-fields-'));
        await rm(resolved, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
});
