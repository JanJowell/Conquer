import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
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
const catalog = JSON.parse(readFileSync(join(root, 'tests/Fixtures/venue-locations.json'), 'utf8'));

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
                const timeout = setTimeout(() => { pending.delete(next); reject(new Error('Browser command timed out: ' + method)); }, 10000);
                pending.set(next, { resolve, reject, timeout });
                socket.send(JSON.stringify({ id: next, method, params }));
            });
        },
        close() { socket.close(); },
    };
}

test('direct venue fields handle dependent locations, safe editing, stale responses, errors and mobile layouts', { skip: !chrome, timeout: 90000 }, async () => {
    const profile = await mkdtemp(join(tmpdir(), 'racetech-venue-browser-'));
    let browser, client;
    const documents = new Map();
    let responseMode = 'normal';
    const requests = [];
    const server = createServer(async (request, response) => {
        const url = new URL(request.url, 'http://localhost');
        if (documents.has(url.pathname)) {
            response.setHeader('Content-Type', 'text/html; charset=utf-8');
            response.end(documents.get(url.pathname));
            return;
        }
        if (url.pathname === '/admin/venue-locations') {
            requests.push(url.search);
            if (responseMode === 'expired') { response.writeHead(401, { 'Content-Type': 'application/json' }).end('{}'); return; }
            if (url.searchParams.get('parent') === '0402100000') await delay(250);
            const level = url.searchParams.get('level');
            const parentKey = { cities: 'area_code', barangays: 'city_code' }[level];
            const parent = url.searchParams.get('parent');
            const rows = level === 'barangays' && /^\d{10}$/.test(parent)
                ? JSON.parse(readFileSync(join(root, 'tests/Fixtures', catalog.barangay_directory, parent + '.json'), 'utf8'))
                : catalog[level];
            const data = Object.values(rows).filter(row => !parentKey || row[parentKey] === parent);
            response.writeHead(200, { 'Content-Type': 'application/json' }).end(JSON.stringify({ data }));
            return;
        }
        if (/^\/js\/[a-z0-9-]+\.js$/.test(url.pathname) || (url.pathname.startsWith('/build/') && !url.pathname.includes('..'))) {
            const asset = join(root, 'public' + url.pathname);
            if (existsSync(asset)) {
                response.setHeader('Content-Type', url.pathname.endsWith('.css') ? 'text/css; charset=utf-8' : 'text/javascript; charset=utf-8');
                response.end(readFileSync(asset)); return;
            }
        }
        response.writeHead(404).end();
    });
    try {
        await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
        const origin = 'http://127.0.0.1:' + server.address().port;
        for (const state of ['', 'legacy', 'restored', 'structured', 'unavailable']) {
            documents.set('/' + state, execFileSync('php', ['tests/Js/fixtures/venue-picker.php', origin, state], { cwd: root, encoding: 'utf8' }));
        }
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
        const waitFor = async expression => {
            for (let i = 0; i < 100; i++) {
                if (await evaluate(expression)) return;
                await delay(40);
            }
            throw new Error('Condition not reached: ' + expression);
        };
        const select = (id, value) => evaluate(`document.getElementById(${JSON.stringify(id)}).value=${JSON.stringify(value)};document.getElementById(${JSON.stringify(id)}).dispatchEvent(new Event('change',{bubbles:true}));`);
        const screenshots = process.env.VENUE_SCREENSHOTS ? await mkdtemp(join(tmpdir(), 'racetech-venue-preview-')) : null;
        const capture = async state => {
            if (!screenshots) return;
            for (const width of [1280, 320]) {
                await client.send('Emulation.setDeviceMetricsOverride', { width, height: 850, deviceScaleFactor: 1, mobile: false });
                const image = await client.send('Page.captureScreenshot', { format: 'png' });
                const path = join(screenshots, `${state}-${width}.png`);
                await writeFile(path, Buffer.from(image.data, 'base64'));
                console.log('Preview:', path);
            }
            await client.send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 850, deviceScaleFactor: 1, mobile: false });
        };
        await client.send('Page.navigate', { url: origin });
        await waitFor("document.readyState === 'complete'");
        await waitFor("document.querySelector('#venue_area_code')?.options.length === 4");
        assert.equal(await evaluate("document.querySelector('[data-venue-mode]').value"), 'new');
        assert.notEqual(await evaluate("getComputedStyle(document.querySelector('[data-venue-new]')).display"), 'none');
        assert.equal(await evaluate("new FormData(document.querySelector('form')).has('venue_name')"), true);
        assert.equal(await evaluate("document.querySelector('[data-venue-choice]')"), null);
        assert.equal(await evaluate("document.querySelectorAll('select').length"), 3);
        assert.equal(await evaluate("document.querySelector('#venue-search, #venue_address')"), null);
        assert.equal(await evaluate("document.querySelector('form').checkValidity()"), false);
        assert.equal(requests.length, 1, 'only the area list loads before a parent is chosen');
        await capture('initial');
        assert.equal(await evaluate('Boolean(window.venueXss)'), false);
        await select('venue_area_code', '0402100000');
        await delay(30);
        await select('venue_area_code', '0401000000');
        await waitFor("document.querySelector('#venue_city_code').options[1]?.value === '0401029000'");
        await delay(350);
        assert.equal(await evaluate("document.querySelector('#venue_city_code').options[1].value"), '0401029000');
        await select('venue_city_code', '0401029000');
        await waitFor("document.querySelector('#venue_barangay_code').options.length === 2");
        await select('venue_barangay_code', '0401029001');
        await evaluate("document.querySelector('#venue_name').value='Assembly Hall, Gate 2';document.querySelector('#venue_name').dispatchEvent(new Event('input'));");
        assert.match(await evaluate("document.querySelector('[data-venue-preview]').textContent"), /Assembly Hall, Gate 2 — Test Barangay B, Test Municipality B, Batangas/);
        assert.equal(await evaluate("document.querySelector('form').checkValidity()"), true);
        await capture('filled');
        await select('venue_area_code', '0402100000');
        assert.equal(await evaluate("document.querySelector('#venue_city_code').value"), '');
        assert.equal(await evaluate("document.querySelector('#venue_barangay_code').value"), '');
        await evaluate("document.querySelector('#venue_name').value='<img src=x onerror=window.venueXss=true>';document.querySelector('#venue_name').dispatchEvent(new Event('input'));");
        assert.equal(await evaluate('Boolean(window.venueXss)'), false);
        assert.equal(await evaluate("document.querySelector('[data-venue-preview] img')"), null);
        await evaluate("document.querySelector('#venue_name').value='Assembly Hall, Gate 2';document.querySelector('#venue_name').dispatchEvent(new Event('input'));");
        for (const width of [1280, 320]) {
            await client.send('Emulation.setDeviceMetricsOverride', { width, height: 850, deviceScaleFactor: 1, mobile: false });
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, 'venue form fits width ' + width);
            await waitFor("!document.querySelector('#venue_area_code').disabled");
            assert.equal(await evaluate("document.querySelector('#venue_name').value"), 'Assembly Hall, Gate 2');
        }
        responseMode = 'expired';
        await client.send('Page.navigate', { url: origin + '/?expired' });
        await waitFor("document.readyState === 'complete'");
        await waitFor("document.querySelector('[data-venue-status]')?.textContent.includes('sign in again')");
        assert.equal(await evaluate("document.querySelector('#venue_area_code').disabled"), true);
        responseMode = 'normal';
        await evaluate("document.querySelector('[data-venue-retry]').click()");
        await waitFor("document.querySelector('#venue_area_code').options.length === 4");
        const beforeEdit = requests.length;
        await client.send('Page.navigate', { url: origin + '/legacy' });
        await waitFor("document.readyState === 'complete'");
        await waitFor("document.querySelector('[data-venue-mode]')?.value === 'keep'");
        assert.equal(requests.length, beforeEdit, 'keeping an existing venue does not require reference requests');
        assert.equal(await evaluate("new FormData(document.querySelector('form')).has('venue_name')"), false);
        assert.equal(await evaluate('Boolean(window.venueXss)'), false);
        assert.equal(await evaluate("document.querySelector('[data-venue-picker] img')"), null);
        await evaluate("document.querySelector('[data-venue-change]').click()");
        await waitFor("document.querySelector('#venue_area_code').options.length === 4");
        assert.equal(await evaluate("document.querySelector('#venue_area_code').value"), '', 'legacy addresses are not guessed');
        assert.equal(await evaluate("document.querySelector('#venue_name').value"), '');
        assert.equal(await evaluate("document.querySelector('form').checkValidity()"), false);
        await evaluate("document.querySelector('[data-venue-cancel]').click()");
        assert.equal(await evaluate("new FormData(document.querySelector('form')).get('venue_mode')"), 'keep');
        assert.equal(await evaluate("new FormData(document.querySelector('form')).has('venue_name')"), false);
        assert.equal(await evaluate("document.querySelector('form').checkValidity()"), true);
        await client.send('Page.navigate', { url: origin + '/structured' });
        await waitFor("document.readyState === 'complete'");
        await waitFor("document.querySelector('[data-venue-change]') && document.querySelector('#venue_name').value === 'Assembly Hall, Gate 2'");
        await evaluate("document.querySelector('[data-venue-change]').click()");
        await waitFor("document.querySelector('#venue_barangay_code').value === '0401029001'");
        assert.equal(await evaluate("document.querySelector('form').checkValidity()"), true);
        await select('venue_area_code', '0402100000');
        await evaluate("document.querySelector('[data-venue-cancel]').click()");
        await delay(350);
        assert.equal(await evaluate("document.querySelector('[data-venue-mode]').value"), 'keep');
        assert.equal(await evaluate("getComputedStyle(document.querySelector('[data-venue-new]')).display"), 'none');
        assert.equal(await evaluate("document.querySelector('[data-venue-status]').textContent"), '');
        await client.send('Page.navigate', { url: origin + '/unavailable' });
        await waitFor("document.readyState === 'complete'");
        await waitFor("document.querySelector('[data-venue-change]')?.disabled");
        assert.equal(await evaluate("document.querySelector('form').checkValidity()"), true, 'an unavailable reference cannot block keeping the current venue');
        await client.send('Page.navigate', { url: origin + '/restored' });
        await waitFor("document.readyState === 'complete'");
        await waitFor("document.querySelector('#venue_barangay_code')?.value === '0401029001'");
        assert.equal(await evaluate("document.querySelector('[data-venue-mode]').value"), 'new');
        assert.equal(await evaluate("document.querySelector('#venue_name').value"), 'Municipal Hall, Gate 2');
        assert.equal(await evaluate("document.querySelector('form').checkValidity()"), true);
        assert.equal(await evaluate("document.querySelector('[data-venue-preview]').textContent"), 'Municipal Hall, Gate 2 — Test Barangay B, Test Municipality B, Batangas');
        assert.ok(requests.length > 0);
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
