/**
 * Garment colour: the single source of truth. app/services/Tint.php is its
 * PHP twin and must stay in step: same constants, same formula.
 *
 * Every product photo is a neutral grey garment, which makes it a map of
 * light and shadow. A colour is laid onto it with one lookup table per
 * channel, from the photo's grey to the garment's colour:
 *
 *  - The photo's midtone becomes the colour itself, so a navy shirt is navy
 *    and black is black, not dark grey. (The CSS filter chains this replaces,
 *    sepia + hue-rotate + saturate + brightness, could only shift the grey
 *    photo, so dark colours came out grey and others looked like a coloured
 *    highlight over it.)
 *  - The photo's folds and seams carry over as differences in perceived
 *    lightness (CIE L*), the same depth for every colour, as in real product
 *    photography, where dark garments are lit to show their folds.
 *  - Chroma follows lightness toward black and white, as real fabric does,
 *    and no fabric is quite black or white (L* 6..95).
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
    var FOLD_DEPTH = 0.85;      // share of the photo's L* contrast carried over
    var L_MIN = 6, L_MAX = 95;  // darkest and lightest fabric
    var MID_GREY = 0.46;        // the grey photos' midtone (sRGB)
    var KEEP_FROM = 80, KEEP_TO = 92;   // L* range over which the photo's own grey takes over

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
    function labF(t) { return t > 216 / 24389 ? Math.cbrt(t) : (24389 / 27 * t + 16) / 116; }
    function labFInv(t) { var t3 = t * t * t; return t3 > 216 / 24389 ? t3 : (116 * t - 16) / (24389 / 27); }

    /** sRGB 0..1 to CIE Lab (D65). */
    function rgbToLab(r, g, b) {
        r = toLinear(r); g = toLinear(g); b = toLinear(b);
        var x = (0.4124 * r + 0.3576 * g + 0.1805 * b) / 0.95047;
        var y =  0.2126 * r + 0.7152 * g + 0.0722 * b;
        var z = (0.0193 * r + 0.1192 * g + 0.9505 * b) / 1.08883;
        return [116 * labF(y) - 16, 500 * (labF(x) - labF(y)), 200 * (labF(y) - labF(z))];
    }

    /** CIE Lab to sRGB 0..1, each channel clamped. */
    function labToRgb(l, a, b) {
        var fy = (l + 16) / 116, fx = fy + a / 500, fz = fy - b / 200;
        var x = 0.95047 * labFInv(fx), y = labFInv(fy), z = 1.08883 * labFInv(fz);
        return [
            toSrgb( 3.2406 * x - 1.5372 * y - 0.4986 * z),
            toSrgb(-0.9689 * x + 1.8758 * y + 0.0415 * z),
            toSrgb( 0.0557 * x - 0.2040 * y + 1.0570 * z)
        ];
    }

    var cache = {};

    /** The three tables for a colour: [r[], g[], b[]], SAMPLES values each, 0..1. */
    function tables(hex) {
        hex = normalizeHex(hex);
        if (!hex) return null;
        if (cache[hex]) return cache[hex];
        var lab = rgbToLab(parseInt(hex.substr(1, 2), 16) / 255, parseInt(hex.substr(3, 2), 16) / 255, parseInt(hex.substr(5, 2), 16) / 255);
        var lA = Math.max(L_MIN, Math.min(L_MAX, lab[0]));
        var lMid = rgbToLab(MID_GREY, MID_GREY, MID_GREY)[0];
        var out = [[], [], []];
        for (var i = 0; i < SAMPLES; i++) {
            var grey = i / (SAMPLES - 1);
            var lGrey = rgbToLab(grey, grey, grey)[0];
            var l = Math.max(0, Math.min(100, lA + FOLD_DEPTH * (lGrey - lMid)));
            var chroma = l < lA ? l / lA : (100 - l) / (100 - lA);
            var rgb = labToRgb(l, lab[1] * chroma, lab[2] * chroma);
            var x = Math.max(0, Math.min(1, (lGrey - KEEP_FROM) / (KEEP_TO - KEEP_FROM)));
            var keep = x * x * (3 - 2 * x);
            for (var c = 0; c < 3; c++) {
                out[c].push(Math.round((rgb[c] * (1 - keep) + grey * keep) * 10000) / 10000);
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
