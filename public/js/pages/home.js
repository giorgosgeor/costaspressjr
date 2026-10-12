/* home.js — from views/pages/home.php, loaded where the inline script used to run. */

// The featured product cards are plain links to each product's page
// (/product/{id}-{slug}), so they need no script.
document.addEventListener('DOMContentLoaded', function () {
    // Expand/collapse the grid. Progressive enhancement: with no JS the eight
    // cards still render and the site's nav still reaches /shop, so nothing is
    // stranded behind a button that cannot work.
    var toggle = document.getElementById('productsToggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') === 'true';
            document.querySelectorAll('#productsGrid .is-extra').forEach(function (el) {
                el.hidden = open;
            });
            toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            toggle.classList.toggle('is-open', !open);
            var label = toggle.querySelector('[data-toggle-label]');
            if (label) {
                label.textContent = open
                    ? toggle.getAttribute('data-label-more')
                    : toggle.getAttribute('data-label-less');
            }
        });
    }
});
