/* checkout-complete.js — from views/checkout/complete.php, loaded where the inline script used to run. */

(function () {
    var btn = document.getElementById('ccCopy');
    var code = document.getElementById('ccTracking');
    if (!btn || !code || !navigator.clipboard) { if (btn) btn.hidden = true; return; }
    var label = btn.textContent;
    btn.addEventListener('click', function () {
        navigator.clipboard.writeText(code.textContent.trim()).then(function () {
            btn.textContent = btn.dataset.done;
            setTimeout(function () { btn.textContent = label; }, 2000);
        });
    });
})();
