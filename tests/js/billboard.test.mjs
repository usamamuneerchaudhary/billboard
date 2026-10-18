import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const source = readFileSync(new URL('../../resources/frontend/billboard.js', import.meta.url), 'utf8');

const now = () => Math.floor(Date.now() / 1000);

function ad(id, { weight = 10, start, end, devices, cap, html } = {}) {
    const attrs = [
        `data-billboard-ad="${id}"`,
        `data-billboard-weight="${weight}"`,
        start ? `data-billboard-start="${start}"` : '',
        end ? `data-billboard-end="${end}"` : '',
        devices ? `data-billboard-devices="${devices}"` : '',
        cap ? `data-billboard-cap="${cap}"` : '',
    ].join(' ');

    return `<div class="billboard-ad" ${attrs}>${html ?? `<a href="/!/billboard/click/${id}/sidebar">${id}</a>`}</div>`;
}

function clientPlacement(ads, { max = 1, rotation = 'weighted', fallback } = {}) {
    return `<aside data-billboard-placement="sidebar" data-billboard-mode="client" data-billboard-rotation="${rotation}" data-billboard-max="${max}">
        ${ads.map((markup) => `<template data-billboard-candidate>${markup}</template>`).join('')}
        ${fallback ? `<template data-billboard-fallback>${fallback}</template>` : ''}
        <div data-billboard-slot></div>
    </aside>`;
}

async function boot(body, { random = 0, mobile = false, tracking = true, gpc = false, storage = {}, observer = 'manual' } = {}) {
    const dom = new JSDOM(`<!doctype html><html><body>${body}</body></html>`, {
        runScripts: 'dangerously',
        url: 'https://example.test/blog/post',
        pretendToBeVisual: true,
    });

    const { window } = dom;
    const sent = [];
    const observed = [];

    window.Math.random = () => random;
    window.matchMedia = (query) => ({ matches: mobile && query.includes('max-width') });
    window.navigator.sendBeacon = (url, body) => {
        sent.push({ url, body });
        return true;
    };
    window.Blob = class {
        constructor(parts) {
            this.text = parts.join('');
        }
    };

    Object.defineProperty(window.navigator, 'globalPrivacyControl', { value: gpc, configurable: true });

    for (const [key, value] of Object.entries(storage)) {
        window.localStorage.setItem(key, value);
    }

    if (observer === 'manual') {
        window.IntersectionObserver = class {
            constructor(callback) {
                this.callback = callback;
            }
            observe(el) {
                observed.push({ el, callback: this.callback, observer: this });
            }
            unobserve() {}
        };
    }

    const script = window.document.createElement('script');
    script.setAttribute('data-billboard-endpoint', '/!/billboard/impressions');
    script.setAttribute('data-billboard-tracking', tracking ? '1' : '0');
    script.setAttribute('data-billboard-privacy', '1');
    script.setAttribute('data-billboard-breakpoint', '768');
    script.textContent = source;
    window.document.body.appendChild(script);

    // jsdom is still "loading" here, so the script waits for DOMContentLoaded,
    // just as it would if it were included without defer in a real page.
    if (window.document.readyState === 'loading') {
        await new Promise((resolve) => window.document.addEventListener('DOMContentLoaded', resolve));
    }

    return { window, document: window.document, sent, observed };
}

function slotIds(document) {
    return [...document.querySelectorAll('[data-billboard-slot] [data-billboard-ad]')].map((el) => el.getAttribute('data-billboard-ad'));
}

test('picks one candidate by weight in the browser', async () => {
    const markup = clientPlacement([ad('light', { weight: 1 }), ad('heavy', { weight: 99 })]);

    assert.deepEqual(slotIds((await boot(markup, { random: 0 })).document), ['light']);
    assert.deepEqual(slotIds((await boot(markup, { random: 0.5 })).document), ['heavy']);
});

test('shows several ads without repeating one', async () => {
    const markup = clientPlacement([ad('a'), ad('b'), ad('c')], { max: 3 });
    const ids = slotIds((await boot(markup, { random: 0.99 })).document);

    assert.equal(ids.length, 3);
    assert.equal(new Set(ids).size, 3);
});

test('ordered rotation keeps the server order', async () => {
    const markup = clientPlacement([ad('first', { weight: 50 }), ad('second', { weight: 20 }), ad('third', { weight: 1 })], {
        max: 2,
        rotation: 'ordered',
    });

    assert.deepEqual(slotIds((await boot(markup, { random: 0.99 })).document), ['first', 'second']);
});

test('skips ads outside their schedule and falls back', async () => {
    const markup = clientPlacement(
        [ad('expired', { end: now() - 60 }), ad('upcoming', { start: now() + 3600 })],
        { fallback: '<p class="house">Advertise with us</p>' },
    );
    const { document } = await boot(markup);

    assert.deepEqual(slotIds(document), []);
    assert.ok(document.querySelector('[data-billboard-slot] .house'));
});

test('hides the placement when nothing is eligible and there is no fallback', async () => {
    const { document } = await boot(clientPlacement([ad('expired', { end: now() - 60 })]));

    assert.equal(document.querySelector('[data-billboard-placement]').hidden, true);
});

test('respects device targeting in the browser', async () => {
    const markup = clientPlacement([ad('desktop-only', { devices: 'desktop' }), ad('mobile-only', { devices: 'mobile' })]);

    assert.deepEqual(slotIds((await boot(markup, { mobile: true })).document), ['mobile-only']);
    assert.deepEqual(slotIds((await boot(markup, { mobile: false })).document), ['desktop-only']);
});

test('excludes an ad once the visitor reaches its daily cap', async () => {
    const d = new Date();
    const today = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const markup = clientPlacement([ad('capped', { cap: 2, weight: 100 }), ad('other', { weight: 1 })]);

    const { document } = await boot(markup, {
        random: 0,
        storage: { 'billboard:seen:capped': JSON.stringify({ d: today, n: 2 }) },
    });

    assert.deepEqual(slotIds(document), ['other']);
});

test('runs scripts inside HTML ads', async () => {
    const markup = clientPlacement([ad('network', { html: '<script>window.__adRan = true;</script>' })]);
    const { window } = await boot(markup);

    assert.equal(window.__adRan, true);
});

test('server-rendered ads for another device are hidden and the fallback shown', async () => {
    const markup = `<aside data-billboard-placement="sidebar" data-billboard-mode="server">
        <div data-billboard-slot>${ad('desktop-only', { devices: 'desktop' })}</div>
        <template data-billboard-fallback><p class="house">House ad</p></template>
    </aside>`;
    const { document } = await boot(markup, { mobile: true });

    assert.equal(document.querySelector('[data-billboard-ad]').hidden, true);
    assert.ok(document.querySelector('.house'));
});

async function makeVisible(env) {
    for (const { el, callback, observer } of env.observed) {
        callback([{ target: el, isIntersecting: true, intersectionRatio: 1 }], observer);
    }

    await new Promise((resolve) => setTimeout(resolve, 1100));
    env.window.Billboard.flush();
}

test('counts an impression after an ad has been visible for a second', async () => {
    const env = await boot(clientPlacement([ad('visible')]));

    assert.equal(env.sent.length, 0, 'nothing is sent before the ad is seen');

    await makeVisible(env);

    assert.equal(env.sent.length, 1);
    assert.equal(env.sent[0].url, '/!/billboard/impressions');
    assert.deepEqual(JSON.parse(env.sent[0].body.text), { e: [['visible', 'sidebar']] });
});

test('does not count an ad that scrolls away before a second passes', async () => {
    const env = await boot(clientPlacement([ad('brief')]));
    const { el, callback, observer } = env.observed[0];

    callback([{ target: el, isIntersecting: true, intersectionRatio: 1 }], observer);
    await new Promise((resolve) => setTimeout(resolve, 300));
    callback([{ target: el, isIntersecting: false, intersectionRatio: 0 }], observer);
    await new Promise((resolve) => setTimeout(resolve, 1000));
    env.window.Billboard.flush();

    assert.equal(env.sent.length, 0);
});

test('sends nothing when the browser has Global Privacy Control on', async () => {
    const env = await boot(clientPlacement([ad('private')]), { gpc: true });

    await makeVisible(env);

    assert.equal(env.sent.length, 0);
});

test('sends nothing when counting is switched off', async () => {
    const env = await boot(clientPlacement([ad('quiet')]), { tracking: false });

    await makeVisible(env);

    assert.equal(env.sent.length, 0);
});

test('still counts views locally for capped ads when counting is off', async () => {
    const env = await boot(clientPlacement([ad('capped', { cap: 3 })]), { tracking: false });

    await makeVisible(env);

    const record = JSON.parse(env.window.localStorage.getItem('billboard:seen:capped'));
    assert.equal(record.n, 1);
    assert.equal(env.sent.length, 0);
});

test('loading the script twice does not render placements twice', async () => {
    const env = await boot(clientPlacement([ad('once')]));
    const again = env.document.createElement('script');
    again.textContent = source;
    env.document.body.appendChild(again);

    assert.equal(slotIds(env.document).length, 1);
});
