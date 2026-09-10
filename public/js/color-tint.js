/**
 * Garment mockup colour tinting — the single source of truth.
 *
 * The mockup art is one orange garment photo that every colour swatch is
 * produced from with a CSS filter chain. `filterFor(hex)` returns the chain for
 * a swatch: a solved override when we have one, otherwise the generic formula.
 *
 * WHY A SINGLE FUNCTION: this logic used to be copy-pasted across account.js,
 * custom_product.js, shop_custom.js, view_design.js and three PHP views, and the
 * copies had drifted into three different formulas (hue-rotate h-38 vs h-50,
 * reddish saturate s*6+2 vs s*3+1, plus a `max(1, s/50)` variant). The same
 * garment tinted differently depending on which screen you were on, and fixes
 * applied to one copy silently missed the others. Everything now delegates here.
 *
 * WHY OVERRIDES EXIST: CSS hue-rotate is a linear matrix approximation, not a
 * true hue rotation, and each primitive clamps its result to [0,1]. Pushing
 * saturation hard enough to reach a deep red pins a channel at that ceiling
 * across the whole garment — and once a channel is pinned, every pixel shares
 * its value, so folds, collars and seams flatten into a silhouette.
 *
 * The chains below were solved numerically against the real mockup art
 * (public/images/products/80.png), maximising L* spread over garment pixels
 * subject to a CIE76 dE budget on the midtone. Reference: orange, which renders
 * correctly through the generic formula, measures L* spread 7.1.
 */
(function (global) {
    'use strict';

    var OVERRIDES = {
        // Red. Darkened BEFORE the saturation boost so the boost has headroom.
        // Generic formula: spread 2.1. This: 6.6. Midtone ~#df0d00.
        // Aims at #e01b1b rather than #ff0000 on purpose — pure red sits on the
        // sRGB gamut corner with no room left for shading.
        '#ff0000': 'grayscale(1) brightness(0.4) sepia(1) saturate(4) hue-rotate(-40deg) saturate(3.25)',

        // Maroon, the same failure but worse. The generic formula's reddish
        // branch asks for saturate(8.0); measured against the real mockup that
        // clamps red on 81.5% of garment pixels and blue on 100%, then the
        // hue-rotate clamps red on 99.3%. Result: L* spread 1.4 against orange's
        // 7.1 — the collar, placket, cuffs and folds disappear into a
        // silhouette. Darkening hard first, then splitting the boost across two
        // smaller saturates, keeps everything inside [0,1]: spread 4.8 (3.4x)
        // with the midtone at #951d12, dE 6.9 against the swatch — far closer
        // than the old chain managed (26.6) while looking properly dark.
        '#800000': 'grayscale(1) brightness(0.2852) sepia(1) saturate(3.565) hue-rotate(-43.55deg) saturate(2.086) brightness(1.358)'
    };

    function normalizeHex(hex) {
        if (!hex) return '';
        hex = String(hex).trim().toLowerCase();
        if (hex.charAt(0) !== '#') hex = '#' + hex;
        if (hex.length === 4) {
            hex = '#' + hex.charAt(1) + hex.charAt(1)
                      + hex.charAt(2) + hex.charAt(2)
                      + hex.charAt(3) + hex.charAt(3);
        }
        return hex;
    }

    function hexToHSL(hex) {
        hex = normalizeHex(hex).replace('#', '');
        var r = parseInt(hex.substring(0, 2), 16) / 255;
        var g = parseInt(hex.substring(2, 4), 16) / 255;
        var b = parseInt(hex.substring(4, 6), 16) / 255;
        var max = Math.max(r, g, b), min = Math.min(r, g, b);
        var h = 0, s = 0, l = (max + min) / 2;
        if (max !== min) {
            var d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            if (max === r)      h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
            else if (max === g) h = ((b - r) / d + 2) / 6;
            else                h = ((r - g) / d + 4) / 6;
        }
        return { h: h * 360, s: s * 100, l: l * 100 };
    }

    /**
     * Round to 4dp and drop trailing zeros. PHP and JS print floats to
     * different precisions ("2.36" vs "2.3599999999999994"), which is
     * harmless to CSS but makes it impossible to assert that the two
     * implementations agree. Rounding both the same way keeps them
     * byte-identical and testable.
     */
    function num(v) {
        return String(Math.round(v * 10000) / 10000);
    }

    /**
     * @param {string} hex Colour swatch value, with or without leading '#'.
     * @returns {string} A complete CSS filter chain.
     */
    function filterFor(hex) {
        if (!hex) return 'none';
        var norm = normalizeHex(hex);
        if (OVERRIDES[norm]) return OVERRIDES[norm];

        var hsl = hexToHSL(norm);
        if (norm === '#ffffff' || hsl.l > 95) return 'grayscale(1) brightness(2.2) contrast(0.85)';
        if (hsl.l < 10)                       return 'grayscale(1) brightness(0.45) contrast(1.2)';
        if (hsl.s < 10)                       return 'grayscale(1) brightness(' + num(0.2 + (hsl.l / 100) * 1.5) + ')';

        var hueRotate   = hsl.h - 38; // sepia base hue ~38deg
        var isReddish   = hsl.h <= 20 || hsl.h >= 340;
        var isYellowish = hsl.h >= 45 && hsl.h <= 80;

        var saturate = (hsl.s / 100) * 3 + 0.8;
        // Reds need a bigger swing from the sepia base, but this is the branch
        // that clamps: anything above ~4.5 pins the red channel on most of the
        // garment. Deep reds that still look wrong get a solved override above
        // rather than an ever-larger multiplier here.
        if (isReddish)   saturate = Math.min((hsl.s / 100) * 6 + 2.0, 4.5);
        if (isYellowish) saturate = (hsl.s / 100) * 4 + 1.0;

        var brightness;
        if (hsl.l < 30)      brightness = 0.3 + (hsl.l / 100) * 0.7;
        else if (hsl.l < 50) brightness = 0.5 + (hsl.l / 100) * 0.6;
        else                 brightness = 0.6 + (hsl.l / 100) * 0.5;
        if (isYellowish && hsl.l >= 45) brightness = Math.min(brightness * 1.25, 1.5);

        return 'grayscale(1) sepia(1) saturate(' + num(saturate) + ') hue-rotate(' + num(hueRotate) + 'deg) brightness(' + num(brightness) + ')';
    }

    global.CostasTint = {
        filterFor: filterFor,
        hexToHSL: hexToHSL,
        /**
         * Legacy shape: null when there is no solved override, so existing
         * callers that fall through to their own formula keep working.
         */
        getOverride: function (hex) {
            return OVERRIDES[normalizeHex(hex)] || null;
        }
    };
})(window);
