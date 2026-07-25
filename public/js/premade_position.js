// State — PHP injects: frontImage, backImageInitial, positions (top of page script block)
let currentSide = 'front';
let newBackImageSrc = '';

function loadProductImage() {
    const select = document.getElementById('productSelect');
    if (!select) return;
    const opt = select.options[select.selectedIndex];
    updateProductImage(opt.dataset.front, opt.dataset.back);
}

function updateProductImage(frontPath, backPath) {
    const el = document.getElementById('mockupProduct');
    const path = currentSide === 'front' ? frontPath : backPath;
    if (el && el.tagName === 'IMG') {
        el.src = path ? '/' + path : '';
        el.style.display = path ? 'block' : 'none';
    }
}

function switchSide(side) {
    if (side === currentSide) return;
    saveCurrentPositionToState();
    currentSide = side;

    document.getElementById('btnFront').classList.toggle('active', side === 'front');
    document.getElementById('btnBack').classList.toggle('active', side === 'back');

    const select = document.getElementById('productSelect');
    if (select) {
        const opt = select.options[select.selectedIndex];
        const path = side === 'front' ? opt.dataset.front : opt.dataset.back;
        const el = document.getElementById('mockupProduct');
        if (el && el.tagName === 'IMG') {
            el.src = path ? '/' + path : '';
            el.style.display = path ? 'block' : 'none';
        }
    }

    const designImg = document.getElementById('designImg');
    const designEl  = document.getElementById('designEl');
    const imgSrc = side === 'front' ? frontImage : (backImageInitial || newBackImageSrc);

    if (imgSrc) {
        designImg.src = '/' + imgSrc;
        designEl.classList.add('visible');
    } else if (newBackImageSrc && side === 'back') {
        designImg.src = newBackImageSrc;
        designEl.classList.add('visible');
    } else {
        designEl.classList.remove('visible');
    }

    setTimeout(() => applyPosition(positions[side]), 20);
}

function initInteract() {
    const el   = document.getElementById('designEl');
    const area = document.getElementById('designArea');

    interact(el)
        .draggable({
            inertia: false,
            modifiers: [interact.modifiers.restrict({ restriction: area, elementRect: { top: 0, left: 0, bottom: 1, right: 1 } })],
            listeners: {
                move(event) {
                    const x = (parseFloat(event.target.getAttribute('data-x')) || 0) + event.dx;
                    const y = (parseFloat(event.target.getAttribute('data-y')) || 0) + event.dy;
                    event.target.style.transform = `translate(calc(-50% + ${x}px), calc(-50% + ${y}px))`;
                    event.target.setAttribute('data-x', x);
                    event.target.setAttribute('data-y', y);
                    saveCurrentPositionToState();
                    updateHiddenInputs();
                }
            }
        })
        .resizable({
            edges: { right: '.resize-handle', bottom: '.resize-handle' },
            modifiers: [
                interact.modifiers.restrictSize({ min: { width: 20, height: 20 }, max: { width: 800, height: 800 } }),
                interact.modifiers.restrict({ restriction: area })
            ],
            listeners: {
                move(event) {
                    const size = Math.max(event.rect.width, event.rect.height);
                    event.target.style.width  = size + 'px';
                    event.target.style.height = size + 'px';
                    saveCurrentPositionToState();
                    updateHiddenInputs();
                }
            }
        });
}

function saveCurrentPositionToState() {
    const el   = document.getElementById('designEl');
    const area = document.getElementById('designArea');
    if (!el || !area || area.offsetWidth === 0) return;
    positions[currentSide] = {
        x:    (parseFloat(el.getAttribute('data-x')) || 0) / (area.offsetWidth  / 2) * 100,
        y:    (parseFloat(el.getAttribute('data-y')) || 0) / (area.offsetHeight / 2) * 100,
        size: (el.offsetWidth / area.offsetWidth) * 100,
    };
}

function applyPosition(pos) {
    const el   = document.getElementById('designEl');
    const area = document.getElementById('designArea');
    if (!el || !area || area.offsetWidth === 0) return;
    const px = (pos.x / 100) * (area.offsetWidth  / 2);
    const py = (pos.y / 100) * (area.offsetHeight / 2);
    const ps = (pos.size / 100) * area.offsetWidth;
    el.style.transform = `translate(calc(-50% + ${px}px), calc(-50% + ${py}px))`;
    el.style.width  = ps + 'px';
    el.style.height = ps + 'px';
    el.setAttribute('data-x', px);
    el.setAttribute('data-y', py);
    updateHiddenInputs();
}

function updateHiddenInputs() {
    document.getElementById('posX').value        = positions.front.x.toFixed(4);
    document.getElementById('posY').value        = positions.front.y.toFixed(4);
    document.getElementById('posSize').value     = positions.front.size.toFixed(4);
    document.getElementById('posBackX').value    = positions.back.x.toFixed(4);
    document.getElementById('posBackY').value    = positions.back.y.toFixed(4);
    document.getElementById('posBackSize').value = positions.back.size.toFixed(4);
}

function resetPosition() {
    positions[currentSide] = { x: 0, y: 0, size: 55 };
    applyPosition(positions[currentSide]);
}

function centerDesign() {
    positions[currentSide].x = 0;
    positions[currentSide].y = 0;
    applyPosition(positions[currentSide]);
}

function previewBackImage(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        newBackImageSrc = e.target.result;
        const preview     = document.getElementById('backPreviewImg');
        const placeholder = document.getElementById('backPreviewPlaceholder');
        preview.src = e.target.result;
        preview.style.display = 'block';
        if (placeholder) placeholder.style.display = 'none';
        if (currentSide === 'back') {
            document.getElementById('designImg').src = e.target.result;
            document.getElementById('designEl').classList.add('visible');
        }
    };
    reader.readAsDataURL(input.files[0]);
}

function confirmRemoveBack() {
    if (!confirm('Remove the back design image?')) return;
    document.getElementById('removeBackImage').value = '1';
    document.getElementById('backPreviewImg').style.display = 'none';
    const ph = document.getElementById('backPreviewPlaceholder');
    if (ph) ph.style.display = '';
    if (currentSide === 'back') {
        document.getElementById('designEl').classList.remove('visible');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initInteract();
    setTimeout(() => applyPosition(positions.front), 60);
    updateHiddenInputs();
});
