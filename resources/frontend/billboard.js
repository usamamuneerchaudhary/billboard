/*!
 * Billboard for Statamic — front-end runtime.
 *
 * - Chooses ads in the browser for placements set to "In the browser", so
 *   rotation and schedules keep working on statically cached pages.
 * - Hides ads that target another device and shows the fallback instead.
 * - Counts an impression once at least half of an ad has been on screen for
 *   one second, and sends impressions to your own site in small batches.
 * - Stores a per-ad daily counter in localStorage only for ads that have a
 *   "views per visitor per day" limit.
 *
 * Nothing is sent anywhere except your own /!/billboard/impressions route.
 */
(function (window, document) {
    'use strict';

    if (window.Billboard && window.Billboard.version) {
        window.Billboard.scan();
        return;
    }

    var script = document.currentScript;

    function attr(el, name) {
        return el ? el.getAttribute(name) : null;
    }

    var config = {
        endpoint: attr(script, 'data-billboard-endpoint'),
        tracking: attr(script, 'data-billboard-tracking') === '1',
        respectPrivacy: attr(script, 'data-billboard-privacy') !== '0',
        breakpoint: parseInt(attr(script, 'data-billboard-breakpoint'), 10) || 768,
    };

    var VISIBLE_RATIO = 0.5;
    var VISIBLE_MS = 1000;
    var BATCH_SIZE = 25;
    var FLUSH_DELAY_MS = 2000;
    var STORAGE_PREFIX = 'billboard:seen:';

    function hasPrivacySignal() {
        var nav = window.navigator || {};

        return nav.globalPrivacyControl === true || nav.doNotTrack === '1' || window.doNotTrack === '1';
    }

    function canSend() {
        return !!(config.tracking && config.endpoint && !(config.respectPrivacy && hasPrivacySignal()));
    }

    /* ------------------------------------------------------------------ */
    /* Targeting                                                           */
    /* ------------------------------------------------------------------ */

    function currentDevice() {
        if (typeof window.matchMedia !== 'function') {
            return 'desktop';
        }

        return window.matchMedia('(max-width: ' + (config.breakpoint - 0.02) + 'px)').matches ? 'mobile' : 'desktop';
    }

    function matchesDevice(el) {
        var devices = attr(el, 'data-billboard-devices');

        return !devices || devices.split(/\s+/).indexOf(currentDevice()) !== -1;
    }

    function inSchedule(el, nowSeconds) {
        var start = parseInt(attr(el, 'data-billboard-start'), 10);
        var end = parseInt(attr(el, 'data-billboard-end'), 10);

        return !(start && start > nowSeconds) && !(end && end <= nowSeconds);
    }

    /* ------------------------------------------------------------------ */
    /* Frequency caps (only for ads that set one)                          */
    /* ------------------------------------------------------------------ */

    function storage() {
        try {
            var s = window.localStorage;
            s.setItem('billboard:test', '1');
            s.removeItem('billboard:test');
            return s;
        } catch (e) {
            return null;
        }
    }

    function today() {
        var d = new Date();
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };

        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    function seenToday(adId) {
        var s = storage();

        if (!s) return 0;

        try {
            var record = JSON.parse(s.getItem(STORAGE_PREFIX + adId) || 'null');
            return record && record.d === today() ? record.n || 0 : 0;
        } catch (e) {
            return 0;
        }
    }

    function underCap(el) {
        var cap = parseInt(attr(el, 'data-billboard-cap'), 10);

        return !cap || seenToday(attr(el, 'data-billboard-ad')) < cap;
    }

    function countView(el) {
        var cap = parseInt(attr(el, 'data-billboard-cap'), 10);
        var s = cap ? storage() : null;

        if (!s) return;

        var adId = attr(el, 'data-billboard-ad');

        try {
            s.setItem(STORAGE_PREFIX + adId, JSON.stringify({ d: today(), n: seenToday(adId) + 1 }));
        } catch (e) {
            // Storage full or blocked: the cap simply won't apply.
        }
    }

    /* ------------------------------------------------------------------ */
    /* Rotation                                                            */
    /* ------------------------------------------------------------------ */

    function weightOf(el) {
        return Math.max(1, parseInt(attr(el, 'data-billboard-weight'), 10) || 1);
    }

    function weightedSample(items, count, random) {
        var pool = items.slice();
        var chosen = [];

        random = random || Math.random;

        while (chosen.length < count && pool.length) {
            var total = 0;
            var i;

            for (i = 0; i < pool.length; i++) total += weightOf(pool[i].ad);

            var target = random() * total;

            for (i = 0; i < pool.length; i++) {
                target -= weightOf(pool[i].ad);
                if (target < 0) break;
            }

            i = Math.min(i, pool.length - 1);
            chosen.push(pool[i]);
            pool.splice(i, 1);
        }

        return chosen;
    }

    /* ------------------------------------------------------------------ */
    /* Rendering                                                           */
    /* ------------------------------------------------------------------ */

    // Scripts inserted from a <template> are recreated so ad network tags run.
    function materialize(template) {
        var fragment = document.importNode(template.content, true);
        var scripts = fragment.querySelectorAll('script');

        for (var i = 0; i < scripts.length; i++) {
            var original = scripts[i];
            var replacement = document.createElement('script');

            for (var j = 0; j < original.attributes.length; j++) {
                replacement.setAttribute(original.attributes[j].name, original.attributes[j].value);
            }

            replacement.text = original.text;
            original.parentNode.replaceChild(replacement, original);
        }

        return fragment;
    }

    function showFallback(placement, slot) {
        var fallback = placement.querySelector('template[data-billboard-fallback]');

        if (fallback && slot) {
            var wrapper = document.createElement('div');
            wrapper.className = 'billboard__fallback';
            wrapper.appendChild(materialize(fallback));
            slot.appendChild(wrapper);
            return true;
        }

        placement.hidden = true;
        return false;
    }

    function renderClient(placement) {
        var slot = placement.querySelector('[data-billboard-slot]');
        var templates = placement.querySelectorAll('template[data-billboard-candidate]');
        var now = Math.floor(Date.now() / 1000);
        var max = Math.max(1, parseInt(attr(placement, 'data-billboard-max'), 10) || 1);
        var candidates = [];

        for (var i = 0; i < templates.length; i++) {
            var ad = templates[i].content.firstElementChild;

            if (ad && inSchedule(ad, now) && matchesDevice(ad) && underCap(ad)) {
                candidates.push({ template: templates[i], ad: ad });
            }
        }

        var chosen = attr(placement, 'data-billboard-rotation') === 'ordered'
            ? candidates.slice(0, max)
            : weightedSample(candidates, max);

        if (!slot) return;

        if (!chosen.length) {
            showFallback(placement, slot);
            return;
        }

        for (var k = 0; k < chosen.length; k++) {
            slot.appendChild(materialize(chosen[k].template));
        }
    }

    function renderServer(placement) {
        var ads = placement.querySelectorAll('[data-billboard-ad]');
        var visible = 0;

        for (var i = 0; i < ads.length; i++) {
            if (matchesDevice(ads[i])) {
                visible++;
            } else {
                ads[i].hidden = true;
            }
        }

        if (ads.length && !visible) {
            showFallback(placement, placement.querySelector('[data-billboard-slot]'));
        }
    }

    /* ------------------------------------------------------------------ */
    /* Impressions                                                         */
    /* ------------------------------------------------------------------ */

    var queue = [];
    var flushTimer = null;
    var observer = null;
    var timers = typeof WeakMap === 'function' ? new WeakMap() : null;

    function send(events) {
        var body = JSON.stringify({ e: events });

        try {
            if (window.navigator && typeof window.navigator.sendBeacon === 'function') {
                var blob = typeof Blob === 'function' ? new Blob([body], { type: 'text/plain' }) : body;

                if (window.navigator.sendBeacon(config.endpoint, blob)) return;
            }

            if (typeof window.fetch === 'function') {
                window.fetch(config.endpoint, {
                    method: 'POST',
                    body: body,
                    keepalive: true,
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'text/plain' },
                });
            }
        } catch (e) {
            // Counting is best-effort and must never break the page.
        }
    }

    function flush() {
        if (flushTimer) {
            clearTimeout(flushTimer);
            flushTimer = null;
        }

        while (queue.length) {
            send(queue.splice(0, BATCH_SIZE));
        }
    }

    function recordImpression(el) {
        if (el.__billboardCounted) return;
        el.__billboardCounted = true;

        countView(el);

        if (!canSend()) return;

        var placement = el.closest ? el.closest('[data-billboard-placement]') : null;
        var handle = attr(placement, 'data-billboard-placement');
        var adId = attr(el, 'data-billboard-ad');

        if (!handle || !adId) return;

        queue.push([adId, handle]);

        if (queue.length >= BATCH_SIZE) {
            flush();
        } else if (!flushTimer) {
            flushTimer = setTimeout(flush, FLUSH_DELAY_MS);
        }
    }

    function onIntersect(entries) {
        for (var i = 0; i < entries.length; i++) {
            var entry = entries[i];
            var el = entry.target;

            if (entry.isIntersecting && entry.intersectionRatio >= VISIBLE_RATIO) {
                if (!timers.has(el)) {
                    timers.set(el, setTimeout(function (target) {
                        return function () {
                            timers.delete(target);
                            observer.unobserve(target);
                            recordImpression(target);
                        };
                    }(el), VISIBLE_MS));
                }
            } else if (timers.has(el)) {
                clearTimeout(timers.get(el));
                timers.delete(el);
            }
        }
    }

    function watch(el) {
        if (el.hidden || el.__billboardWatched) return;
        el.__billboardWatched = true;

        var needsCounting = canSend() || attr(el, 'data-billboard-cap');

        if (!needsCounting) return;

        if (typeof window.IntersectionObserver !== 'function' || !timers) {
            recordImpression(el);
            return;
        }

        observer = observer || new window.IntersectionObserver(onIntersect, { threshold: [0, VISIBLE_RATIO] });
        observer.observe(el);
    }

    /* ------------------------------------------------------------------ */
    /* Boot                                                                */
    /* ------------------------------------------------------------------ */

    function activate(placement) {
        if (placement.__billboardActive) return;
        placement.__billboardActive = true;

        if (attr(placement, 'data-billboard-mode') === 'client') {
            renderClient(placement);
        } else {
            renderServer(placement);
        }

        var ads = placement.querySelectorAll('[data-billboard-ad]');

        for (var i = 0; i < ads.length; i++) {
            watch(ads[i]);
        }
    }

    function scan(root) {
        var placements = (root || document).querySelectorAll('[data-billboard-placement]');

        for (var i = 0; i < placements.length; i++) {
            activate(placements[i]);
        }
    }

    window.Billboard = {
        version: '1.0.0',
        scan: scan,
        flush: flush,
    };

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') flush();
    });

    window.addEventListener('pagehide', flush);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { scan(); });
    } else {
        scan();
    }
})(window, document);
