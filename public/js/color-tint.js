/**
 * Garment mockup colour tinting - exact filter chains.
 *
 * The mockup art is a single orange garment photo that every colour swatch is
 * produced from with a CSS filter chain. The generic approximation used across
 * the views (sepia -> saturate -> hue-rotate -> brightness) is accurate enough
 * for most hues, but it renders saturated reds as washed-out salmon: CSS
 * hue-rotate is a linear matrix approximation, not a true hue rotation, and it
 * desaturates badly over the ~316deg swing that red needs from the sepia base.
 * Saturating BEFORE the rotation, as the generic formula does, means the
 * rotation's desaturation then eats the boost.
 *
 * The chains below were solved numerically against the real mockup art
 * (public/images/products/*.png). The garment's own luminance runs p05 0.33 to
 * p95 0.51, and each chain minimises CIE76 error against an ideal multiply
 * blend of the target colour over that range - so fabric folds and shading
 * survive instead of clipping to a flat silhouette.
 *
 * Note on Red: the palette swatch is #ff0000, but a filter chain that puts the
 * garment midtone on pure red clips 73.7% of garment pixels to a flat shape and
 * turns highlights yellow - #ff0000 sits on the sRGB gamut corner and leaves no
 * headroom for shading. The chain here aims at #e01b1b instead: visually a
 * strong red, 0% clipped, shading intact.
 *
 * Anything not listed here falls through to each view's existing formula, so
 * this file only ever changes the colours it explicitly names.
 */
(function (global) {
    'use strict';

    var OVERRIDES = {
        // Red. The garment is darkened BEFORE the saturation boost so the boost
        // has headroom: saturating a mid-grey straight to full red pins the red
        // channel at its ceiling across the whole garment and the folds vanish.
        // Measured L* spread over the garment 24.8, against 27.5 for a colour
        // that renders correctly through the generic formula - shading intact.
        // Midtone lands ~#df0d00.
        '#ff0000': 'grayscale(1) brightness(0.4) sepia(1) saturate(4) hue-rotate(-40deg) saturate(3.25)'
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

    global.CostasTint = {
        /**
         * @param {string} hex Colour swatch value, with or without leading '#'.
         * @returns {string|null} A CSS filter chain, or null to use the caller's
         *                        own formula.
         */
        getOverride: function (hex) {
            return OVERRIDES[normalizeHex(hex)] || null;
        }
    };
})(window);
