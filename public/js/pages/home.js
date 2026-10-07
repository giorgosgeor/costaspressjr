/* home.js — from views/pages/home.php, loaded where the inline script used to run. */

// Featured product cards open the customiser with that product preselected —
// the same handshake the product picker uses (/shop/set_selected_product
// stores the id in the session, /shop/custom_product reads it back). Works
// for guests too: designing no longer requires an account.
document.addEventListener('DOMContentLoaded', function () {
    function openProduct(card) {
        var productId = card.getAttribute('data-product-id');
        if (!productId) return;
        var meta = document.querySelector('meta[name="csrf-token"]');
        fetch('/shop/set_selected_product', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': meta ? meta.getAttribute('content') : ''
            },
            body: JSON.stringify({ product_id: productId })
        }).then(function (res) {
            if (res.ok) window.location.href = '/shop/custom_product';
        }).catch(function () {});
    }
    // Expand/collapse the grid. Progressive enhancement: with no JS the eight
    // cards still render and the site's nav still reaches /shop, so nothing is
    // stranded behind a button that cannot work.
    var toggle = document.getElementById('productsToggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') === 'true';
            document.querySelectorAll('#productsGrid .is-extra').forEach(function (el) {
                el.hidden = open;
            });
            toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            toggle.classList.toggle('is-open', !open);
            var label = toggle.querySelector('[data-toggle-label]');
            if (label) {
                label.textContent = open
                    ? toggle.getAttribute('data-label-more')
                    : toggle.getAttribute('data-label-less');
            }
        });
    }

    document.querySelectorAll('.product-card.featured-product').forEach(function (card) {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function () { openProduct(card); });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openProduct(card); }
        });
    });
});
