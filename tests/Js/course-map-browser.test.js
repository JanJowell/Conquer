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

test('course editor browser workflow (Google API mocked; no billable requests)', { skip: !chrome, timeout: 60000 }, async (t) => {
    const profile = await mkdtemp(join(tmpdir(), 'racetech-map-browser-'));
    let browser;
    let client;
    let html;
    const server = createServer((request, response) => {
        const path = new URL(request.url, 'http://localhost').pathname;
        if (path === '/') {
            response.setHeader('Content-Type', 'text/html');
            response.end(html);
        } else if (['/js/category-course-map.js', '/js/course-map-data.js'].includes(path)) {
            response.setHeader('Content-Type', 'text/javascript');
            response.end(readFileSync(join(root, 'public', path)));
        } else { response.writeHead(404).end(); }
    });
    try {
        await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
        const origin = `http://127.0.0.1:${server.address().port}`;
        html = execFileSync('php', ['tests/Js/fixtures/course-map-page.php', origin], { cwd: root, encoding: 'utf8' });
        browser = spawn(chrome, ['--headless=new', '--no-first-run', '--no-default-browser-check',
            '--disable-background-networking', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'],
        { windowsHide: true, stdio: 'ignore' });
        let startupError;
        browser.on('error', (error) => { startupError = error; });
        let debugPort;
        for (let attempt = 0; attempt < 100; attempt++) {
            if (startupError) throw startupError;
            try { debugPort = (await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; }
            catch { await delay(100); }
        }
        assert.ok(debugPort, 'Chrome started with a temporary test profile');
        const pages = await (await fetch(`http://127.0.0.1:${debugPort}/json/list`)).json();
        client = await connect(pages.find((page) => page.type === 'page').webSocketDebuggerUrl);
        let googleRequests = 0;
        const errors = [];
        let failLoad = false;
        client.on('Runtime.exceptionThrown', (event) => errors.push(event.exceptionDetails.text));
        client.on('Fetch.requestPaused', async ({ requestId }) => {
            googleRequests++;
            try {
                if (failLoad) await client.send('Fetch.failRequest', { requestId, errorReason: 'InternetDisconnected' });
                else await client.send('Fetch.fulfillRequest', {
                    requestId, responseCode: 200,
                    responseHeaders: [{ name: 'Content-Type', value: 'text/javascript' }],
                    body: readFileSync(join(root, 'tests/Js/fixtures/mock-google-maps.js')).toString('base64'),
                });
            } catch (error) { errors.push(error.message); }
        });
        await client.send('Runtime.enable');
        await client.send('Fetch.enable', { patterns: [{ urlPattern: '*maps.googleapis.com/*' }] });
        const evaluate = async (expression) => {
            const result = await client.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
            if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description ?? result.exceptionDetails.text);
            return result.result.value;
        };
        const until = async (expression) => {
            for (let attempt = 0; attempt < 100; attempt++) {
                if (await evaluate(expression)) return;
                await delay(50);
            }
            assert.fail(`Condition not met: ${expression}`);
        };
        const load = async () => {
            await client.send('Page.navigate', { url: origin });
            await until('Boolean(window.__racetechCourseEditor?.bound)');
            await evaluate(`
                window.confirm = () => true;
                window.editor = (index = 0) => document.querySelectorAll('[data-course-map-editor]')[index];
                window.field = (index = 0) => editor(index).querySelector('[data-course-map-value]');
                window.statusText = (index = 0) => editor(index).querySelector('[data-course-map-status]').textContent;
                window.clickTool = async (action, index = 0) => {
                    editor(index).querySelector('[data-course-action="' + action + '"]').click();
                    await new Promise(resolve => setTimeout(resolve, 0));
                };
                window.point = (lat, lng, index = 0) => mockMaps[index].emit('click', {latLng: new google.maps.LatLng({lat, lng})});
            `);
        };
        await load();

        await t.test('lazy load and route review are enforced', async () => {
            assert.equal(googleRequests, 0);
            await evaluate('clickTool("open")');
            await until('window.mockMaps?.length === 1');
            assert.equal(googleRequests, 1);
            await evaluate('point(14.46,120.96); point(14.47,120.97)');
            assert.equal(await evaluate('field().value'), '');
            assert.equal(await evaluate(`document.getElementById('event-form').dispatchEvent(new Event('submit', {bubbles:true,cancelable:true}))`), false);
            await evaluate('clickTool("apply")');
            assert.match(await evaluate('statusText()'), /Review the course/);
            await evaluate('clickTool("close-loop")');
            assert.equal(await evaluate('mockLines[0].getPath().getLength()'), 3);
            assert.match(await evaluate('editor().querySelector("[data-course-map-canvas]").textContent'), /Start \/ Finish/);
            await evaluate('editor().querySelector("[data-course-reviewed]").checked = true; clickTool("apply")');
            const course = JSON.parse(await evaluate('field().value'));
            assert.deepEqual(course.path[0], course.path.at(-1));
        });

        await t.test('checkpoint text, undo after move, and discard preserve the applied route', async () => {
            const applied = await evaluate('field().value');
            await evaluate('clickTool("open")');
            assert.equal(googleRequests, 1);
            await evaluate('clickTool("checkpoint")');
            await evaluate('point(14.465,120.965)');
            await evaluate(`
                const input = editor().querySelector('[data-course-checkpoint-label]');
                input.value = '<img src=x onerror=alert(1)>'; input.dispatchEvent(new Event('input', {bubbles:true}));
            `);
            assert.equal(await evaluate('editor().querySelectorAll("img").length'), 0);
            await evaluate('clickTool("move")');
            await evaluate('clickTool("undo")');
            await evaluate('point(14.48,120.98)');
            assert.equal(await evaluate('editor().querySelectorAll("[data-course-checkpoint-label]").length'), 0);
            await evaluate('clickTool("discard")');
            assert.equal(await evaluate('field().value'), applied);
        });

        await t.test('new category rows have isolated routes and share the loader', async () => {
            await evaluate(`
                const row = document.getElementById('category-template').content.cloneNode(true);
                row.querySelector('input').name = 'categories[1][course_map]';
                document.getElementById('event-form').append(row);
                clickTool('open', 1);
            `);
            await until('mockMaps.length === 2');
            await evaluate('point(15,121,1); point(16,122,1); editor(1).querySelector("[data-course-reviewed]").checked = true; clickTool("apply",1)');
            assert.equal(googleRequests, 1);
            assert.equal(JSON.parse(await evaluate('field(1).value')).path[0].lat, 15);
            assert.equal(JSON.parse(await evaluate('field(0).value')).path[0].lat, 14.46);
            await evaluate('clickTool("remove",1)');
            assert.equal(await evaluate('field(1).value'), '');
            assert.notEqual(await evaluate('field(0).value'), '');
            await evaluate('editor(1).remove()');
            await until('__racetechCourseEditor.controllers.size === 1');
        });

        await t.test('navigation and authentication failures preserve staged data', async () => {
            const applied = await evaluate('field().value');
            assert.equal(await evaluate(`(() => {
                const form = document.getElementById('event-form');
                form.addEventListener('submit', event => event.preventDefault(), {once:true});
                return form.dispatchEvent(new Event('submit', {bubbles:true,cancelable:true}));
            })()`), false, 'a confirmation may cancel a clean route submission');
            assert.equal(await evaluate('__racetechCourseEditor.submitting'), false, 'a cancelled attempt must not suppress unsaved-route warnings');
            assert.equal(await evaluate(`window.confirm = () => false; document.dispatchEvent(new CustomEvent('livewire:navigate', {cancelable:true}))`), false);
            await evaluate('window.gm_authFailure()');
            assert.match(await evaluate('statusText()'), /authentication failed/);
            assert.equal(await evaluate('field().value'), applied);
        });

        await t.test('download failure can be retried without changing the submitted field', async () => {
            failLoad = true;
            await load();
            await evaluate('clickTool("open")');
            await until('statusText().includes("could not be downloaded")');
            assert.equal(await evaluate('field().value'), '');
            failLoad = false;
            await evaluate('clickTool("open")');
            await until('window.mockMaps?.length === 1');
        });
        assert.deepEqual(errors, []);
    } finally {
        if (client && browser?.exitCode === null) await client.send('Browser.close').catch(() => {});
        client?.close();
        if (browser && browser.exitCode === null) {
            const exited = new Promise((resolve) => browser.once('exit', resolve));
            browser.kill();
            await Promise.race([exited, delay(5000)]);
        }
        server.closeAllConnections();
        await new Promise((resolve) => server.close(resolve));
        // Only the fresh mkdtemp browser profile belongs to this test.
        assert.equal(resolve(profile).startsWith(resolve(tmpdir()) + (process.platform === 'win32' ? '\\' : '/')), true);
        await rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
});
