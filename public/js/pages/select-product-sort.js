/* select-product-sort.js — from views/shop/select_product.php, loaded where the inline script used to run. */

// ── Product picker: filter by garment family + sort ──────────────────────
// Entirely client-side: 19 products is far too few to justify a round trip,
// and instant feedback is the point. Cards carry data-family / data-price /
// data-name so no lookup table has to be kept in sync.
(function () {
    var grid = document.getElementById('productListGrid');
    if (!grid) return;
    var chips   = Array.prototype.slice.call(document.querySelectorAll('.picker-chip'));
    var sortSel = document.getElementById('pickerSort');
    var countEl = document.getElementById('pickerCount');
    var cards   = Array.prototype.slice.call(grid.querySelectorAll('.product-list-card'));
    var order   = cards.slice();            // original ("featured") order
    var family  = 'all';

    function apply() {
        var list = order.slice();
        var mode = sortSel ? sortSel.value : 'featured';
        if (mode === 'price-asc')  list.sort(function (a, b) { return pf(a) - pf(b); });
        if (mode === 'price-desc') list.sort(function (a, b) { return pf(b) - pf(a); });
        if (mode === 'name')       list.sort(function (a, b) {
            return (a.dataset.name || '').localeCompare(b.dataset.name || '');
        });

        var shown = 0;
        list.forEach(function (card) {
            var match = (family === 'all') || card.dataset.family === family;
            card.style.display = match ? '' : 'none';
            if (match) shown++;
            grid.appendChild(card);          // re-order in place
        });
        if (countEl) {
            var tpl = (window.I18N && window.I18N.t('shop.select.showing')) || '{n} products';
            countEl.textContent = tpl.replace('{n}', shown);
        }
    }
    function pf(c) { return parseFloat(c.dataset.price) || 0; }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (c) { c.classList.remove('is-active'); });
            chip.classList.add('is-active');
            family = chip.dataset.family;
            apply();
        });
    });
    if (sortSel) sortSel.addEventListener('change', apply);
    apply();
})();
