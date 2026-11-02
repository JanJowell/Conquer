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
const chrome = [process.env.CHROME_PATH,
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    '/usr/bin/chromium', '/usr/bin/google-chrome',
].find((path) => path && existsSync(path));

// Use Chrome's built-in protocol and Node 22+; no browser package installation.
async function connect(url) {
    const socket = new WebSocket(url);
    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });
    let id = 0;
    const pending = new Map();
    const listeners = new Map();
    socket.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data);
        if (message.id) {
            const request = pending.get(message.id);
            if (!request) return;
            clearTimeout(request.timeout);
            pending.delete(message.id);
            message.error ? request.reject(new Error(message.error.message)) : request.resolve(message.result);
        } else listeners.get(message.method)?.(message.params);
    });
    return {
        send(method, params = {}) {
            return new Promise((resolve, reject) => {
                const requestId = ++id;
                const timeout = setTimeout(() => { pending.delete(requestId); reject(new Error(`Timed out: ${method}`)); }, 10000);
                pending.set(requestId, { resolve, reject, timeout });
                socket.send(JSON.stringify({ id: requestId, method, params }));
            });
        },
        on(method, callback) { listeners.set(method, callback); },
        close() { socket.close(); },
    };
}


test('security screens: responsive layout, OTP paste, recovery and password controls', { skip: !chrome, timeout: 90000 }, async () => {
    const profile = await mkdtemp(join(tmpdir(), 'racetech-security-browser-'));
    let browser, client;
    const pagesHtml = {};
    const server = createServer((request, response) => {
        const path = new URL(request.url, 'http://localhost').pathname;
        if (pagesHtml[path]) { response.setHeader('Content-Type', 'text/html'); response.end(pagesHtml[path]); return; }
        const assets = {
            '/css/security-auth.css': 'public/css/security-auth.css',
            '/js/security-auth.js': 'public/js/security-auth.js',
        };
        let asset = assets[path];
        if (path.startsWith('/build/') && !path.includes('..')) asset = 'public' + path;
        if (asset && existsSync(join(root, asset))) {
            response.setHeader('Content-Type', path.endsWith('.css') ? 'text/css' : 'text/javascript');
            response.end(readFileSync(join(root, asset)));
        } else response.writeHead(404).end();
    });
    try {
        await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
        const origin = 'http://127.0.0.1:' + server.address().port;
        for (const mode of ['password', 'code', 'recovery']) {
            pagesHtml['/' + mode] = execFileSync('php', ['tests/Js/fixtures/security-screen.php', origin, mode], { cwd: root, encoding: 'utf8' });
        }
        browser = spawn(chrome, ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-background-networking',
            '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
        let port;
        for (let attempt = 0; attempt < 100; attempt++) {
            try { port = (await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; }
            catch { await delay(100); }
        }
        assert.ok(port);
        const pages = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json();
        client = await connect(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
        await client.send('Runtime.enable');
        const evaluate = async expression => {
            const result = await client.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
            if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
            return result.result.value;
        };
        const load = async mode => {
            await client.send('Page.navigate', { url: origin + '/' + mode });
            for (let attempt = 0; attempt < 100; attempt++) {
                if (await evaluate("document.readyState === 'complete' && Boolean(document.querySelector('.security-card'))")) return;
                await delay(50);
            }
            assert.fail('Screen did not load');
        };
        for (const width of [1280, 320]) {
            await client.send('Emulation.setDeviceMetricsOverride', { width, height: 850, deviceScaleFactor: 1, mobile: false });
            for (const mode of ['password', 'code', 'recovery']) {
                await load(mode);
                assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, mode + ' fits ' + width);
                assert.equal(await evaluate("getComputedStyle(document.querySelector('.security-card')).backgroundColor"), 'rgb(255, 255, 255)');
                assert.equal(await evaluate("document.querySelectorAll('h1').length"), 1);
            }
        }
        await load('password');
        assert.equal(await evaluate("document.activeElement.id"), 'confirm-password');
        await evaluate("document.querySelector('[data-password-toggle]').click()");
        assert.equal(await evaluate("document.querySelector('#confirm-password').type"), 'text');
        await evaluate("document.querySelector('[data-password-toggle]').click()");
        assert.equal(await evaluate("document.querySelector('#confirm-password').type"), 'password');
        await evaluate("document.querySelector('[data-security-form]').dispatchEvent(new Event('submit', {cancelable:true}))");
        assert.equal(await evaluate("document.querySelector('[data-submit-label]').disabled"), true);
        await evaluate("window.dispatchEvent(new Event('pageshow'))");
        assert.equal(await evaluate("document.querySelector('[data-submit-label]').disabled"), false);
        await load('code');
        assert.equal(await evaluate("document.activeElement.id"), 'authentication-code');
        await client.send('Input.insertText', { text: '123' });
        assert.equal(await evaluate("document.querySelector('#authentication-code').checkValidity()"), false);
        await evaluate("document.querySelector('#authentication-code').select()");
        // Insert the whole value in one browser edit, as with paste/autofill.
        await client.send('Input.insertText', { text: '123456' });
        assert.equal(await evaluate("new FormData(document.querySelector('[data-security-form]')).get('code')"), '123456');
        assert.equal(await evaluate("document.querySelector('#authentication-code').checkValidity()"), true);
        if (process.env.SECURITY_SCREENSHOTS) {
            const screenshots = await mkdtemp(join(tmpdir(), 'racetech-security-preview-'));
            for (const width of [1280, 320]) {
                await client.send('Emulation.setDeviceMetricsOverride', { width, height: 850, deviceScaleFactor: 1, mobile: false });
                await load('code');
                const capture = await client.send('Page.captureScreenshot', { format: 'png' });
                const path = join(screenshots, `code-${width}.png`);
                await writeFile(path, Buffer.from(capture.data, 'base64'));
                console.log('Preview:', path);
            }
        }
        await load('recovery');
        assert.equal(await evaluate("document.activeElement.id"), 'recovery-code');
        assert.equal(await evaluate("document.querySelector('[name=code]') === null"), true);
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
