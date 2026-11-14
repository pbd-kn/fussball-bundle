const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

(async () => {
    const listeners = {};
    const requests = [];
    let reloads = 0;
    let confirmation = true;
    let response = { ok: true, payload: { data: 'Gespeichert.', error: '' } };
    const status = { textContent: '', innerHTML: 'unchanged' };
    const panel = { innerHTML: '', replaceChildren() { this.innerHTML = ''; } };
    const root = {
        dataset: { token: 'session-bound-csrf-token' },
        querySelector(selector) { return selector.includes('result') ? status : panel; }
    };
    const context = vm.createContext({
        URL, URLSearchParams,
        FormData: class { constructor(form) { return new Map(form.entries); } },
        window: {
            location: { href: 'https://hoyzer.example/', origin: 'https://hoyzer.example', reload() { reloads++; } },
            confirm() { return confirmation; }
        },
        document: { addEventListener(type, handler) { listeners[type] = handler; } },
        async fetch(url, options) {
            requests.push({ url, options });
            return { ok: response.ok, async json() { return response.payload; } };
        }
    });
    const script = fs.readFileSync(path.join(__dirname, '../src/Resources/public/js/fussball-admin.js'), 'utf8');
    vm.runInContext(script, context);

    const submitButton = { disabled: false };
    const password = { value: 'private-test-password' };
    const form = {
        action: '/contao/fussball/bearbeiteteilnehmer', dataset: {},
        entries: [['aktion', 'u'], ['ID', '7'], ['pwd', password.value]],
        closest() { return root; },
        querySelector(selector) { return selector.includes('password') ? password : submitButton; }
    };
    await listeners.submit({ target: { closest() { return form; } }, preventDefault() {} });
    assert.equal(requests[0].options.method, 'POST');
    assert.equal(requests[0].options.credentials, 'same-origin');
    assert.equal(requests[0].options.cache, 'no-store');
    assert.equal(requests[0].options.body.get('REQUEST_TOKEN'), root.dataset.token);
    assert.equal(requests[0].options.body.get('pwd'), 'private-test-password');
    assert.equal(new URL(requests[0].url).search, '');
    assert.ok(!requests[0].url.includes('private-test-password'));
    assert.equal(password.value, '');
    assert.equal(reloads, 1);

    const button = {
        disabled: false,
        dataset: { fussballWrite: '/contao/fussball/bearbeiteort', action: 'd', id: '4', confirm: 'Löschen?' },
        closest() { return root; },
        hasAttribute() { return false; }
    };
    const click = { target: { closest() { return button; } }, preventDefault() {} };
    confirmation = false;
    await listeners.click(click);
    assert.equal(requests.length, 1);
    confirmation = true;
    response = { ok: false, payload: { error: '<img src=x onerror=alert(1)>' } };
    await listeners.click(click);
    assert.equal(requests[1].options.method, 'POST');
    assert.equal(requests[1].options.body.get('ID'), '4');
    assert.equal(status.textContent, response.payload.error);
    assert.equal(status.innerHTML, 'unchanged');
    assert.equal(button.disabled, false);
    assert.equal(reloads, 1);

    button.dataset = { fussballRead: 'https://attacker.example/contao/fussball/anzeigeort/u/1' };
    await listeners.click(click);
    assert.equal(requests.length, 2);
    button.dataset = { fussballRead: '/contao/fussball/anzeigeort/u/1' };
    response = { ok: true, payload: { data: '<form>escaped server form</form>' } };
    await listeners.click(click);
    assert.equal(requests[2].options.method, 'GET');
    assert.equal(new URL(requests[2].url).search, '');
    assert.equal(panel.innerHTML, response.payload.data);
    const oldClick = listeners.click;
    vm.runInContext(script, context);
    assert.equal(listeners.click, oldClick);
    console.log('OK: admin client uses POST/CSRF, keeps secrets out of URLs, renders errors as text, rejects foreign origins and initializes once');
})().catch(error => { console.error(error); process.exitCode = 1; });
