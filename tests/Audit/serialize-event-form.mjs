// Diagnostic only: render a synthetic edit form, run its actual JavaScript and
// serialize it without submitting anything to a running Laravel application.
import { existsSync, readFileSync } from 'node:fs';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, sep } from 'node:path';
import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { setTimeout as delay } from 'node:timers/promises';

const chrome = [process.env.CHROME_PATH, 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(path => path && existsSync(path));
if (!chrome) throw new Error('A browser is required for this explicit audit.');
const root = resolve(import.meta.dirname, '../..');
const publicRoot = resolve(root, 'public');
const profile = await mkdtemp(join(tmpdir(), 'racetech-form-audit-'));
let browser, socket, origin;
const pending = new Map();
let nextId = 0;
const send = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++nextId;
    const timeout = setTimeout(() => { pending.delete(id); reject(new Error('Browser command timed out: ' + method)); }, 10000);
    pending.set(id, { resolve, reject, timeout });
    socket.send(JSON.stringify({ id, method, params }));
});
const server = createServer((request, response) => {
    const path = new URL(request.url, origin).pathname;
    if (path === '/') {
        response.setHeader('Content-Type', 'text/html');
        // All network resources stay on this disposable local fixture server.
        response.end(readFileSync(process.argv[2], 'utf8').replace(/https?:\/\/[^/\s"'<>]+/g, origin));
        return;
    }
    const asset = resolve(publicRoot, '.' + path);
    if (asset.startsWith(publicRoot + sep) && /\.(js|css)$/.test(asset) && existsSync(asset)) {
        response.setHeader('Content-Type', asset.endsWith('.js') ? 'text/javascript' : 'text/css');
        response.end(readFileSync(asset));
    } else response.writeHead(404).end();
});
try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    origin = 'http://127.0.0.1:' + server.address().port;
    browser = spawn(chrome, ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-background-networking',
        '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    let port;
    for (let attempt = 0; attempt < 200; attempt++) {
        try { port = (await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; }
        catch { await delay(100); }
    }
    if (!port) throw new Error('Browser did not start.');
    const pages = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json();
    socket = new WebSocket(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });
    socket.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data);
        if (message.id) {
            const request = pending.get(message.id);
            if (!request) return;
            clearTimeout(request.timeout);
            pending.delete(message.id);
            message.error ? request.reject(new Error(message.error.message)) : request.resolve(message.result);
        } else if (message.method === 'Fetch.requestPaused') {
            const local = message.params.request.url.startsWith(origin + '/');
            send(local ? 'Fetch.continueRequest' : 'Fetch.failRequest', { requestId: message.params.requestId,
                ...(!local ? { errorReason: 'BlockedByClient' } : {}) }).catch(() => {});
        }
    });
    await send('Fetch.enable', { patterns: [{ urlPattern: '*' }] });
    await send('Page.navigate', { url: origin + '/' });
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
        return result.result.value;
    };
    for (let attempt = 0; attempt < 150; attempt++) {
        if (await evaluate("document.readyState === 'complete' && Boolean(document.querySelector('#interest_type'))")) break;
        if (attempt === 149) throw new Error('Form did not load.');
        await delay(50);
    }
    const result = await evaluate(`(() => {
        const form = document.querySelector('form[enctype="multipart/form-data"]');
        const entries = [...new FormData(form)].filter(([, value]) => typeof value === 'string');
        return { encoded: new URLSearchParams(entries).toString(),
            invalid: [...form.elements].filter(field => field.willValidate && !field.validity.valid).map(field => field.name),
            invalidDetails: [...form.elements].filter(field => field.willValidate && !field.validity.valid)
                .map(field => ({ name: field.name, value: field.value, stepMismatch: field.validity.stepMismatch, missing: field.validity.valueMissing })),
            uploadedFileInputs: [...form.querySelectorAll('input[type=file]')].map(field => field.name) };
    })()`);
    process.stdout.write(JSON.stringify(result));
} finally {
    socket?.close();
    browser?.kill();
    await new Promise(resolve => server.close(resolve));
    const resolvedProfile = resolve(profile);
    if (resolvedProfile.startsWith(resolve(tmpdir()) + sep) && /^racetech-form-audit-[\w-]+$/.test(resolvedProfile.split(sep).pop())) {
        await rm(resolvedProfile, { recursive: true, force: true, maxRetries: 10, retryDelay: 100 });
    }
}
