/**
 * Size × quantity picker for the add-to-cart pop-ups — premade designs, the
 * designer, and saved designs on the account page all use this one, so they
 * look and behave the same.
 *
 * One colour at a time. Every size of the product is listed with its own
 * stepper (sizes that colour doesn't come in are crossed out), and each size
 * with a quantity becomes its own cart line: a small and a medium are
 * different items. Lines are priced the way the cart prices them (the
 * variant's cost at that line's quantity tier — lib/pricing.js — plus the
 * print extras and any design fee).
 *
 * Markup: views/partials/size_qty_fields.php, inside the pop-up.
 *
 *   const picker = SizeQty.create(popupEl, { button: addBtn, error: errorEl });
 *   picker.configure({ productName, basePrice, extra, fee, note });
 *   picker.setData(apiResponse);   // /api/product-variants/{id}
 *   picker.setColor(colorId);      // clears the quantities
 *   picker.addToCart(line => payload, (line, data) => { … per line added … })
 *       .then(allAdded => …);
 */
(function (global) {
    'use strict';

    var MAX_QTY = 99;

    function tr(key, params, fallback) {
        if (!global.I18N) return fallback;
        var s = global.I18N.t(key, params);
        return s && s !== key ? s : fallback;
    }

    function money(n) { return '€' + n.toFixed(2); }

    function create(root, opts) {
        opts = opts || {};
        var grid    = root.querySelector('[data-sq-grid]');
        var linesEl = root.querySelector('[data-sq-lines]');
        var labelEl = root.querySelector('[data-sq-total-label]');
        var totalEl = root.querySelector('[data-sq-total]');
        var noteEl  = root.querySelector('[data-sq-note]');
        var button  = opts.button || null;
        var errorEl = opts.error || null;

        var state = {
            variants: [], sizes: [], colorId: null, qty: {},
            productName: '', basePrice: 0, extra: 0, fee: 0
        };

        function variantFor(sizeId) {
            return state.variants.find(function (v) {
                return v.size_id == sizeId && v.color_id == state.colorId && v.is_available == 1;
            }) || null;
        }

        function render() {
            grid.innerHTML = '';
            // One column per size: the wide pop-up puts them all on one row.
            grid.style.setProperty('--sq-cols', String(Math.max(1, state.sizes.length)));
            state.sizes.forEach(function (size) {
                var available = !!variantFor(size.id);
                var qty = state.qty[size.id] || 0;
                var cell = document.createElement('div');
                cell.className = 'size-qty-cell' + (available ? '' : ' is-unavailable') + (qty > 0 ? ' has-qty' : '');
                cell.dataset.sizeId = size.id;

                var label = document.createElement('span');
                label.className = 'size-qty-label';
                label.textContent = size.name;

                var stepper = document.createElement('div');
                stepper.className = 'qty-stepper';
                var minus = document.createElement('button');
                minus.type = 'button';
                minus.dataset.step = '-1';
                minus.textContent = '−';
                minus.setAttribute('aria-label', tr('size_qty.fewer', { size: size.name }, 'Fewer ' + size.name));
                minus.disabled = !available || qty <= 0;
                var input = document.createElement('input');
                input.type = 'number';
                input.min = '0';
                input.max = String(MAX_QTY);
                input.inputMode = 'numeric';
                input.value = String(qty);
                input.disabled = !available;
                input.setAttribute('aria-label', tr('size_qty.qty_for', { size: size.name }, size.name + ' quantity'));
                var plus = document.createElement('button');
                plus.type = 'button';
                plus.dataset.step = '1';
                plus.textContent = '+';
                plus.setAttribute('aria-label', tr('size_qty.more', { size: size.name }, 'More ' + size.name));
                plus.disabled = !available || qty >= MAX_QTY;
                stepper.appendChild(minus);
                stepper.appendChild(input);
                stepper.appendChild(plus);

                cell.appendChild(label);
                cell.appendChild(stepper);
                if (!available) {
                    var na = document.createElement('span');
                    na.className = 'size-qty-na';
                    na.textContent = tr('size_qty.unavailable', null, 'Not available');
                    cell.appendChild(na);
                }
                grid.appendChild(cell);
            });
            update();
        }

        function setQty(sizeId, qty) {
            qty = Math.max(0, Math.min(MAX_QTY, parseInt(qty, 10) || 0));
            state.qty[sizeId] = qty;
            var cell = grid.querySelector('.size-qty-cell[data-size-id="' + sizeId + '"]');
            if (cell) {
                cell.classList.toggle('has-qty', qty > 0);
                var input = cell.querySelector('input');
                if (input && document.activeElement !== input) input.value = String(qty);
                cell.querySelector('[data-step="-1"]').disabled = qty <= 0;
                cell.querySelector('[data-step="1"]').disabled = qty >= MAX_QTY;
            }
            hideError();
            update();
        }

        // The sizes with a quantity, in size order, priced as the cart prices
        // each line.
        // A size's price per item at a quantity: the variant's cost at that
        // quantity's tier (as the cart prices each line), plus the extras.
        function unitFor(sizeId, qty) {
            var category = global.Pricing ? global.Pricing.categoryFor('', state.productName) : 'tshirt';
            var cost = global.Pricing
                ? global.Pricing.variantCost(state.variants, sizeId, state.colorId, state.basePrice)
                : state.basePrice;
            return (global.Pricing ? global.Pricing.unitPrice(cost, category, qty, state.extra) : cost + state.extra) + state.fee;
        }

        // The sizes with a quantity, in size order, priced as the cart prices
        // each line.
        function lines() {
            return state.sizes
                .filter(function (size) { return (state.qty[size.id] || 0) > 0 && variantFor(size.id); })
                .map(function (size) {
                    var qty = state.qty[size.id];
                    var unit = unitFor(size.id, qty);
                    return { sizeId: size.id, name: size.name, qty: qty, unit: unit, total: unit * qty };
                });
        }

        // Sizes grouped by price per item, in size order.
        function byPrice(items) {
            var groups = [];
            items.forEach(function (it) {
                var key = it.unit.toFixed(2);
                var g = groups.find(function (x) { return x.key === key; });
                if (!g) { g = { key: key, unit: it.unit, names: [] }; groups.push(g); }
                g.names.push(it.name);
            });
            return groups;
        }

        function priceRow(left, right) {
            var row = document.createElement('div');
            row.className = 'popup-price-row';
            var l = document.createElement('span');
            l.textContent = left;
            var r = document.createElement('span');
            r.textContent = right;
            row.appendChild(l);
            row.appendChild(r);
            linesEl.appendChild(row);
        }

        // The price per item, once. The grid above already shows what was
        // picked, so sizes are named only when they cost different amounts
        // (a 3XL costing more, or a bigger quantity of one size reaching a
        // cheaper tier).
        function update() {
            var ls = lines();
            linesEl.innerHTML = '';
            var perItem = tr('size_qty.per_item', null, 'Price per item');
            if (ls.length) {
                var groups = byPrice(ls);
                if (groups.length === 1) {
                    priceRow(perItem, money(groups[0].unit));
                } else {
                    groups.forEach(function (g) {
                        priceRow(g.names.join(', '), tr('size_qty.each', { price: money(g.unit) }, money(g.unit) + ' each'));
                    });
                }
            } else {
                // Nothing picked yet: the price of one, from the cheapest size.
                var offered = state.sizes.filter(function (size) { return variantFor(size.id); })
                    .map(function (size) { return unitFor(size.id, 1); });
                if (offered.length) {
                    var lo = Math.min.apply(null, offered), hi = Math.max.apply(null, offered);
                    priceRow(perItem, hi - lo > 0.005 ? tr('size_qty.from', { price: money(lo) }, 'from ' + money(lo)) : money(lo));
                }
            }
            var items = ls.reduce(function (n, l) { return n + l.qty; }, 0);
            var total = ls.reduce(function (sum, l) { return sum + l.total; }, 0);
            labelEl.textContent = items > 1
                ? tr('size_qty.total_qty', { qty: items }, 'Total (' + items + ' items)')
                : tr('size_qty.total', null, 'Total');
            totalEl.textContent = money(total);
            if (button) button.disabled = items === 0;
        }

        function showError(msg) {
            if (!errorEl) { if (global.UI) global.UI.error(msg); return; }
            errorEl.textContent = msg;
            errorEl.style.display = 'block';
        }
        function hideError() { if (errorEl) errorEl.style.display = 'none'; }

        grid.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-step]');
            if (!btn || btn.disabled) return;
            var sizeId = btn.closest('.size-qty-cell').dataset.sizeId;
            setQty(sizeId, (state.qty[sizeId] || 0) + parseInt(btn.dataset.step, 10));
        });
        grid.addEventListener('input', function (e) {
            if (e.target.tagName !== 'INPUT') return;
            setQty(e.target.closest('.size-qty-cell').dataset.sizeId, e.target.value);
        });
        grid.addEventListener('change', function (e) {
            if (e.target.tagName !== 'INPUT') return;
            e.target.value = String(state.qty[e.target.closest('.size-qty-cell').dataset.sizeId] || 0);
        });

        return {
            configure: function (o) {
                state.productName = o.productName || '';
                state.basePrice = parseFloat(o.basePrice) || 0;
                state.extra = parseFloat(o.extra) || 0;
                state.fee = parseFloat(o.fee) || 0;
                if (noteEl) {
                    noteEl.textContent = o.note || '';
                    noteEl.hidden = !o.note;
                }
                state.variants = [];
                state.sizes = [];
                state.qty = {};
                grid.innerHTML = '';
                hideError();
                update();
            },
            setData: function (data) {
                state.variants = (data && data.variants) || [];
                state.sizes = (data && data.sizes) || [];
                render();
            },
            setColor: function (colorId) {
                state.colorId = colorId;
                state.qty = {};
                render();
            },
            lines: lines,
            showError: showError,

            /**
             * One cart line per size, one request after another so a failure
             * is reported against its size. Sizes that went in are cleared
             * from the grid, so pressing the button again only retries the
             * rest. Resolves true when every line was added.
             */
            addToCart: async function (payloadFor, onAdded) {
                var ls = lines();
                if (!ls.length) { showError(tr('size_qty.pick_sizes', null, 'Choose how many of each size you want.')); return false; }
                if (button && global.UI) global.UI.loading(button, true);
                var failed = [];
                for (var i = 0; i < ls.length; i++) {
                    var line = ls[i];
                    var payload = payloadFor(line);
                    try {
                        var r = await fetch('/cart/add', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify(payload)
                        });
                        var data = await r.json().catch(function () { return {}; });
                        if (data.requireLogin && global.redirectToLoginWithPendingCart) {
                            global.redirectToLoginWithPendingCart(payload);
                            return false;
                        }
                        if (r.ok && (data.success || data.cart_item_id)) {
                            state.qty[line.sizeId] = 0;
                            if (onAdded) onAdded(line, data);
                        } else {
                            failed.push(line.name + ': ' + (data.error || tr('studio.cart.error_generic', null, 'Failed to add to cart.')));
                        }
                    } catch (e) {
                        failed.push(line.name + ': ' + tr('studio.cart.error_generic', null, 'Failed to add to cart.'));
                    }
                }
                if (button && global.UI) global.UI.loading(button, false);
                render();
                if (!failed.length) return true;
                showError((failed.length < ls.length ? tr('size_qty.partial', null, 'The other sizes were added. Not added:') + ' ' : '') + failed.join(' · '));
                return false;
            }
        };
    }

    /** "● Color: Black" — the colour a pop-up is locked to. */
    function colorLine(el, hex, name) {
        el.textContent = '';
        var dot = document.createElement('span');
        dot.className = 'popup-color-dot';
        dot.style.backgroundColor = hex || '#fff';
        el.appendChild(dot);
        el.appendChild(document.createTextNode(tr('cart.item.color', null, 'Color') + ': ' + (name || '')));
    }

    global.SizeQty = { create: create, colorLine: colorLine };
})(window);
