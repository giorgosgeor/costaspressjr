/* anime.js — from views/shop/anime.php, loaded where the inline script used to run. */

// Cards: a tilt and a moving foil highlight that follow the pointer, and a
// button that flips the card to its back print. The tilt is skipped for
// touch screens and for a reduced-motion preference; the flip always works.
(function () {
    var cards = document.querySelectorAll('[data-card]');
    var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var hover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    // Scroll entrance: the art and each card play their entrance when they
    // come into view (see pages/anime.css). Cards in a row go one after another.
    var page = document.querySelector('.anime-page');
    if (page && !calm && 'IntersectionObserver' in window) {
        page.classList.add('js-reveal');
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-in');
                io.unobserve(entry.target);
            });
        }, { threshold: 0.2 });
        page.querySelectorAll('.an-art-stage, .an-card-wrap').forEach(function (el) {
            if (el.classList.contains('an-card-wrap')) {
                var siblings = Array.prototype.indexOf.call(el.parentNode.children, el);
                el.style.setProperty('--i', siblings % 4);
            }
            io.observe(el);
        });
    }

    cards.forEach(function (card) {
        if (!calm && hover) {
            card.addEventListener('pointermove', function (e) {
                var r = card.getBoundingClientRect();
                var x = (e.clientX - r.left) / r.width;
                var y = (e.clientY - r.top) / r.height;
                card.style.setProperty('--mx', (x * 100).toFixed(1) + '%');
                card.style.setProperty('--my', (y * 100).toFixed(1) + '%');
                card.style.setProperty('--rx', ((0.5 - y) * 14).toFixed(2) + 'deg');
                card.style.setProperty('--ry', ((x - 0.5) * 18).toFixed(2) + 'deg');
                card.classList.add('is-live');
            });
            card.addEventListener('pointerleave', function () {
                card.classList.remove('is-live');
                card.style.setProperty('--rx', '0deg');
                card.style.setProperty('--ry', '0deg');
            });
        }
    });

    document.querySelectorAll('.an-flip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('.an-card-wrap').querySelector('[data-card]');
            var flipped = !card.classList.contains('is-flipped');
            card.classList.toggle('is-flipped', flipped);
            btn.setAttribute('aria-pressed', flipped ? 'true' : 'false');
            var label = flipped ? btn.dataset.labelFront : btn.dataset.labelBack;
            btn.querySelector('span').textContent = label;
            btn.title = label;
            // Keep keyboard focus and the link on the side that is showing.
            var front = card.querySelector('.an-card-front');
            var back = card.querySelector('.an-card-back');
            if (front && back) {
                front.setAttribute('tabindex', flipped ? '-1' : '0');
                front.setAttribute('aria-hidden', flipped ? 'true' : 'false');
                back.setAttribute('tabindex', flipped ? '0' : '-1');
                back.setAttribute('aria-hidden', flipped ? 'false' : 'true');
            }
        });
    });
})();
