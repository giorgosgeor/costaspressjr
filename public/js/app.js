// Main application JavaScript

// ── CSRF auto-attach ────────────────────────────────────────────────────────
// Pulls the token from <meta name="csrf-token"> and attaches it to every
// same-origin mutating request (POST/PUT/PATCH/DELETE) made via fetch() or
// XMLHttpRequest. Individual callers don't need to know about CSRF.
(function () {
    function token() {
        var el = document.querySelector('meta[name="csrf-token"]');
        return el ? el.getAttribute('content') : '';
    }
    window.getCsrfToken = token;

    var MUTATING = { POST: 1, PUT: 1, PATCH: 1, DELETE: 1 };

    function isSameOrigin(url) {
        try {
            if (!url) return true;
            if (typeof url !== 'string') url = String(url);
            if (url.indexOf('//') === -1 && url.indexOf('://') === -1) return true;
            var a = document.createElement('a');
            a.href = url;
            return a.origin === window.location.origin;
        } catch (e) { return true; }
    }

    var origFetch = window.fetch ? window.fetch.bind(window) : null;
    if (origFetch) {
        window.fetch = function (input, init) {
            init = init || {};
            var method = (init.method || (input && input.method) || 'GET').toUpperCase();
            var url = typeof input === 'string' ? input : (input && input.url) || '';
            if (MUTATING[method] && isSameOrigin(url)) {
                var headers = new Headers(init.headers || (input && input.headers) || {});
                if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token());
                init.headers = headers;
            }
            return origFetch(input, init);
        };
    }

    var origOpen = XMLHttpRequest.prototype.open;
    var origSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url) {
        this.__csrfMethod = (method || 'GET').toUpperCase();
        this.__csrfUrl = url;
        return origOpen.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function () {
        if (MUTATING[this.__csrfMethod] && isSameOrigin(this.__csrfUrl)) {
            try { this.setRequestHeader('X-CSRF-Token', token()); } catch (e) {}
        }
        return origSend.apply(this, arguments);
    };
})();

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const mainNav = document.querySelector('.main-nav');
    
    if (mobileMenuToggle && mainNav) {
        mobileMenuToggle.addEventListener('click', function() {
            const open = mainNav.classList.toggle('active');
            this.classList.toggle('active');
            this.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Confirm delete actions
    const deleteLinks = document.querySelectorAll('.delete-confirm');
    deleteLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            var msg = (window.I18N && window.I18N.t) ? window.I18N.t('common.confirm_delete') : 'Are you sure you want to delete this item?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // Form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let valid = true;
            let firstInvalid = null;

            requiredFields.forEach(function(field) {
                const isEmpty = !field.value.trim();
                field.classList.toggle('error', isEmpty);
                field.setAttribute('aria-invalid', isEmpty ? 'true' : 'false');
                if (isEmpty) {
                    valid = false;
                    if (!firstInvalid) firstInvalid = field;
                    var errId = field.id ? field.id + '-error' : null;
                    if (errId && !document.getElementById(errId)) {
                        var msg = document.createElement('span');
                        msg.id = errId;
                        msg.className = 'field-error-msg';
                        msg.setAttribute('role', 'alert');
                        var label = field.labels && field.labels[0] ? field.labels[0].textContent.replace('*', '').trim() : 'This field';
                        msg.textContent = label + ' is required.';
                        field.parentNode.appendChild(msg);
                    }
                } else {
                    var errId = field.id ? field.id + '-error' : null;
                    if (errId) {
                        var existing = document.getElementById(errId);
                        if (existing) existing.remove();
                    }
                }
            });

            if (!valid) {
                e.preventDefault();
                if (firstInvalid) firstInvalid.focus();
            }
        });
    });

    // Auto-hide alerts after 5 seconds with smooth fade
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.4s ease';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 400);
        }, 5000);
    });
});

// Helper function to format currency
function formatCurrency(amount) {
    return '€' + parseFloat(amount).toFixed(2);
}

// ── Pending cart-add replay ──────────────────────────────────────────────────
// When a guest tries to add to cart they get redirected to login.
// Before redirecting, JS stores the payload in sessionStorage.
// On the next page load (after login returns them here), we replay it.

document.addEventListener('DOMContentLoaded', function () {
    const raw = sessionStorage.getItem('pendingCartAdd');
    if (!raw) return;

    let pending;
    try { pending = JSON.parse(raw); } catch (e) { sessionStorage.removeItem('pendingCartAdd'); return; }

    // Only replay if we're back on the same page the action came from
    if (pending.fromPath && window.location.pathname !== pending.fromPath) return;

    sessionStorage.removeItem('pendingCartAdd');

    fetch('/cart/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(pending.payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.requireLogin) return; // still not logged in — give up
        if (data.success || data.cart_item_id) {
            _showCartReplayToast('Added to cart!');
            // Update cart count badge if present
            const badge = document.getElementById('cart-count');
            if (badge && data.cart_count != null) {
                badge.textContent = data.cart_count;
                badge.style.display = 'inline';
            }
        }
    })
    .catch(() => {});
});

function _showCartReplayToast(msg) {
    const toast = document.createElement('div');
    toast.textContent = msg;
    toast.style.cssText = 'position:fixed;bottom:32px;left:50%;transform:translateX(-50%);background:#22c55e;color:#fff;padding:12px 28px;border-radius:8px;font-weight:600;font-size:1rem;z-index:99999;box-shadow:0 4px 16px rgba(0,0,0,0.18);';
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.transition = 'opacity 0.4s'; toast.style.opacity = '0'; setTimeout(() => toast.remove(), 400); }, 3000);
}

/**
 * Call this instead of window.location.href when a cart-add is blocked by login.
 * Stores the payload and redirects to login with a ?redirect= back to this page.
 */
function redirectToLoginWithPendingCart(payload) {
    sessionStorage.setItem('pendingCartAdd', JSON.stringify({
        fromPath: window.location.pathname,
        payload: payload
    }));
    window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
}

function initCookiePopup(loggedIn, cookieAccepted) {
    if (!loggedIn || cookieAccepted !== 0) return;

    var popup = document.createElement('div');
    popup.id = 'cookie-popup';
    popup.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#fff;border:1px solid #e2e8f0;box-shadow:0 4px 24px rgba(0,0,0,0.12);border-radius:14px;padding:20px 24px;z-index:9999;width:560px;max-width:95vw;';
    popup.innerHTML = `
        <h3 style="margin:0 0 8px;font-size:1rem;color:#1a1a2e;">🍪 We use cookies</h3>
        <p style="margin:0 0 16px;font-size:0.875rem;line-height:1.6;color:#555;">
            We use essential cookies to keep the site working and, with your consent, optional cookies to improve your experience and analyse usage.
            See our <a href="/cookies" style="color:#15130E;">Cookie Policy</a> and <a href="/privacy" style="color:#15130E;">Privacy Policy</a> for details.
        </p>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button id="rejectCookieBtn" style="padding:8px 18px;border:1.5px solid #cbd5e1;border-radius:8px;background:#fff;color:#555;font-size:0.875rem;font-weight:600;cursor:pointer;">Reject optional</button>
            <button id="acceptCookieBtn" style="padding:8px 18px;border:none;border-radius:8px;background:linear-gradient(135deg,#15130E,#E63946);color:#fff;font-size:0.875rem;font-weight:600;cursor:pointer;">Accept all</button>
        </div>
    `;
    document.body.appendChild(popup);

    function sendConsent(value) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/account/cookie-consent', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('accept=' + value);
        popup.remove();
    }
    document.getElementById('acceptCookieBtn').onclick = function() { sendConsent(1); };
    document.getElementById('rejectCookieBtn').onclick = function() { sendConsent(2); };
}

// Filter products on shop page
function filterProducts() {
    const typeFilter = document.getElementById('type-filter');
    const sortFilter = document.getElementById('sort-filter');
    
    if (typeFilter && sortFilter) {
        const type = typeFilter.value;
        const sort = sortFilter.value;
        
        // Redirect with query params
        let url = '/shop?';
        if (type) url += 'type=' + type + '&';
        if (sort) url += 'sort=' + sort;
        
        window.location.href = url;
    }
}
