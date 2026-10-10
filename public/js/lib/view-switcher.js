/**
 * Placement switcher — dots + swipe.
 *
 * Replaces the old row of Front/Back/Left Sleeve/Right Sleeve buttons with a
 * compact bar: clickable dots on the left, the current view's name on the
 * right. Swiping the mockup left/right moves between views.
 *
 * Design notes:
 *  - The dots ARE the original buttons, restyled. They keep their ids,
 *    data-view/data-side attributes and .active class, so every existing
 *    handler (switchView, the .click() calls that reset to front, the
 *    style.display toggles that hide sleeves a product doesn't have) keeps
 *    working untouched.
 *  - The label follows the .active class via a MutationObserver rather than
 *    being pushed by the switch functions — so neither page's switching logic
 *    needed to change, and the label stays correct no matter who flips it.
 *  - Hidden dots (display:none) are skipped when swiping, so a product with no
 *    sleeves swipes straight from Back back round to Front.
 *  - Arrows on the mockup's left and right edges step to the previous/next
 *    view. They exist only while the product has more than one view, and show
 *    when the pointer is over the mockup (CSS: .view-arrow, in shop.css).
 */
(function (global) {
    'use strict';

    // A view is available when its dot isn't display:none — that is exactly the
    // mechanism both pages already use to hide sleeves a product doesn't have.
    // (Checked via computed style rather than offsetParent, which is also null
    // for unrelated reasons such as a fixed-position ancestor.)
    function visibleDots(dots) {
        return Array.prototype.filter.call(dots, function (d) {
            return window.getComputedStyle(d).display !== 'none';
        });
    }

    function init(opts) {
        var dotWrap = document.querySelector(opts.dots);
        var label   = document.querySelector(opts.label);
        var surface = document.querySelector(opts.surface);
        if (!dotWrap) return;

        var dotSel = opts.dotSelector || 'button';

        function all() { return dotWrap.querySelectorAll(dotSel); }

        function activeDot() {
            var found = null;
            Array.prototype.forEach.call(all(), function (d) {
                if (d.classList.contains('active')) found = d;
            });
            return found;
        }

        function syncLabel() {
            if (!label) return;
            var a = activeDot();
            if (a) label.textContent = a.getAttribute('data-label') || '';
        }

        // ---- arrows: previous / next view, on the mockup itself ----
        var arrows = [];
        if (surface && opts.arrows !== false) {
            var say = function (key, fallback) {
                return (global.I18N && global.I18N.t) ? global.I18N.t(key) : fallback;
            };
            arrows = [-1, 1].map(function (dir) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'view-arrow ' + (dir < 0 ? 'view-arrow-prev' : 'view-arrow-next');
                var name = dir < 0 ? say('view.prev', 'Previous view') : say('view.next', 'Next view');
                b.setAttribute('aria-label', name);
                b.title = name;
                b.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                    + (dir < 0 ? '<polyline points="15 18 9 12 15 6"/>' : '<polyline points="9 18 15 12 9 6"/>')
                    + '</svg>';
                b.hidden = true;
                b.addEventListener('click', function (e) {
                    e.preventDefault();
                    // Not a click on the mockup: that would deselect artwork.
                    e.stopPropagation();
                    step(dir);
                });
                surface.appendChild(b);
                return b;
            });
        }
        // Only worth showing with somewhere to go.
        function syncArrows() {
            var several = visibleDots(all()).length > 1;
            arrows.forEach(function (b) { b.hidden = !several; });
        }

        function sync() {
            syncLabel();
            syncArrows();
        }

        // Follow whoever sets .active or hides a view — no changes needed in
        // the page's own switching code.
        var observer = new MutationObserver(sync);
        Array.prototype.forEach.call(all(), function (d) {
            observer.observe(d, { attributes: true, attributeFilter: ['class', 'style'] });
        });

        // Always open on the first (front) view. Restored state used to leave
        // the dots and the mockup disagreeing — the label said FRONT while the
        // back image was showing — and the first click then looked like a no-op
        // because it targeted the view already flagged active.
        var first = visibleDots(all())[0];
        if (first && !first.classList.contains('active')) {
            first.click();
        }
        sync();

        function step(dir) {
            var vis = visibleDots(all());
            if (vis.length < 2) return;
            var cur = vis.indexOf(activeDot());
            if (cur === -1) cur = 0;
            var next = (cur + dir + vis.length) % vis.length;
            vis[next].click();
        }

        // ---- swipe (touch) and drag (mouse) ----
        // Same gesture on both: the mockup is the natural thing to throw
        // left/right, and on desktop a mouse drag is the obvious equivalent of
        // the phone swipe.
        if (surface) {
            var x0 = null, y0 = null, tracking = false;
            var IGNORE = opts.ignore || '.design-element';
            var THRESHOLD = 45;          // px of horizontal travel to count
            var H_BIAS = 1.5;            // must be this much more horizontal than vertical

            function startsOnDesign(target) {
                return target && target.closest && target.closest(IGNORE);
            }
            function finish(dx, dy) {
                if (Math.abs(dx) < THRESHOLD || Math.abs(dx) < Math.abs(dy) * H_BIAS) return;
                step(dx < 0 ? 1 : -1);
            }

            surface.addEventListener('touchstart', function (e) {
                // Don't hijack a drag of the design itself.
                if (startsOnDesign(e.target) || e.touches.length !== 1) { tracking = false; return; }
                tracking = true;
                x0 = e.touches[0].clientX;
                y0 = e.touches[0].clientY;
            }, { passive: true });

            surface.addEventListener('touchend', function (e) {
                if (!tracking || x0 === null) return;
                tracking = false;
                var t = e.changedTouches[0];
                finish(t.clientX - x0, t.clientY - y0);
                x0 = y0 = null;
            }, { passive: true });

            // Mouse drag. Left button only, and never when the gesture starts on
            // a design element — those are dragged with interact.js.
            surface.addEventListener('mousedown', function (e) {
                if (e.button !== 0 || startsOnDesign(e.target)) { tracking = false; return; }
                tracking = true;
                x0 = e.clientX;
                y0 = e.clientY;
                surface.classList.add('is-grabbable');
            });

            document.addEventListener('mouseup', function (e) {
                if (!tracking || x0 === null) return;
                tracking = false;
                surface.classList.remove('is-grabbable');
                finish(e.clientX - x0, e.clientY - y0);
                x0 = y0 = null;
            });

            // Stop a drag across the image from selecting the alt text/image.
            surface.addEventListener('dragstart', function (e) {
                if (!startsOnDesign(e.target)) e.preventDefault();
            });
        }

        // ---- keyboard: arrows move between views when a dot has focus ----
        dotWrap.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight') { e.preventDefault(); step(1); focusActive(); }
            else if (e.key === 'ArrowLeft') { e.preventDefault(); step(-1); focusActive(); }
        });
        function focusActive() {
            var a = activeDot();
            if (a) a.focus();
        }

        return { next: function () { step(1); }, prev: function () { step(-1); }, sync: sync };
    }

    global.ViewSwitcher = { init: init };
})(window);
