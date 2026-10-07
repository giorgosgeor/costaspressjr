/* custom-product-action-bar.js — from views/shop/custom_product.php, loaded where the inline script used to run. */

(function () {
    document.body.classList.add('has-action-bar');

    var start = document.getElementById('mabStart');
    var real  = document.getElementById('startDesigningBtn');
    if (start && real) {
        start.addEventListener('click', function () { real.click(); });
    }

    // lib/price-tiers.js owns the per-unit figure and re-renders it whenever the
    // quantity changes. Mirroring its output keeps one source of truth; the
    // observer is needed because that block is replaced wholesale, so a
    // reference taken once would go stale on the first update.
    var panel = document.getElementById('productPriceTiers');
    var out   = document.getElementById('mabAmount');
    if (panel && out && 'MutationObserver' in window) {
        var sync = function () {
            var unit = panel.querySelector('.price-tiers-unit');
            if (!unit) return;
            var text = (unit.childNodes[0] && unit.childNodes[0].nodeValue || '').trim();
            if (text) out.textContent = text;
        };
        new MutationObserver(sync).observe(panel, { childList: true, subtree: true });
        sync();
    }
})();
