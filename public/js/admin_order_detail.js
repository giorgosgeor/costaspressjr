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
