/* select-product-sort.js — from views/shop/select_product.php, loaded where the inline script used to run. */

// ── Product picker: sort ──────────────────────────────────────────────────
// Client-side: twenty-odd products are far too few to justify a round trip.
// Cards carry data-price / data-name. The garment filters are links to each
// family's own page (/shop/hoodies), which lists only that family.
(function () {
    var grid = document.getElementById('productListGrid');
    if (!grid) return;
    var sortSel = document.getElementById('pickerSort');
    var countEl = document.getElementById('pickerCount');
    var cards   = Array.prototype.slice.call(grid.querySelectorAll('.product-list-card'));
    var order   = cards.slice();            // original ("featured") order

    function apply() {
        var list = order.slice();
        var mode = sortSel ? sortSel.value : 'featured';
        if (mode === 'price-asc')  list.sort(function (a, b) { return pf(a) - pf(b); });
        if (mode === 'price-desc') list.sort(function (a, b) { return pf(b) - pf(a); });
        if (mode === 'name')       list.sort(function (a, b) {
            return (a.dataset.name || '').localeCompare(b.dataset.name || '');
        });

        list.forEach(function (card) {
            grid.appendChild(card);          // re-order in place
        });
        var shown = list.length;
        if (countEl) {
            var tpl = (window.I18N && window.I18N.t('shop.select.showing')) || '{n} products';
            countEl.textContent = tpl.replace('{n}', shown);
        }
    }
    function pf(c) { return parseFloat(c.dataset.price) || 0; }

    if (sortSel) sortSel.addEventListener('change', apply);
    apply();
})();
