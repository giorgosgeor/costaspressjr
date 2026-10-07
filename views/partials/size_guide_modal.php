<?php
/**
 * Reusable Size Guide modal. Include this partial once per page;
 * trigger it via `openSizeGuide(imageUrl, productName)` from any
 * JS handler. Closes on overlay click or the × button.
 *
 * Designed to coexist with existing modals (z-index 51000 sits
 * above the cart modal which uses 50000).
 */
?>
<div id="sizeGuideOverlay"
     style="display:none; position:fixed; inset:0; z-index:51000;
            background:rgba(15,23,42,0.6); backdrop-filter:blur(4px);
            -webkit-backdrop-filter:blur(4px);
            align-items:center; justify-content:center; padding:20px;"
     onclick="if(event.target===this) closeSizeGuide()">
  <div style="background:#fff; border-radius:16px; max-width:760px;
              width:100%; max-height:90vh; overflow:hidden;
              box-shadow:0 24px 64px rgba(0,0,0,0.2);
              display:flex; flex-direction:column;">
    <div style="display:flex; align-items:center; justify-content:space-between;
                padding:14px 20px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
      <h3 id="sizeGuideTitle" style="margin:0; font-size:1.05rem; color:#0f172a; font-weight:600;">
        Size Guide
      </h3>
      <button type="button" onclick="closeSizeGuide()"
              aria-label="Close"
              style="background:transparent; border:none; font-size:22px;
                     color:#64748b; line-height:1; cursor:pointer; padding:4px 8px;">
        &times;
      </button>
    </div>
    <div id="sizeGuideBody"
         style="padding:18px 20px; overflow:auto; flex:1; background:#fff;
                display:flex; align-items:center; justify-content:center;">
      <img id="sizeGuideImage" src="" alt="Size guide"
           style="max-width:100%; max-height:75vh; height:auto;
                  display:block; object-fit:contain;">
    </div>
    <div style="padding:10px 20px; border-top:1px solid #f1f5f9;
                background:#f8fafc; font-size:0.78rem; color:#64748b;
                text-align:center;">
      Measurements in centimetres. Pick the size whose chest width matches yours, plus a bit of ease.
    </div>
  </div>
</div>

<script>
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
</script>
