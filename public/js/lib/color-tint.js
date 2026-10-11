/**
 * Garment colour: the single source of truth. app/services/Tint.php is its
 * PHP twin and must stay in step: same constants, same formula.
 *
 * Every product photo is a neutral grey garment, which makes it a map of
 * light and shadow. A colour is laid onto it with one lookup table per
 * channel, from the photo's grey to the garment's colour. The work is done in
 * OKLab, a colour space where lightness and hue are independent, so a fold
 * makes a garment darker or lighter without turning it another colour:
 *
 *  - The photo's midtone becomes the colour itself, so a navy shirt is navy
 *    and black is black, not dark grey. (The CSS filter chains this replaces,
 *    sepia + hue-rotate + saturate + brightness, could only shift the grey
 *    photo, so dark colours came out grey and others looked like a coloured
 *    highlight over it.)
 *  - The photo's folds and seams carry over as differences in lightness, the
 *    same depth for every colour, as in real product photography, where dark
 *    garments are lit to show their folds. On strongly saturated colours the
 *    highlights lift half as much: real red cotton stays red where the light
 *    falls, it doesn't go pink.
 *  - Chroma follows lightness toward black and white, as real fabric does,
 *    and no fabric is quite black or white. A shade the screen can't show
 *    loses chroma, never hue.
 *  - A colour more saturated than any dyed fabric (a pure screen red, #ff0000)
 *    is shown a little deeper, the way such a garment photographs.
 *  - The brightest pixels, the white neck label and glints, keep the photo's
 *    own grey instead of being coloured.
 *
 * Pages use filterFor(hex): it adds an SVG <filter> holding the tables to the
 * page (once per colour) and returns "url(#tint-rrggbb)" for CSS. Canvases
 * use drawImage(), which applies the same tables to the pixels, since Safari
 * has no canvas filters.
 */
(function (global) {
    'use strict';

    var SAMPLES = 64;           // points per table; the browser interpolates between them
    var FOLD_DEPTH = 0.85;      // share of the photo's lightness contrast carried over
    var L_MIN = 0.19, L_MAX = 0.96;     // darkest and lightest fabric (OKLab L; L* 6 and 95)
    var MID_GREY = 0.46;        // the grey photos' midtone (sRGB)
    var KEEP_FROM = 0.83, KEEP_TO = 0.93;   // lightness over which the photo's own grey takes over
    var SOFT_LIFT = 0.5;        // how much less the highlights lift on the most saturated colours
    var SAT_FULL = 0.25;        // OKLab chroma counted as fully saturated (pure red is 0.26)
    var DYE_CHROMA = 0.22;      // chroma beyond what dyed fabric reaches...
    var DEEPEN = 0.06;          // ...is shown up to this much darker

    function normalizeHex(hex) {
        hex = String(hex || '').trim().toLowerCase();
        if (hex.charAt(0) !== '#') hex = '#' + hex;
        if (/^#[0-9a-f]{3}$/.test(hex)) {
            hex = '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3];
        }
        return /^#[0-9a-f]{6}$/.test(hex) ? hex : '';
    }

    function toLinear(c) { return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); }
    function toSrgb(c) {
        c = Math.max(0, Math.min(1, c));
        return c <= 0.0031308 ? c * 12.92 : 1.055 * Math.pow(c, 1 / 2.4) - 0.055;
    }

    /** sRGB 0..1 to OKLab. (Math.pow rather than Math.cbrt, to match PHP's **.) */
    function rgbToOklab(r, g, b) {
        r = toLinear(r); g = toLinear(g); b = toLinear(b);
        var l = Math.pow(0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b, 1 / 3);
        var m = Math.pow(0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b, 1 / 3);
        var s = Math.pow(0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b, 1 / 3);
        return [
            0.2104542553 * l + 0.7936177850 * m - 0.0040720468 * s,
            1.9779984951 * l - 2.4285922050 * m + 0.4505937099 * s,
            0.0259040371 * l + 0.7827717662 * m - 0.8086757660 * s
        ];
    }

    /** OKLab to linear sRGB, unclamped. */
    function oklabToLinear(l, a, b) {
        var lc = Math.pow(l + 0.3963377774 * a + 0.2158037573 * b, 3);
        var mc = Math.pow(l - 0.1055613458 * a - 0.0638541728 * b, 3);
        var sc = Math.pow(l - 0.0894841775 * a - 1.2914855480 * b, 3);
        return [
             4.0767416621 * lc - 3.3077115913 * mc + 0.2309699292 * sc,
            -1.2684380046 * lc + 2.6097574011 * mc - 0.3413193965 * sc,
            -0.0041960863 * lc - 0.7034186147 * mc + 1.7076147010 * sc
        ];
    }

    function fits(c) { return Math.min(c[0], c[1], c[2]) >= -1e-6 && Math.max(c[0], c[1], c[2]) <= 1 + 1e-6; }

    /**
     * OKLab to sRGB 0..1. A colour the screen can't show loses chroma, keeping
     * its lightness and hue, until it fits (clipping each channel shifted hue:
     * red highlights went salmon).
     */
    function oklabToRgb(l, a, b) {
        var rgb = oklabToLinear(l, a, b);
        if (!fits(rgb)) {
            var lo = 0, hi = 1;
            for (var i = 0; i < 16; i++) {
                var mid = (lo + hi) / 2;
                if (fits(oklabToLinear(l, a * mid, b * mid))) lo = mid; else hi = mid;
            }
            rgb = oklabToLinear(l, a * lo, b * lo);
        }
        return [toSrgb(rgb[0]), toSrgb(rgb[1]), toSrgb(rgb[2])];
    }

    var cache = {};

    /** The three tables for a colour: [r[], g[], b[]], SAMPLES values each, 0..1. */
    function tables(hex) {
        hex = normalizeHex(hex);
        if (!hex) return null;
        if (cache[hex]) return cache[hex];
        var lab = rgbToOklab(parseInt(hex.substr(1, 2), 16) / 255, parseInt(hex.substr(3, 2), 16) / 255, parseInt(hex.substr(5, 2), 16) / 255);
        var c = Math.sqrt(lab[1] * lab[1] + lab[2] * lab[2]);
        var sat = Math.min(1, c / SAT_FULL);
        var x = Math.max(0, Math.min(1, (c - DYE_CHROMA) / (SAT_FULL - DYE_CHROMA)));
        var lA = Math.max(L_MIN, Math.min(L_MAX, lab[0])) - DEEPEN * x * x * (3 - 2 * x);
        var lMid = rgbToOklab(MID_GREY, MID_GREY, MID_GREY)[0];
        var out = [[], [], []];
        for (var i = 0; i < SAMPLES; i++) {
            var grey = i / (SAMPLES - 1);
            var lGrey = rgbToOklab(grey, grey, grey)[0];
            var d = FOLD_DEPTH * (lGrey - lMid);
            if (d > 0) d *= 1 - SOFT_LIFT * sat;
            var l = Math.max(0, Math.min(1, lA + d));
            var chroma = l < lA ? l / lA : (1 - l) / (1 - lA);
            var rgb = oklabToRgb(l, lab[1] * chroma, lab[2] * chroma);
            var k = Math.max(0, Math.min(1, (lGrey - KEEP_FROM) / (KEEP_TO - KEEP_FROM)));
            var keep = k * k * (3 - 2 * k);
            for (var ch = 0; ch < 3; ch++) {
                out[ch].push(Math.round((rgb[ch] * (1 - keep) + grey * keep) * 10000) / 10000);
            }
        }
        cache[hex] = out;
        return out;
    }

    var SVG = 'http://www.w3.org/2000/svg';

    /** CSS filter for a garment in this colour; "none" for anything that isn't a colour. */
    function filterFor(hex) {
        hex = normalizeHex(hex);
        if (!hex) return 'none';
        var id = 'tint-' + hex.slice(1);
        if (!document.getElementById(id)) {
            // Server-rendered pages may already hold some (Tint::svgDefs).
            var defs = document.getElementById('tint-defs');
            if (!defs) {
                var svg = document.createElementNS(SVG, 'svg');
                svg.setAttribute('width', '0');
                svg.setAttribute('height', '0');
                svg.setAttribute('aria-hidden', 'true');
                svg.style.position = 'absolute';   // not display:none, which some browsers skip filters in
                defs = document.createElementNS(SVG, 'defs');
                defs.id = 'tint-defs';
                svg.appendChild(defs);
                document.body.appendChild(svg);
            }
            var filter = document.createElementNS(SVG, 'filter');
            filter.id = id;
            filter.setAttribute('color-interpolation-filters', 'sRGB');
            var transfer = document.createElementNS(SVG, 'feComponentTransfer');
            var t = tables(hex);
            ['feFuncR', 'feFuncG', 'feFuncB'].forEach(function (name, c) {
                var fn = document.createElementNS(SVG, name);
                fn.setAttribute('type', 'table');
                fn.setAttribute('tableValues', t[c].join(' '));
                transfer.appendChild(fn);
            });
            filter.appendChild(transfer);
            defs.appendChild(filter);
        }
        return 'url(#' + id + ')';
    }

    /** ctx.drawImage(img, x, y, w, h) with the garment in this colour (none: as it is). */
    function drawImage(ctx, img, x, y, w, h, hex) {
        var t = tables(hex);
        w = Math.max(1, Math.round(w));
        h = Math.max(1, Math.round(h));
        if (!t) { ctx.drawImage(img, x, y, w, h); return; }
        var off = document.createElement('canvas');
        off.width = w;
        off.height = h;
        var octx = off.getContext('2d');
        octx.drawImage(img, 0, 0, w, h);
        var data = octx.getImageData(0, 0, w, h);
        // The tables expanded to every 8-bit value, interpolating like the browser.
        var maps = t.map(function (table) {
            var m = new Uint8ClampedArray(256), last = table.length - 1;
            for (var v = 0; v < 256; v++) {
                var pos = v / 255 * last, k = Math.min(Math.floor(pos), last - 1), f = pos - k;
                m[v] = Math.round((table[k] + (table[k + 1] - table[k]) * f) * 255);
            }
            return m;
        });
        var px = data.data;
        for (var i = 0; i < px.length; i += 4) {
            px[i] = maps[0][px[i]];
            px[i + 1] = maps[1][px[i + 1]];
            px[i + 2] = maps[2][px[i + 2]];
        }
        octx.putImageData(data, 0, 0);
        ctx.drawImage(off, x, y);
    }

    global.CostasTint = { filterFor: filterFor, drawImage: drawImage, tables: tables, normalizeHex: normalizeHex };
})(window);
