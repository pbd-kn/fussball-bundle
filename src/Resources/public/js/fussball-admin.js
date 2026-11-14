(function () {
    'use strict';
    if (window.fussballAdminInitialized) return;
    window.fussballAdminInitialized = true;

    function result(root, message) {
        root.querySelector('.fussball-admin-result').textContent = message;
    }

    async function request(url, options) {
        const target = new URL(url, window.location.href);
        if (target.origin !== window.location.origin || target.search) {
            throw new Error('Ungültige Verwaltungsadresse.');
        }
        const response = await fetch(target.href, {
            credentials: 'same-origin',
            cache: 'no-store',
            redirect: 'error',
            ...options,
            headers: { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) }
        });
        let payload;
        try {
            payload = await response.json();
        } catch (_) {
            throw new Error('Die Anfrage wurde abgelehnt. Bitte Anmeldung prüfen und die Seite neu laden.');
        }
        if (!response.ok || payload.error) {
            throw new Error(payload.error || 'Die Anfrage wurde abgelehnt.');
        }
        return payload;
    }

    async function write(root, url, body) {
        body.set('REQUEST_TOKEN', root.dataset.token);
        return request(url, { method: 'POST', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' } });
    }

    document.addEventListener('click', async function (event) {
        const button = event.target.closest('button');
        const root = button && button.closest('[data-fussball-admin]');
        if (!root) return;
        if (button.hasAttribute('data-fussball-cancel')) {
            root.querySelector('.fussball-admin-form').replaceChildren();
            return;
        }
        const readUrl = button.dataset.fussballRead;
        const writeUrl = button.dataset.fussballWrite;
        if (!readUrl && !writeUrl) return;
        event.preventDefault();
        if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) return;
        button.disabled = true;
        try {
            if (readUrl) {
                const payload = await request(readUrl, { method: 'GET' });
                // Only authenticated, server-rendered forms with escaped data are inserted.
                root.querySelector('.fussball-admin-form').innerHTML = payload.data;
                result(root, '');
            } else {
                await write(root, writeUrl, new URLSearchParams({ aktion: button.dataset.action, ID: button.dataset.id }));
                window.location.reload();
            }
        } catch (error) {
            result(root, error.message);
        } finally {
            button.disabled = false;
        }
    });

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest('form[data-fussball-form]');
        const root = form && form.closest('[data-fussball-admin]');
        if (!root) return;
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        if (button.disabled) return;
        button.disabled = true;
        try {
            const body = new URLSearchParams(new FormData(form));
            const payload = await write(root, form.action, body);
            if (form.dataset.keepOpen) {
                result(root, payload.data);
            } else {
                window.location.reload();
            }
            const password = form.querySelector('input[type="password"]');
            if (password) password.value = '';
        } catch (error) {
            result(root, error.message);
        } finally {
            button.disabled = false;
        }
    });
})();
