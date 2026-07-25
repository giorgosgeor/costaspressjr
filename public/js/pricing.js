/**
 * Client-side mirror of app/core/Pricing.php.
 *
 * Used ONLY for live price previews in the customizer/cart. The server
 * (Pricing.php + CustomerController) remains authoritative for what is
 * actually charged — keep the tier tables and formula here in sync with the
 * PHP version.
 *
 *   cost   = supplierCost + ERROR + COST_OF_PRINT
 *   retail = cost / (1 - margin[category][quantityTier])
 *   retail += 3   if printed front AND back
 *   retail += 1   per printed sleeve
 */
(function (global) {
    'use strict';

    var ERROR = 1.0;
    var COST_OF_PRINT = 1.0;
    var FRONT_AND_BACK_EXTRA = 3.0;
    var SLEEVE_EXTRA = 1.0;

    // [minQty, maxQty, margin]. Infinity for the open-ended top tier.
    var TIERS = {
        tshirt: [
            [0, 4, 0.70], [5, 14, 0.65], [15, 29, 0.60],
            [30, 49, 0.54], [50, 99, 0.50], [100, Infinity, 0.44]
        ],
        hoodie: [
            [0, 4, 0.45], [5, 14, 0.42], [15, 29, 0.38],
            [30, 49, 0.35], [50, 99, 0.30], [100, Infinity, 0.25]
        ]
    };

    function categoryFor(slug, name) {
        var haystack = ((slug || '') + ' ' + (name || '')).toLowerCase();
        if (haystack.indexOf('hoodie') !== -1) return 'hoodie';
        return 'tshirt';
    }

    function marginFor(category, quantity) {
        var tiers = TIERS[category] || TIERS.tshirt;
        var qty = Math.max(1, quantity | 0);
        for (var i = 0; i < tiers.length; i++) {
            if (qty >= tiers[i][0] && qty <= tiers[i][1]) return tiers[i][2];
        }
        return tiers[tiers.length - 1][2];
    }

    // Raw (uncapped) per-unit garment price at a quantity's tier.
    function rawGarmentUnit(supplierCost, category, quantity) {
        var margin = marginFor(category, quantity);
        return ((parseFloat(supplierCost) || 0) + ERROR + COST_OF_PRINT) / (1 - margin);
    }

    // Garment total, guaranteed monotonic: a smaller order is never charged more
    // than a larger one (mirrors Pricing::garmentTotal in PHP).
    function garmentTotal(supplierCost, category, quantity) {
        var qty = Math.max(1, quantity | 0);
        var total = rawGarmentUnit(supplierCost, category, qty) * qty;
        var tiers = TIERS[category] || TIERS.tshirt;
        for (var i = 0; i < tiers.length; i++) {
            var start = tiers[i][0];
            if (start > qty) {
                var cand = rawGarmentUnit(supplierCost, category, start) * start;
                if (cand < total) total = cand;
            }
        }
        return total;
    }

    function unitPrice(supplierCost, category, quantity, frontAndBack, sleeves) {
        var qty = Math.max(1, quantity | 0);
        var price = garmentTotal(supplierCost, category, qty) / qty;
        if (frontAndBack) price += FRONT_AND_BACK_EXTRA;
        price += Math.max(0, sleeves || 0) * SLEEVE_EXTRA;
        return Math.round(price * 100) / 100;
    }

    global.Pricing = {
        ERROR: ERROR,
        COST_OF_PRINT: COST_OF_PRINT,
        FRONT_AND_BACK_EXTRA: FRONT_AND_BACK_EXTRA,
        SLEEVE_EXTRA: SLEEVE_EXTRA,
        categoryFor: categoryFor,
        marginFor: marginFor,
        unitPrice: unitPrice
    };
})(window);
