/**
 * Shared UI primitives: toasts and button pending states.
 *
 * Replaces alert() across the site. alert() blocks the whole page, can't be
 * styled, looks like a browser error to a shopper, and on repeated calls
 * Chrome offers to suppress further dialogs — which silently swallows later
 * messages. Toasts are non-blocking and announce themselves to screen readers.
 *
 *   UI.toast('Saved');                      // neutral
 *   UI.success('Added to cart');
 *   UI.error('Could not save your design');
 *   UI.loading(button, true) / (button, false);
 */
(function (global) {
    'use strict';

    var ICONS = {
        success: '<path d="M20 6 9 17l-5-5"/>',
        error:   '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
        info:    '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>'
    };

    function region() {
        var el = document.getElementById('toastRegion');
        if (el) return el;
        el = document.createElement('div');
        el.id = 'toastRegion';
        el.className = 'toast-region';
        // polite: announced when the user is idle, so it never interrupts
        // whatever they are reading mid-sentence.
        el.setAttribute('aria-live', 'polite');
        el.setAttribute('aria-atomic', 'false');
        // A script that runs before any body markup has no document.body yet,
        // and appending to null throws — which would take out whatever called
        // us. Attach to <html> now and move it once the body exists.
        if (document.body) {
            document.body.appendChild(el);
        } else {
            document.documentElement.appendChild(el);
            document.addEventListener('DOMContentLoaded', function () {
                if (document.body && el.parentNode !== document.body) document.body.appendChild(el);
            });
        }
        return el;
    }

    /**
     * @param {string} message
     * @param {string} type    'success' | 'error' | 'info'
     * @param {number|object} opts  milliseconds, or { ms, action: {label, href} }
     */
    function show(message, type, opts) {
        if (!message) return null;
        type = type || 'info';
        if (typeof opts === 'number' || opts == null) opts = { ms: opts };
        var ms = opts.ms;

        var host = region();
        var el = document.createElement('div');
        el.className = 'toast toast-' + type;
        // Errors interrupt: the user needs to know before carrying on.
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');

        var icon = '<svg class="toast-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" ' +
                   'stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" ' +
                   'aria-hidden="true">' + (ICONS[type] || ICONS.info) + '</svg>';

        var text = document.createElement('span');
        text.className = 'toast-msg';
        text.textContent = message;          // textContent: never inject markup

        el.innerHTML = icon;
        el.appendChild(text);

        // Optional follow-up link, e.g. "Go to cart" after adding an item.
        if (opts.action && opts.action.label && opts.action.href) {
            var a = document.createElement('a');
            a.className = 'toast-action';
            a.href = opts.action.href;
            a.textContent = opts.action.label;
            el.appendChild(a);
        }

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'toast-close';
        close.setAttribute('aria-label', 'Dismiss');
        close.innerHTML = '&times;';
        close.addEventListener('click', function () { dismiss(el); });
        el.appendChild(close);

        host.appendChild(el);
        // Next frame, so the entry transition actually runs.
        requestAnimationFrame(function () { el.classList.add('is-in'); });

        // Errors stay until dismissed — auto-hiding a failure means the user
        // can miss the reason their action didn't work.
        var life = ms != null ? ms : (type === 'error' ? 0 : 4000);
        if (life > 0) {
            var t = setTimeout(function () { dismiss(el); }, life);
            // Reading it shouldn't race the timer.
            el.addEventListener('mouseenter', function () { clearTimeout(t); });
            el.addEventListener('mouseleave', function () {
                t = setTimeout(function () { dismiss(el); }, 1500);
            });
        }
        return el;
    }

    function dismiss(el) {
        if (!el || el.dataset.going) return;
        el.dataset.going = '1';
        el.classList.remove('is-in');
        el.addEventListener('transitionend', function () { el.remove(); }, { once: true });
        // Belt and braces if the transition never fires (display:none ancestor).
        setTimeout(function () { if (el.parentNode) el.remove(); }, 400);
    }

    /**
     * Toggle a button's pending state. Disables it too, so a double-click
     * can't fire the same request twice.
     */
    function loading(btn, on) {
        if (!btn) return;
        if (on) {
            btn.classList.add('is-loading');
            btn.setAttribute('aria-busy', 'true');
            btn.disabled = true;
        } else {
            btn.classList.remove('is-loading');
            btn.removeAttribute('aria-busy');
            btn.disabled = false;
        }
    }

    global.UI = {
        toast:   function (m, o) { return show(m, 'info', o); },
        success: function (m, o) { return show(m, 'success', o); },
        error:   function (m, o) { return show(m, 'error', o); },
        dismiss: dismiss,
        loading: loading
    };
})(window);
