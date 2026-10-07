// Heart toggle for products and premade designs.
//
// The button reflects what the SERVER stored, not what was clicked — the state
// only flips once the response confirms it, so a failed request can't leave a
// filled heart with nothing saved behind it.
(function () {
    'use strict';

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function setState(btn, on) {
        btn.classList.toggle('is-favorited', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        var label = on ? btn.dataset.labelOn : btn.dataset.labelOff;
        if (label) {
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
        }
    }

    // Capture phase, deliberately. These hearts sit inside cards whose own click
    // handlers are bound to a container between the card and document — during
    // bubbling that container runs FIRST, so a bubble-phase listener here would
    // stop propagation too late and the card would still open. Capturing at
    // document level runs before any of them, on every page, without each card
    // needing to know the heart exists.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.fav-btn');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        if (btn.disabled) return;
        btn.disabled = true;

        fetch('/account/favorites/toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
            body: JSON.stringify({ kind: btn.dataset.kind, id: parseInt(btn.dataset.id, 10) })
        })
        .then(function (r) {
            // Saving is per-account, so a guest has to sign in first.
            if (r.status === 401) {
                window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
                return null;
            }
            return r.ok ? r.json() : Promise.reject(r.status);
        })
        .then(function (res) {
            if (res) setState(btn, !!res.favorited);
            btn.disabled = false;
        })
        .catch(function () { btn.disabled = false; });
    }, true);
})();
