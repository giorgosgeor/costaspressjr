/* size-guide.js — from views/partials/size_guide_modal.php, loaded where the inline script used to run. */

(function () {
    if (window.__sizeGuideWired) return;
    window.__sizeGuideWired = true;

    window.openSizeGuide = function (imageUrl, title) {
        if (!imageUrl) return;
        var img = document.getElementById('sizeGuideImage');
        var ttl = document.getElementById('sizeGuideTitle');
        var overlay = document.getElementById('sizeGuideOverlay');
        if (!img || !overlay) return;
        img.src = imageUrl;
        if (ttl) ttl.textContent = (title ? title + ' — ' : '') + 'Size Guide';
        overlay.style.display = 'flex';
    };
    window.closeSizeGuide = function () {
        var overlay = document.getElementById('sizeGuideOverlay');
        if (overlay) overlay.style.display = 'none';
    };
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') window.closeSizeGuide();
    });
})();
