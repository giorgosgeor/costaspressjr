/**
 * Live quantity → price widget.
 *
 * The pricing engine gives real volume discounts (a tee is €16.00 at qty 1 and
 * €8.50 at 100+), but until now that ladder was only visible in a hover popup
 * on the product picker. This renders it as a first-class part of the page:
 * unit price and line total for the quantity currently chosen, the full tier
 * ladder with the active row marked, and a nudge showing what the next tier
 * would save.
 *
 * All arithmetic goes through window.Pricing (public/js/pricing.js), which
 * mirrors app/core/Pricing.php — so what is shown here is what checkout
 * charges. Nothing is computed independently.
 *
 * Usage:
 *   <div class="price-tiers" data-supplier-cost="2.80" data-product-name="T-Shirt"
 *        data-design-price="0" data-quantity="1"></div>
 *   PriceTiers.mount(el)                  // or PriceTiers.mountAll()
 *   PriceTiers.update(el, { quantity: 5, extraPrintCost: 3 })
 */
(function (global) {
    'use strict';

    // Tier start quantities, mirroring Pricing::TIERS. Both categories use the
    // same quantity bands; only the margin differs.
    var BANDS = [1, 5, 15, 30, 50, 100];

    function money(n) {
        return '€' + (Math.round(n * 100) / 100).toFixed(2);
    }

    function t(key, fallback) {
        if (global.I18N && typeof global.I18N.t === 'function') {
            var s = global.I18N.t(key);
            if (s && s !== key) return s;
        }
        return fallback;
    }

    function readConfig(el) {
        return {
            supplierCost:   parseFloat(el.getAttribute('data-supplier-cost')) || 0,
            productName:    el.getAttribute('data-product-name') || '',
            productSlug:    el.getAttribute('data-product-slug') || '',
            designPrice:    parseFloat(el.getAttribute('data-design-price')) || 0,
            extraPrintCost: parseFloat(el.getAttribute('data-extra-print')) || 0,
            quantity:       Math.max(1, parseInt(el.getAttribute('data-quantity'), 10) || 1)
        };
    }

    // Per-unit price a customer pays at a given quantity, design fee included.
    function unitAt(cfg, qty) {
        if (!global.Pricing) return cfg.supplierCost + cfg.designPrice;
        var category = global.Pricing.categoryFor(cfg.productSlug, cfg.productName);
        return global.Pricing.unitPrice(cfg.supplierCost, category, qty, cfg.extraPrintCost)
             + cfg.designPrice;
    }

    function bandFor(qty) {
        var band = BANDS[0];
        for (var i = 0; i < BANDS.length; i++) {
            if (qty >= BANDS[i]) band = BANDS[i];
        }
        return band;
    }

    function bandLabel(start, i) {
        var next = BANDS[i + 1];
        if (!next) return start + '+';
        return next - 1 === start ? String(start) : start + '–' + (next - 1);
    }

    function render(el) {
        var cfg = readConfig(el);
        var qty = cfg.quantity;
        var unit = unitAt(cfg, qty);
        var active = bandFor(qty);

        var rows = '';
        for (var i = 0; i < BANDS.length; i++) {
            var start = BANDS[i];
            var isActive = start === active;
            rows += '<tr class="price-tier-row' + (isActive ? ' is-active' : '') + '">'
                  + '<td>' + bandLabel(start, i) + '</td>'
                  + '<td>' + money(unitAt(cfg, start)) + '</td>'
                  + '</tr>';
        }

        // What would the next band save on the CURRENT quantity's per-unit price?
        var hint = '';
        var idx = BANDS.indexOf(active);
        if (idx > -1 && idx < BANDS.length - 1) {
            var nextStart = BANDS[idx + 1];
            var nextUnit = unitAt(cfg, nextStart);
            var perUnitSaving = unit - nextUnit;
            if (perUnitSaving > 0.005) {
                hint = '<p class="price-tiers-hint">'
                     + t('pricing.tier_hint', 'Order {qty}+ and pay {price} each')
                         .replace('{qty}', nextStart).replace('{price}', money(nextUnit))
                     + ' <span class="price-tiers-save">'
                     + t('pricing.tier_save', 'save {amount} per item')
                         .replace('{amount}', money(perUnitSaving))
                     + '</span></p>';
            }
        }

        el.innerHTML =
            '<div class="price-tiers-head">'
          +   '<span class="price-tiers-unit">' + money(unit)
          +     '<small>' + t('pricing.each', 'each') + '</small></span>'
          +   '<span class="price-tiers-total">' + money(unit * qty)
          +     '<small>' + t('pricing.total_for', 'for {qty}').replace('{qty}', qty) + '</small></span>'
          + '</div>'
          + hint
          + '<details class="price-tiers-details">'
          +   '<summary>' + t('pricing.see_tiers', 'Volume pricing') + '</summary>'
          +   '<table class="price-tiers-table">'
          +     '<thead><tr><th>' + t('pricing.qty', 'Quantity') + '</th>'
          +     '<th>' + t('pricing.per_item', 'Per item') + '</th></tr></thead>'
          +     '<tbody>' + rows + '</tbody>'
          +   '</table>'
          + '</details>';
    }

    function update(el, patch) {
        if (!el) return;
        Object.keys(patch || {}).forEach(function (k) {
            var attr = 'data-' + k.replace(/[A-Z]/g, function (m) { return '-' + m.toLowerCase(); });
            el.setAttribute(attr, patch[k]);
        });
        render(el);
    }

    global.PriceTiers = {
        mount: render,
        mountAll: function (root) {
            (root || document).querySelectorAll('.price-tiers').forEach(render);
        },
        update: update,
        unitAt: function (el, qty) { return unitAt(readConfig(el), qty); }
    };

    document.addEventListener('DOMContentLoaded', function () {
        global.PriceTiers.mountAll();
    });
})(window);
