/* order.js — the customer's order page (views/account/order.php):
   the "cancel this order?" confirmation. The cancelling itself is a plain
   form POST to /orders/cancel. */

function openCancelOrder() {
    document.getElementById('cancelOrderOverlay').style.display = 'flex';
}

function closeCancelOrder() {
    document.getElementById('cancelOrderOverlay').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-cancel-order-form]');
    if (!form) return;
    // One refund request per click: the button stays busy until the page
    // reloads with the outcome.
    form.addEventListener('submit', function () {
        var btn = form.querySelector('button[type="submit"]');
        if (window.UI) UI.loading(btn, true); else btn.disabled = true;
    });
});
