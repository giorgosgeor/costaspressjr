/*
 * Checkout page (/checkout).
 *
 * One form, three sections: who's collecting, how (the store, free, or an
 * ACS point, with a fee), and payment through Stripe's Payment Element. The
 * order summary sits beside it (above it, collapsed, on phones).
 *
 * Pressing Pay:
 *   1. checks the details and pickup point here, pointing at what's missing;
 *   2. elements.submit() — Stripe checks its own fields, and wallets (Apple
 *      Pay) get the click they need to open;
 *   3. POST /api/create-payment-intent — the server re-validates, prices the
 *      cart and records the checkout against the new PaymentIntent;
 *   4. stripe.confirmPayment() — on success Stripe sends the browser to
 *      /checkout/complete, which places the order and shows it. Every method
 *      ends there, cards included. The webhook is the backstop for anyone who
 *      never arrives.
 */
(function () {
    'use strict';

    var cfgEl = document.getElementById('checkoutConfig');
    var form = document.getElementById('checkoutForm');
    if (!cfgEl || !form) return;

    var cfg = JSON.parse(cfgEl.textContent);
    var $ = function (id) { return document.getElementById(id); };
    var t = function (k, p) { return (window.I18N && window.I18N.t) ? window.I18N.t(k, p) : k; };
    var money = function (cents) { return '€' + (cents / 100).toFixed(2); };
    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var state = {
        subtotal: cfg.subtotalCents,
        method: 'store',
        point: null,
        points: null,
        userPos: null,
        locAsked: false,
        map: null,
        markers: {},
        userMarker: null,
        mapFailed: false,
        leaflet: null,
        stripe: null,
        elements: null,
        paymentEl: null,
        busy: false
    };

    function feeCents() { return state.method === 'acs_point' ? (cfg.acsFeeCents || 0) : 0; }
    function totalCents() { return state.subtotal + feeCents(); }

    function scrollToEl(el) {
        if (!el) return;
        el.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
    }

    // ------------------------------------------------------------- totals

    function refreshTotals() {
        var fee = feeCents();
        document.querySelectorAll('[data-co-subtotal]').forEach(function (el) { el.textContent = money(state.subtotal); });
        document.querySelectorAll('[data-co-fee]').forEach(function (el) {
            el.textContent = fee ? money(fee) : t('checkout.pickup.free');
            el.classList.toggle('is-free', !fee);
        });
        document.querySelectorAll('[data-co-total]').forEach(function (el) { el.textContent = money(totalCents()); });
        if (state.elements && totalCents() > 0) state.elements.update({ amount: totalCents() });
    }

    // ------------------------------------------------------------- details

    var inputs = { name: $('coName'), phone: $('coPhone'), email: $('coEmail') };
    var touched = {};

    function contact() {
        return {
            name: (inputs.name.value || '').trim(),
            phone: (inputs.phone.value || '').trim(),
            email: inputs.email ? (inputs.email.value || '').trim() : ''
        };
    }

    // Mirrors Pickup::validateChoice(); the server checks everything again.
    function problem(key) {
        var c = contact();
        if (key === 'name') return c.name ? '' : t('checkout.pickup.errors.name');
        if (key === 'phone') {
            var digits = c.phone.replace(/\D/g, '');
            return (/^[+\d][\d\s()\-]*$/.test(c.phone) && digits.length >= 8 && digits.length <= 15) ? '' : t('checkout.pickup.errors.phone');
        }
        if (key === 'email') {
            if (!inputs.email) return '';
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(c.email) ? '' : t('checkout.pickup.errors.email');
        }
        return '';
    }

    function showFieldError(key, msg) {
        var input = inputs[key];
        if (!input) return;
        var err = $(input.id + 'Err');
        input.setAttribute('aria-invalid', msg ? 'true' : 'false');
        if (err) {
            err.textContent = msg;
            err.hidden = !msg;
        }
    }

    function detailsComplete() {
        return !problem('name') && !problem('phone') && !problem('email');
    }

    function markSections() {
        $('coSectionDetails').classList.toggle('is-complete', detailsComplete());
        $('coSectionCollection').classList.toggle('is-complete', state.method === 'store' || !!state.point);
    }

    /** Show every problem; return the first bad input, or null. */
    function validateDetails() {
        var first = null;
        ['name', 'phone', 'email'].forEach(function (key) {
            if (!inputs[key]) return;
            var msg = problem(key);
            touched[key] = true;
            showFieldError(key, msg);
            if (msg && !first) first = inputs[key];
        });
        return first;
    }

    // Validate when the customer leaves a field, not while they type; once a
    // field has shown an error, re-check as they type so it clears the moment
    // it's fixed.
    Object.keys(inputs).forEach(function (key) {
        var input = inputs[key];
        if (!input) return;
        input.addEventListener('blur', function () {
            if (!input.value.trim() && !touched[key]) return;
            touched[key] = true;
            showFieldError(key, problem(key));
            markSections();
            syncBillingDefaults();
        });
        input.addEventListener('input', function () {
            if (touched[key]) showFieldError(key, problem(key));
            markSections();
            remember();
        });
    });

    // Kept for this tab only, so going back to the cart and returning doesn't
    // mean typing it all again. Storage may be unavailable; that's fine.
    var STORE_KEY = 'checkout.contact';
    function remember() {
        try { sessionStorage.setItem(STORE_KEY, JSON.stringify(contact())); } catch (e) { /* ignore */ }
    }
    function restore() {
        var saved = null;
        try { saved = JSON.parse(sessionStorage.getItem(STORE_KEY) || 'null'); } catch (e) { saved = null; }
        if (!saved) return;
        ['name', 'phone', 'email'].forEach(function (key) {
            if (inputs[key] && !inputs[key].value && saved[key]) inputs[key].value = saved[key];
        });
    }

    // The Payment Element uses these for Link and for the receipt.
    var lastSynced = '';
    function syncBillingDefaults() {
        if (!state.paymentEl) return;
        var c = contact();
        var billing = { name: c.name, email: c.email || cfg.accountEmail || '', phone: c.phone };
        var key = JSON.stringify(billing);
        if (key === lastSynced) return;
        lastSynced = key;
        state.paymentEl.update({ defaultValues: { billingDetails: billing } });
    }

    // ------------------------------------------------------------- collection

    function showPointError(msg) {
        var el = $('coPointErr');
        if (!el) return;
        el.textContent = msg;
        el.hidden = !msg;
    }

    function setMethod(method) {
        state.method = method;
        var loc = $('acsLocator');
        if (loc) loc.hidden = method !== 'acs_point';
        showPointError('');
        refreshTotals();
        markSections();
        if (method === 'acs_point') openLocator();
    }

    function setStatus(msg) {
        var el = $('acsStatus');
        if (el) el.textContent = msg || '';
    }

    function openLocator() {
        var ready = state.points
            ? Promise.resolve()
            : fetch('/api/pickup-points', { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) { state.points = d.points || []; })
                .catch(function () { state.points = []; });

        setStatus(t('checkout.pickup.loading'));
        ready.then(function () {
            if (!state.points.length) {
                setStatus(t('checkout.pickup.none'));
                return;
            }
            return loadLeaflet().then(initMap, function () { state.mapFailed = true; }).then(function () {
                if (state.map) setTimeout(function () { state.map.invalidateSize(); }, 50);
                // Ask for the location once, when the customer first picks ACS.
                if (!state.locAsked) {
                    state.locAsked = true;
                    locate();
                } else {
                    render();
                }
            });
        });
    }

    function loadLeaflet() {
        if (window.L) return Promise.resolve();
        if (state.leaflet) return state.leaflet;
        state.leaflet = new Promise(function (resolve, reject) {
            var css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = cfg.leafletCss;
            document.head.appendChild(css);
            var js = document.createElement('script');
            js.src = cfg.leafletJs;
            js.onload = resolve;
            js.onerror = reject;
            document.head.appendChild(js);
        });
        return state.leaflet;
    }

    var STYLE = {
        point:    { radius: 7, color: '#16130F', weight: 2, fillColor: '#FBF9F3', fillOpacity: 1 },
        near:     { radius: 8, color: '#16130F', weight: 2, fillColor: '#16130F', fillOpacity: 0.85 },
        selected: { radius: 10, color: '#C83017', weight: 3, fillColor: '#C83017', fillOpacity: 1 }
    };

    function initMap() {
        if (state.map || !window.L) return;
        state.map = L.map('acsMap', { scrollWheelZoom: false }).setView([35.05, 33.2], 8);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
        }).addTo(state.map);

        state.points.forEach(function (p) {
            var m = L.circleMarker([p.lat, p.lng], STYLE.point)
                .bindTooltip(esc(p.name))
                .on('click', function () { select(p.id); })
                .addTo(state.map);
            state.markers[p.id] = m;
        });
        fit(state.points);
    }

    function locate() {
        if (!navigator.geolocation) {
            state.userPos = null;
            render();
            return;
        }
        setStatus(t('checkout.pickup.locating'));
        navigator.geolocation.getCurrentPosition(function (pos) {
            state.userPos = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            if (state.map) {
                if (state.userMarker) state.userMarker.setLatLng([state.userPos.lat, state.userPos.lng]);
                else state.userMarker = L.circleMarker([state.userPos.lat, state.userPos.lng],
                    { radius: 7, color: '#1B3A6B', weight: 3, fillColor: '#FFFFFF', fillOpacity: 1 })
                    .bindTooltip(t('checkout.pickup.you_are_here')).addTo(state.map);
            }
            render();
        }, function () {
            state.userPos = null;
            render(t('checkout.pickup.no_location'));
        }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 600000 });
    }

    function km(a, b) {
        var R = 6371, rad = Math.PI / 180;
        var dLat = (b.lat - a.lat) * rad, dLng = (b.lng - a.lng) * rad;
        var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(a.lat * rad) * Math.cos(b.lat * rad) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return 2 * R * Math.asin(Math.sqrt(h));
    }

    // Accent- and case-insensitive, so "λευκωσια" finds "Λευκωσία".
    function norm(s) {
        return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    }

    function render(notice) {
        if (!state.points || !state.points.length) return;
        var q = norm(($('acsSearch') || {}).value);
        var rows = state.points.map(function (p) {
            return { p: p, d: state.userPos ? km(state.userPos, p) : null };
        });
        if (q) {
            rows = rows.filter(function (r) {
                return norm([r.p.name, r.p.address, r.p.city, r.p.zipcode].join(' ')).indexOf(q) !== -1;
            });
        }
        if (state.userPos) rows.sort(function (a, b) { return a.d - b.d; });

        var nearest = !!state.userPos && !q;
        var shown = nearest ? rows.slice(0, 5) : rows.slice(0, 40);

        // Without a location or a search the map IS the picker; a list of every
        // point in Cyprus would just be a wall. If the map failed to load, the
        // list has to carry it.
        var listVisible = nearest || !!q || state.mapFailed;
        var list = $('acsList');
        list.hidden = !listVisible;
        list.innerHTML = shown.map(function (r) {
            var sel = state.point && state.point.id === r.p.id;
            return '<li><button type="button" class="acs-point' + (sel ? ' is-selected' : '') + '" data-id="' + r.p.id + '" aria-pressed="' + sel + '">' +
                '<span class="acs-point-main"><strong>' + esc(r.p.name) + '</strong>' +
                '<span class="acs-point-kind">' + esc(t('checkout.pickup.kind.' + r.p.kind)) + '</span></span>' +
                '<span class="acs-point-addr">' + esc(r.p.address) + (r.p.city ? ', ' + esc(r.p.city) : '') + '</span>' +
                (r.d !== null ? '<span class="acs-point-dist">' + (r.d < 1 ? Math.round(r.d * 1000) + ' m' : r.d.toFixed(1) + ' km') + '</span>' : '') +
                '</button></li>';
        }).join('');

        if (notice) setStatus(notice + ' ' + t('checkout.pickup.pick_on_map'));
        else if (nearest) setStatus(t('checkout.pickup.nearest', { count: shown.length }));
        else if (q) setStatus(shown.length ? t('checkout.pickup.results', { count: rows.length }) : t('checkout.pickup.no_results'));
        else setStatus(t('checkout.pickup.pick_on_map'));

        if (state.map) {
            var ids = {};
            shown.forEach(function (r) { ids[r.p.id] = true; });
            Object.keys(state.markers).forEach(function (id) {
                var m = state.markers[id];
                var isSel = state.point && String(state.point.id) === id;
                m.setStyle(isSel ? STYLE.selected : ((nearest || q) && ids[id] ? STYLE.near : STYLE.point));
                if (isSel) m.bringToFront();
            });
            var frame = (nearest || q) ? shown.map(function (r) { return r.p; }) : state.points;
            if (nearest && state.userPos) frame = frame.concat([state.userPos]);
            if (frame.length) fit(frame);
        }
    }

    function fit(pts) {
        if (!state.map || !pts.length) return;
        if (pts.length === 1) { state.map.setView([pts[0].lat, pts[0].lng], 14); return; }
        state.map.fitBounds(L.latLngBounds(pts.map(function (p) { return [p.lat, p.lng]; })), { padding: [24, 24], maxZoom: 14 });
    }

    function select(id) {
        var p = (state.points || []).filter(function (x) { return x.id === Number(id); })[0];
        if (!p) return;
        state.point = p;
        showPointError('');
        markSections();

        var box = $('acsSelected');
        var hours = [p.hours, p.hours_saturday ? t('checkout.pickup.saturday') + ' ' + p.hours_saturday : '']
            .filter(Boolean).join(' · ');
        box.innerHTML =
            '<span class="acs-selected-label">' + esc(t('checkout.pickup.selected')) + '</span>' +
            '<strong>' + esc(p.name) + '</strong>' +
            '<span>' + esc(p.address) + (p.city ? ', ' + esc(p.city) : '') + '</span>' +
            (hours ? '<span class="acs-selected-hours">' + esc(hours) + '</span>' : '') +
            (p.kind === 'locker' ? '<span class="acs-selected-hours">' + esc(t('checkout.pickup.locker_note')) + '</span>' : '');
        box.hidden = false;

        document.querySelectorAll('.acs-point').forEach(function (b) {
            var on = Number(b.dataset.id) === p.id;
            b.classList.toggle('is-selected', on);
            b.setAttribute('aria-pressed', on);
        });
        if (state.map) {
            Object.keys(state.markers).forEach(function (mid) {
                if (Number(mid) === p.id) state.markers[mid].setStyle(STYLE.selected).bringToFront();
                else if (state.markers[mid].options.fillColor === STYLE.selected.fillColor) state.markers[mid].setStyle(STYLE.point);
            });
            state.map.panTo([p.lat, p.lng]);
        }
    }

    // ------------------------------------------------------------- payment

    function showPayError(msg) {
        var box = $('payError');
        $('payErrorText').textContent = msg || '';
        box.hidden = !msg;
    }

    // ------------------------------------------------------------- terms

    function termsOk() {
        var ok = $('termsAccept').checked;
        $('coTerms').classList.toggle('is-invalid', !ok);
        $('termsError').hidden = ok;
        return ok;
    }

    function mountPayment() {
        if (!cfg.stripeKey || typeof Stripe === 'undefined') {
            showPayError(t('checkout.errors.stripe_missing'));
            $('payBtn').disabled = true;
            return;
        }
        var c = contact();
        state.stripe = Stripe(cfg.stripeKey, { locale: cfg.locale });
        // Deferred intent: the element renders from the amount alone; the
        // PaymentIntent is created only when the customer presses Pay.
        state.elements = state.stripe.elements({
            mode: 'payment',
            amount: totalCents(),
            currency: 'eur',
            fonts: [{ cssSrc: 'https://fonts.googleapis.com/css2?family=Geologica:wght@400;500;600&display=swap' }],
            appearance: {
                theme: 'flat',
                variables: {
                    colorPrimary: '#0D0D0D',
                    colorBackground: '#FFFFFF',
                    colorText: '#0D0D0D',
                    colorTextSecondary: '#5F5E59',
                    colorTextPlaceholder: '#6F6E68',
                    colorDanger: '#B3271B',
                    colorSuccess: '#1F7A46',
                    fontFamily: '"Geologica", system-ui, sans-serif',
                    fontSizeBase: '16px',
                    fontWeightMedium: '600',
                    borderRadius: '0px',
                    spacingUnit: '4px',
                    gridRowSpacing: '16px',
                    gridColumnSpacing: '16px',
                    focusOutline: 'none',
                    focusBoxShadow: '0 0 0 1px #0D0D0D'
                },
                rules: {
                    '.Label': { fontWeight: '500', color: '#0D0D0D', marginBottom: '6px' },
                    '.Input': { border: '1px solid #8A8983', boxShadow: 'none', padding: '12px 14px' },
                    '.Input:hover': { border: '1px solid #5F5E59' },
                    '.Input:focus': { border: '1px solid #0D0D0D', boxShadow: '0 0 0 1px #0D0D0D' },
                    '.Input--invalid': { border: '1px solid #B3271B', boxShadow: 'none' },
                    '.Error': { fontWeight: '600' },
                    '.AccordionItem': { border: '1px solid #8A8983', boxShadow: 'none', backgroundColor: '#FFFFFF' },
                    '.AccordionItem--selected': { border: '1px solid #0D0D0D', boxShadow: '0 0 0 1px #0D0D0D' }
                }
            }
        });

        // Accordion: Stripe's recommendation above four payment methods.
        state.paymentEl = state.elements.create('payment', {
            layout: { type: 'accordion', defaultCollapsed: false, radios: 'always', spacedAccordionItems: true },
            // No Link. With the customer's email filled in, Stripe recognised
            // a Link account and put a one-time-code prompt over the card
            // form, with "pay without Link" hidden behind a ⋯ menu. The card
            // form now shows straight away; Apple Pay / Google Pay unaffected.
            wallets: { link: 'never' },
            business: { name: cfg.business },
            defaultValues: { billingDetails: { name: c.name, email: c.email || cfg.accountEmail || '', phone: c.phone } },
            fields: { billingDetails: { address: 'if_required' } }
        });
        state.paymentEl.on('ready', function () { $('peStatus').textContent = ''; });
        state.paymentEl.on('loaderror', function () { showPayError(t('checkout.errors.stripe_missing')); });
        state.paymentEl.on('change', function (e) {
            $('coSectionPayment').classList.toggle('is-complete', !!e.complete);
        });
        state.paymentEl.mount('#paymentElement');
        lastSynced = JSON.stringify({ name: c.name, email: c.email || cfg.accountEmail || '', phone: c.phone });
    }

    var payLabel = $('payLabel');
    var payLabelHtml = payLabel.innerHTML;

    function setBusy(on) {
        state.busy = on;
        var b = $('payBtn');
        b.disabled = on;
        b.classList.toggle('is-busy', on);
        form.setAttribute('aria-busy', on ? 'true' : 'false');
        if (on) {
            payLabel.textContent = t('checkout.paying');
        } else {
            payLabel.innerHTML = payLabelHtml;
            refreshTotals();
        }
    }

    async function pay(ev) {
        ev.preventDefault();
        if (state.busy) return;
        showPayError('');

        // 1. Our own fields.
        var firstBad = validateDetails();
        var pointMissing = state.method === 'acs_point' && !state.point;
        if (pointMissing) showPointError(t('checkout.pickup.errors.no_point'));
        var termsAccepted = termsOk();
        markSections();
        if (firstBad || pointMissing || !termsAccepted) {
            var target = firstBad || (pointMissing ? $('acsLocator') : $('coTerms'));
            scrollToEl(target);
            if (firstBad) firstBad.focus({ preventScroll: true });
            else if (!pointMissing) $('termsAccept').focus({ preventScroll: true });
            return;
        }
        if (!state.elements) {
            showPayError(t('checkout.errors.stripe_missing'));
            return;
        }

        setBusy(true);
        try {
            // 2. Stripe's fields. Must be the first await: Apple Pay only
            // opens from inside the click.
            var submitted = await state.elements.submit();
            if (submitted.error) {
                // The Payment Element shows the message next to the field.
                scrollToEl($('coSectionPayment'));
                setBusy(false);
                return;
            }

            // 3. The PaymentIntent.
            var res = await fetch('/api/create-payment-intent', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    delivery_method: state.method,
                    pickup_point_id: state.point ? state.point.id : null,
                    contact: contact(),
                    terms_accepted: $('termsAccept').checked,
                    expected_amount: totalCents()
                })
            });
            var data = await res.json().catch(function () { return {}; });
            if (!res.ok || !data.clientSecret) {
                showPayError(data.error || t('checkout.errors.generic'));
                scrollToEl($('payError'));
                if (data.reload) setTimeout(function () { location.reload(); }, 2500);
                setBusy(false);
                return;
            }

            // 4. Confirm. Success leaves this page for /checkout/complete, so
            // the button stays busy; only an error comes back here.
            var result = await state.stripe.confirmPayment({
                elements: state.elements,
                clientSecret: data.clientSecret,
                confirmParams: { return_url: cfg.returnUrl }
            });
            var err = result.error || {};
            // Stripe's wording is right for the customer on card and form
            // problems (declined, wrong CVC); anything else is ours to explain.
            var own = err.type === 'card_error' || err.type === 'validation_error';
            showPayError(own && err.message ? err.message : t('checkout.errors.generic'));
            scrollToEl($('payError'));
            setBusy(false);
        } catch (e) {
            console.error('Checkout error:', e);
            showPayError(t('checkout.errors.generic'));
            setBusy(false);
        }
    }

    // ------------------------------------------------------------- wiring

    form.addEventListener('submit', pay);
    // Ticking the box clears its error straight away.
    $('termsAccept').addEventListener('change', function () {
        if (this.checked) termsOk();
    });

    document.querySelectorAll('input[name="deliveryMethod"]').forEach(function (r) {
        r.addEventListener('change', function () { if (r.checked) setMethod(r.value); });
    });
    var list = $('acsList');
    if (list) list.addEventListener('click', function (e) {
        var b = e.target.closest('.acs-point');
        if (b) select(b.dataset.id);
    });
    var search = $('acsSearch');
    if (search) {
        search.addEventListener('input', function () { render(); });
        // Enter searches; it must not submit the payment form.
        search.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    }
    var locBtn = $('acsLocateBtn');
    if (locBtn) locBtn.addEventListener('click', function () { state.locAsked = true; locate(); });

    var toggle = $('coSummaryToggle');
    if (toggle) toggle.addEventListener('click', function () {
        var open = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        $('coSummary').classList.toggle('is-open', open);
        toggle.querySelector('[data-when="closed"]').hidden = open;
        toggle.querySelector('[data-when="open"]').hidden = !open;
    });

    // Coming back with the browser's Back button (e.g. from PayPal) can
    // restore the page from cache mid-payment; make it usable again.
    window.addEventListener('pageshow', function (e) { if (e.persisted && state.busy) setBusy(false); });

    restore();
    var checkedMethod = document.querySelector('input[name="deliveryMethod"]:checked');
    if (checkedMethod) state.method = checkedMethod.value;
    refreshTotals();
    markSections();
    mountPayment();
})();
