/* ============================================================
   STUDIO STATE PERSISTENCE
   Saves the design-studio state (selected product, color, size,
   view, design elements per view) to localStorage so it survives
   page refreshes — including the /lang/en /lang/el redirect.
   ============================================================ */
(function () {
    var KEY = "studio_state_v1";
    var saveTimer = null;
    var ready = false;

    function deepClone(obj) {
        try { return JSON.parse(JSON.stringify(obj)); } catch (e) { return null; }
    }

    function getSelectedSize() {
        if (!window.currentProduct) return null;
        var panel = document.getElementById("options-" + window.currentProduct.id);
        if (!panel) return null;
        var radio = panel.querySelector('input[type=radio][name^="size_"]:checked');
        return radio ? radio.value : null;
    }

    function setSelectedSize(sizeId) {
        if (!sizeId || !window.currentProduct) return;
        var panel = document.getElementById("options-" + window.currentProduct.id);
        if (!panel) return;
        var radio = panel.querySelector(
            'input[type=radio][name^="size_"][value="' + sizeId + '"]'
        );
        if (radio) {
            radio.checked = true;
            try { radio.dispatchEvent(new Event("change", { bubbles: true })); } catch (e) {}
        }
    }

    function trySetItem(value) {
        try { localStorage.setItem(KEY, value); return true; }
        catch (e) { return false; }
    }

    window.saveStudioState = function () {
        if (!ready) return;
        if (!window.currentProduct) return;
        var snapshot = {
            v: 1,
            ts: Date.now(),
            productId: window.currentProduct.id,
            view: window.currentView || (typeof currentView !== "undefined" ? currentView : "front"),
            colorHex: window.currentColorHex || (typeof currentColorHex !== "undefined" ? currentColorHex : "#ffffff"),
            studioColorId: window.studioSelectedColorId || null,
            sizeId: getSelectedSize(),
            elements: deepClone(window.designElements || (typeof elements !== "undefined" ? elements : {})),
            counter: window.elementIdCounter || (typeof elementIdCounter !== "undefined" ? elementIdCounter : 0)
        };

        var payload = JSON.stringify(snapshot);
        if (trySetItem(payload)) return;

        // Quota exceeded — strip image data URLs (the heaviest part) and retry.
        if (snapshot.elements) {
            Object.keys(snapshot.elements).forEach(function (k) {
                (snapshot.elements[k] || []).forEach(function (el) {
                    if (el && el.type === "image" && el.src
                        && String(el.src).indexOf("data:") === 0) {
                        el._stripped = true;
                        delete el.src;
                    }
                });
            });
        }
        trySetItem(JSON.stringify(snapshot));
    };

    window.scheduleStudioStateSave = function () {
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(window.saveStudioState, 250);
    };

    window.clearStudioState = function () {
        try { localStorage.removeItem(KEY); } catch (e) {}
    };

    function restoreStudioState() {
        var raw;
        try { raw = localStorage.getItem(KEY); } catch (e) { return false; }
        if (!raw) return false;

        var s;
        try { s = JSON.parse(raw); } catch (e) { return false; }
        if (!s || s.v !== 1 || !s.productId) return false;

        var card = document.querySelector(
            '.product-choice[data-product-id="' + s.productId + '"]'
        );
        if (!card) return false;

        try {
            if (typeof selectProduct === "function") selectProduct(card);
        } catch (e) { return false; }

        // Restore view
        if (s.view && typeof switchView === "function") {
            try { switchView(s.view); } catch (e) {}
        }

        // Restore size
        if (s.sizeId) setSelectedSize(s.sizeId);

        // Restore color tint (so the mockup looks right immediately)
        if (s.colorHex && typeof applyColorTint === "function") {
            try { applyColorTint(s.colorHex); } catch (e) {}
        }

        // Restore studio color swatch (the panel may fetch variants asynchronously)
        if (s.studioColorId) {
            setTimeout(function () {
                var sw = document.querySelector(
                    '.color-swatch[data-color-id="' + s.studioColorId + '"]'
                );
                if (sw) {
                    sw.click();
                } else {
                    setTimeout(function () {
                        var sw2 = document.querySelector(
                            '.color-swatch[data-color-id="' + s.studioColorId + '"]'
                        );
                        if (sw2) sw2.click();
                    }, 600);
                }
            }, 250);
        }

        // Restore design elements per view
        var target = window.designElements
            || (typeof elements !== "undefined" ? elements : null);
        if (s.elements && target) {
            ["front", "back", "left-sleeve", "right-sleeve"].forEach(function (k) {
                target[k] = Array.isArray(s.elements[k]) ? s.elements[k] : [];
            });

            // Bump element id counter past any restored elements
            var maxId = 0;
            Object.keys(target).forEach(function (k) {
                (target[k] || []).forEach(function (el) {
                    var n = parseInt(String(el && el.id || "").replace(/[^0-9]/g, ""), 10);
                    if (!isNaN(n) && n > maxId) maxId = n;
                });
            });
            try {
                if (typeof window.elementIdCounter !== "undefined") {
                    window.elementIdCounter = Math.max(
                        window.elementIdCounter || 0,
                        s.counter || 0,
                        maxId + 1
                    );
                }
            } catch (e) {}

            if (typeof renderElements === "function") {
                try { renderElements(); } catch (e) {}
            }
            if (typeof updateLayerList === "function") {
                try { updateLayerList(); } catch (e) {}
            }
            if (typeof updateSummary === "function") {
                try { updateSummary(); } catch (e) {}
            }
        }

        return true;
    }

    // The existing init runs `selectProduct(firstProduct)` on DOMContentLoaded.
    // We attach a later listener; DOMContentLoaded listeners fire in registration
    // order, so this runs AFTER the default setup.
    document.addEventListener("DOMContentLoaded", function () {
        setTimeout(function () {
            ready = true;
            try { restoreStudioState(); } catch (e) {}

            // Auto-save on user interaction inside the studio
            var root = document.querySelector(".custom-studio-layout") || document.body;
            root.addEventListener("click", window.scheduleStudioStateSave, true);
            root.addEventListener("change", window.scheduleStudioStateSave, true);
            root.addEventListener("input", window.scheduleStudioStateSave, true);

            // Catch design-element drag/resize/text edits ending anywhere
            document.addEventListener("mouseup", window.scheduleStudioStateSave, true);
            document.addEventListener("touchend", window.scheduleStudioStateSave, true);
        }, 50);
    });

    // Save before the page unloads — covers the /lang/ redirect and refreshes
    window.addEventListener("beforeunload", function () {
        try { window.saveStudioState(); } catch (e) {}
    });
    window.addEventListener("pagehide", function () {
        try { window.saveStudioState(); } catch (e) {}
    });
})();
