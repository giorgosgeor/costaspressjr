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

    // Per-unit garment price at a quantity's tier — the spec formula:
    //   PRICE = (SUPPLIER + ERROR + COST_OF_PRINT) / (1 - PROFIT_MARGIN)
    // Flat across a whole band, because the margin is flat across it.
    function rawGarmentUnit(supplierCost, category, quantity) {
        var margin = marginFor(category, quantity);
        return ((parseFloat(supplierCost) || 0) + ERROR + COST_OF_PRINT) / (1 - margin);
    }

    // Spec has no total cap, so there is none here. A quantity at the top of a
    // band can therefore cost more in total than the first quantity of the next
    // band; price-tiers.js surfaces a "order N+ and pay X each" prompt so the
    // customer can see that, without changing what is charged.
    /**
     * @param {number} extraPrintCost flat euro total of the print add-ons
     *        (front+back 3, each sleeve 1). Same 4th parameter as
     *        Pricing::unitPrice() in PHP.
     *
     * This used to take (frontAndBack, sleeves) instead, but every caller was
     * already passing the flat euro total into the boolean slot — so one sleeve
     * (1.00) and two sleeves (2.00) both silently billed as 3.00, and
     * front+back plus a sleeve (4.00) billed as 3.00. The quoted price then
     * disagreed with what the server charged.
     */
    function unitPrice(supplierCost, category, quantity, extraPrintCost) {
        var qty = Math.max(1, quantity | 0);
        var price = rawGarmentUnit(supplierCost, category, qty)
                  + Math.max(0, parseFloat(extraPrintCost) || 0);
        return Math.round(price * 100) / 100;
    }

    /** Flat euro cost of the print add-ons. Mirrors Pricing::printExtraCost(). */
    function printExtraCost(frontAndBack, sleeves) {
        return (frontAndBack ? FRONT_AND_BACK_EXTRA : 0)
             + Math.max(0, sleeves || 0) * SLEEVE_EXTRA;
    }

    global.Pricing = {
        ERROR: ERROR,
        COST_OF_PRINT: COST_OF_PRINT,
        FRONT_AND_BACK_EXTRA: FRONT_AND_BACK_EXTRA,
        SLEEVE_EXTRA: SLEEVE_EXTRA,
        categoryFor: categoryFor,
        marginFor: marginFor,
        unitPrice: unitPrice,
        printExtraCost: printExtraCost
    };
})(window);
