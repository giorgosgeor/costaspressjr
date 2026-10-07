function openPreviewModal(imgSrc, label) {
    const modal = document.getElementById('previewModal');
    document.getElementById('previewModalImg').src = imgSrc;
    document.getElementById('previewModalLabel').textContent = label;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closePreviewModal(e) {
    if (e && e.target !== document.getElementById('previewModal')) return;
    document.getElementById('previewModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePreviewModal();
});

// ── Copy a text specification to the clipboard ───────────────────────────
// One click gives the printer the whole line: content, font, size, colour,
// style, placement and position — rather than transcribing it by eye.
document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.prod-copy');
    if (!btn) return;
    var spec = btn.getAttribute('data-spec') || '';
    var done = function () {
        var original = btn.textContent;
        btn.textContent = 'Copied';
        btn.classList.add('copied');
        setTimeout(function () { btn.textContent = original; btn.classList.remove('copied'); }, 1400);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(spec).then(done, function () { fallback(spec, done); });
    } else {
        fallback(spec, done);
    }
    function fallback(text, cb) {
        // execCommand is deprecated but is the only option on a page served
        // over plain HTTP, where navigator.clipboard is unavailable.
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.cssText = 'position:absolute;left:-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); cb(); } catch (err) {}
        document.body.removeChild(ta);
    }
});
