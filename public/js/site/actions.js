/* actions.js — page behaviour declared with data-* attributes instead of
 * inline on* handlers, so templates carry no inline JavaScript (and the
 * Content-Security-Policy can forbid it).
 *
 *   data-on-click="fn"            call the global function fn on click
 *   data-on-change, -input,
 *   -drop, -dragover, -dragleave  the same for those events
 *   data-args='[12, "front"]'     its arguments (JSON). "$this" is the element,
 *                                 "$event" the event, "$value" the element's
 *                                 value, "$files" its files
 *   data-click-self               only when the click lands on the element
 *                                 itself, not inside it (modal backdrops)
 *   data-stop-click               clicks inside go no further out (a modal's box)
 *   data-prevent-default          cancel the default action (a link's navigation)
 *   data-href="/cart"             go to a URL (after data-on-click, if any)
 *   data-confirm="Sure?"          on a form: ask before submitting
 *   data-fallback="/a.png"        on an image: try this src once if it fails
 *   data-hide-on-error, data-hide-parent-on-error, data-remove-on-error
 *   data-show-on-load             show the image once it has loaded
 *
 * Functions are called as an inline onclick="fn()" called them (this is the
 * window, not the element; pass "$this" for the element).
 * Handlers run in the order inline ones did: the innermost element first,
 * before any listener a container added, so this listens in the capture
 * phase. A function that returns false cancels the default action, as an
 * inline handler's `return false` did. Loaded in <head> without defer, so it
 * is in place before the first image can fail to load.
 */
(function () {
    'use strict';

    var TYPES = ['click', 'change', 'input', 'drop', 'dragover', 'dragleave'];

    // An inline handler saw event.currentTarget as its own element; listening
    // on the document makes it the document. Handlers get a view of the event
    // that answers currentTarget with the element and passes the rest through.
    function eventFor(event, el) {
        return new Proxy(event, {
            get: function (target, prop) {
                if (prop === 'currentTarget') return el;
                var v = target[prop];
                return typeof v === 'function' ? v.bind(target) : v;
            }
        });
    }

    function args(el, event) {
        var raw = el.getAttribute('data-args');
        if (!raw) return [];
        return JSON.parse(raw).map(function (a) {
            if (a === '$this') return el;
            if (a === '$event') return eventFor(event, el);
            if (a === '$value') return el.value;
            if (a === '$files') return el.files;
            return a;
        });
    }

    TYPES.forEach(function (type) {
        var attr = 'data-on-' + type;
        document.addEventListener(type, function (event) {
            var node = event.target;
            if (node && node.nodeType !== 1) node = node.parentElement;
            for (; node && node !== document.documentElement; node = node.parentElement) {
                var isClick = type === 'click';
                var acts = node.hasAttribute(attr) || (isClick && node.hasAttribute('data-href'));
                if (acts && !(isClick && node.hasAttribute('data-click-self') && event.target !== node)) {
                    if (node.hasAttribute('data-prevent-default')) event.preventDefault();
                    if (node.hasAttribute(attr)) {
                        var name = node.getAttribute(attr);
                        var fn = window[name];
                        if (typeof fn !== 'function') {
                            console.error(attr + '="' + name + '": no such global function');
                        } else if (fn.apply(window, args(node, event)) === false) {
                            event.preventDefault();
                        }
                    }
                    if (isClick && node.hasAttribute('data-href')) {
                        window.location.href = node.getAttribute('data-href');
                    }
                }
                // The box's own listener (bound below) stops the event there;
                // nothing further out may act on it either.
                if (isClick && node.hasAttribute('data-stop-click')) break;
            }
        }, true);
    });

    // A modal's content box: clicks inside must not reach the backdrop or the
    // document, exactly as its inline event.stopPropagation() did.
    function bindStops(root) {
        root.querySelectorAll('[data-stop-click]').forEach(function (el) {
            if (el.__stopBound) return;
            el.__stopBound = true;
            el.addEventListener('click', function (e) { e.stopPropagation(); });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { bindStops(document); });
    } else {
        bindStops(document);
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form.hasAttribute && form.hasAttribute('data-confirm') && !window.confirm(form.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    }, true);

    // Image load/error events don't bubble, but they do pass through the
    // capture phase, so one listener sees every image on the page.
    document.addEventListener('error', function (event) {
        var img = event.target;
        if (!img || img.tagName !== 'IMG') return;
        if (img.hasAttribute('data-fallback') && !img.__fellBack) {
            img.__fellBack = true;
            img.src = img.getAttribute('data-fallback');
        } else if (img.hasAttribute('data-remove-on-error')) {
            img.remove();
        } else if (img.hasAttribute('data-hide-parent-on-error')) {
            if (img.parentElement) img.parentElement.style.display = 'none';
        } else if (img.hasAttribute('data-hide-on-error')) {
            img.style.display = 'none';
        }
    }, true);

    document.addEventListener('load', function (event) {
        var img = event.target;
        if (img && img.tagName === 'IMG' && img.hasAttribute('data-show-on-load')) {
            img.style.display = img.getAttribute('src') ? '' : 'none';
        }
    }, true);
})();
