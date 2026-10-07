/* mobile-bar.js — from views/shop/designer.php, loaded where the inline script used to run. */

(function () {
    'use strict';

    var tab    = document.getElementById('studioToolsTab');
    var panel  = document.getElementById('studioControls');
    var scrim  = document.getElementById('studioScrim');
    var isPhone = function () { return window.matchMedia('(max-width: 900px)').matches; };

    function setDrawer(open) {
        if (!panel) return;
        panel.classList.toggle('is-open', open);
        if (tab) {
            tab.classList.toggle('is-open', open);
            tab.setAttribute('aria-expanded', open ? 'true' : 'false');
            var lbl = open ? tab.getAttribute('data-label-close') : tab.getAttribute('data-label-open');
            if (lbl) tab.setAttribute('aria-label', lbl);
        }
        if (scrim) {
            scrim.classList.toggle('is-open', open);
            // hidden is toggled as well as the class: a scrim left in the
            // accessibility tree is an invisible element a screen reader can
            // still land on.
            if (open) { scrim.removeAttribute('hidden'); }
            else { scrim.setAttribute('hidden', ''); }
        }
        document.body.classList.toggle('studio-drawer-open', open);
    }

    if (tab && panel) {
        tab.addEventListener('click', function () {
            setDrawer(!panel.classList.contains('is-open'));
        });
    }
    if (scrim) {
        scrim.addEventListener('click', function () { setDrawer(false); });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel && panel.classList.contains('is-open')) setDrawer(false);
    });

    // The drawer deliberately does NOT close when a tool is chosen.
    //
    // It used to, on the theory that picking a tool was the end of what the
    // drawer was for. That was wrong: choosing Uploads or Add Text opens that
    // tool's panel INSIDE this drawer, so closing on the same tap hid the
    // exact thing the tap had just opened — the panel was there all along,
    // only visible again after reopening the drawer. It stays open; the tab,
    // the scrim and Escape are how it closes.

    // Growing past the breakpoint turns the drawer back into a column; the
    // scroll lock has to come off with it or the page is frozen for no
    // visible reason.
    window.addEventListener('resize', function () {
        if (!isPhone() && panel && panel.classList.contains('is-open')) setDrawer(false);
    });

    // ---- Action bar ----
    document.body.classList.add('studio-has-bar');
    function forward(fromId, toId) {
        var from = document.getElementById(fromId), to = document.getElementById(toId);
        if (from && to) { from.addEventListener('click', function () { to.click(); }); }
    }
    forward('mobAddToCart', 'addToCartDirectBtn');
    forward('mobSaveDesign', 'saveDesignBtn');
})();
