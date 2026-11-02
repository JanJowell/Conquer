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

test('cutoff controls preserve legacy values, validate custom input and isolate event types', { skip: !chrome, timeout: 90000 }, async () => {
    const profile = await mkdtemp(join(tmpdir(), 'racetech-cutoff-browser-'));
    let browser, client, html;
    const server = createServer((request, response) => {
        const path = new URL(request.url, 'http://localhost').pathname;
        if (path === '/') { response.setHeader('Content-Type', 'text/html'); response.end(html); return; }
        if (/^\/js\/[a-z0-9-]+\.js$/.test(path) || (path.startsWith('/build/') && !path.includes('..'))) {
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
            const result = await client.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
            if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
            return result.result.value;
        };
        await client.send('Page.navigate', { url: origin });
        for (let i = 0; i < 100; i++) {
            if (await evaluate("document.readyState === 'complete' && Boolean(document.querySelector('[data-duration-preset]'))")) break;
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
        for (const width of [1280, 320]) {
            await client.send('Emulation.setDeviceMetricsOverride', { width, height: 850, deviceScaleFactor: 1, mobile: false });
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, 'form fits width ' + width);
        }
    } finally {
        client?.close();
        if (browser && browser.exitCode === null) {
            const exited = new Promise(resolve => browser.once('exit', resolve));
            browser.kill();
            await Promise.race([exited, delay(5000)]);
        }
        server.closeAllConnections();
        await new Promise(resolve => server.close(resolve));
        assert.equal(resolve(profile).startsWith(resolve(tmpdir()) + (process.platform === 'win32' ? '\\' : '/')), true);
        await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
});
