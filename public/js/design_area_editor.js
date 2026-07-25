// State — products data is injected inline by PHP before this file loads
let currentProductId = null;
let currentView = 'front';
let imgW = 0, imgH = 0;

let viewData = {
    front:   { x: 27.5, y: 25, w: 45, h: 60 },
    back:    { x: 27.5, y: 25, w: 45, h: 60 },
    lsleeve: { x: 46,   y: 27, w: 13, h: 16 },
    rsleeve: { x: 46,   y: 27, w: 13, h: 16 },
};

const mockupImg  = document.getElementById('mockupImg');
const mockupWrap = document.getElementById('mockupWrap');
const designBox  = document.getElementById('designBox');
const noImageMsg = document.getElementById('noImageMsg');

function loadProduct() {
    const sel = document.getElementById('productSelect');
    const opt = sel.options[sel.selectedIndex];
    currentProductId = sel.value || null;

    if (!currentProductId) {
        mockupWrap.style.display = 'none';
        noImageMsg.style.display = '';
        return;
    }

    const da = JSON.parse(opt.dataset.da);
    viewData.front   = da.front;
    viewData.back    = da.back;
    viewData.lsleeve = da.lsleeve;
    viewData.rsleeve = da.rsleeve;

    window.viewImages = {
        front:   opt.dataset.front   || '',
        back:    opt.dataset.back    || '',
        lsleeve: opt.dataset.lsleeve || '',
        rsleeve: opt.dataset.rsleeve || '',
    };

    document.getElementById('tabBack').style.display    = viewImages.back    ? '' : 'none';
    document.getElementById('tabLsleeve').style.display = viewImages.lsleeve ? '' : 'none';
    document.getElementById('tabRsleeve').style.display = viewImages.rsleeve ? '' : 'none';

    currentView = 'front';
    document.querySelectorAll('.view-tab').forEach(t => t.classList.toggle('active', t.dataset.view === 'front'));
    loadView('front');
}

function loadView(view) {
    currentView = view;
    document.querySelectorAll('.view-tab').forEach(t => t.classList.toggle('active', t.dataset.view === view));

    const imgPath = viewImages[view];
    if (!imgPath) {
        mockupWrap.style.display = 'none';
        noImageMsg.style.display = '';
        noImageMsg.textContent   = 'No image for this view';
        return;
    }

    noImageMsg.style.display = 'none';
    mockupWrap.style.display = 'inline-block';
    mockupImg.src = '/' + imgPath;

    mockupImg.onload = () => {
        imgW = mockupImg.offsetWidth;
        imgH = mockupImg.offsetHeight;
        renderBox();
        initInteract();
    };
    if (mockupImg.complete && mockupImg.naturalWidth) {
        imgW = mockupImg.offsetWidth;
        imgH = mockupImg.offsetHeight;
        renderBox();
        initInteract();
    }

    updateLabel(view);
}

function renderBox() {
    const d = viewData[currentView];
    imgW = mockupImg.offsetWidth;
    imgH = mockupImg.offsetHeight;
    designBox.style.left   = (d.x / 100 * imgW) + 'px';
    designBox.style.top    = (d.y / 100 * imgH) + 'px';
    designBox.style.width  = (d.w / 100 * imgW) + 'px';
    designBox.style.height = (d.h / 100 * imgH) + 'px';
    syncInputs();
}

function syncInputs() {
    const d = viewData[currentView];
    document.getElementById('inX').value = round(d.x);
    document.getElementById('inY').value = round(d.y);
    document.getElementById('inW').value = round(d.w);
    document.getElementById('inH').value = round(d.h);
}

function boxToPercent() {
    imgW = mockupImg.offsetWidth;
    imgH = mockupImg.offsetHeight;
    viewData[currentView] = {
        x: round(parseFloat(designBox.style.left)   / imgW * 100),
        y: round(parseFloat(designBox.style.top)    / imgH * 100),
        w: round(parseFloat(designBox.style.width)  / imgW * 100),
        h: round(parseFloat(designBox.style.height) / imgH * 100),
    };
    syncInputs();
}

function applyFromInputs() {
    viewData[currentView] = {
        x: parseFloat(document.getElementById('inX').value) || 0,
        y: parseFloat(document.getElementById('inY').value) || 0,
        w: parseFloat(document.getElementById('inW').value) || 10,
        h: parseFloat(document.getElementById('inH').value) || 10,
    };
    renderBox();
}

function updateLabel(view) {
    const labels = { front:'Front Design Area', back:'Back Design Area', lsleeve:'Left Sleeve Area', rsleeve:'Right Sleeve Area' };
    document.getElementById('boxLabel').textContent = labels[view] || 'Design Area';
}

function initInteract() {
    interact(designBox)
        .draggable({
            listeners: {
                move(e) {
                    imgW = mockupImg.offsetWidth;
                    imgH = mockupImg.offsetHeight;
                    const bw = parseFloat(designBox.style.width);
                    const bh = parseFloat(designBox.style.height);
                    const x = Math.max(0, Math.min(parseFloat(designBox.style.left) + e.dx, imgW - bw));
                    const y = Math.max(0, Math.min(parseFloat(designBox.style.top)  + e.dy, imgH - bh));
                    designBox.style.left = x + 'px';
                    designBox.style.top  = y + 'px';
                    boxToPercent();
                }
            }
        })
        .resizable({
            edges: { left: true, right: true, bottom: true, top: true },
            modifiers: [
                interact.modifiers.restrictEdges({ outer: '#mockupWrap' }),
                interact.modifiers.restrictSize({ min: { width: 20, height: 20 } }),
            ],
            listeners: {
                move(e) {
                    e.target.style.left   = (parseFloat(e.target.style.left) + e.deltaRect.left) + 'px';
                    e.target.style.top    = (parseFloat(e.target.style.top)  + e.deltaRect.top)  + 'px';
                    e.target.style.width  = e.rect.width  + 'px';
                    e.target.style.height = e.rect.height + 'px';
                    boxToPercent();
                }
            }
        });
}

document.querySelectorAll('.view-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        if (currentProductId) loadView(tab.dataset.view);
    });
});

function copyToView(target) {
    viewData[target] = { ...viewData[currentView] };
    if (target === currentView) renderBox();
}

function saveAll() {
    if (!currentProductId) { alert('Select a product first.'); return; }

    fetch('/admin/products/design-area/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: currentProductId, areas: viewData })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const banner = document.getElementById('savedBanner');
            banner.classList.add('show');
            setTimeout(() => banner.classList.remove('show'), 2500);
        } else {
            alert('Error: ' + (data.error || 'unknown'));
        }
    })
    .catch(() => alert('Save failed.'));
}

function round(v) { return Math.round(v * 10) / 10; }

new ResizeObserver(() => {
    if (currentProductId && mockupImg.offsetWidth) renderBox();
}).observe(mockupImg);
