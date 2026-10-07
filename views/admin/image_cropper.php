<?php $title = 'Image Cropper - Admin'; ?>
<?php require __DIR__ . '/../layouts/admin_header.php'; ?>

<style>
.cropper-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.cropper-header {
    margin-bottom: 30px;
}

.cropper-header h1 {
    font-size: 1.8rem;
    margin-bottom: 10px;
}

.cropper-header p {
    color: #666;
}

.cropper-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.image-panel {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.image-panel h3 {
    margin-bottom: 15px;
    font-size: 1.1rem;
    color: #333;
}

.upload-area {
    border: 3px dashed #ddd;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    background: #fafafa;
    min-height: 300px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.upload-area:hover {
    border-color: #15130E;
    background: #f0f0ff;
}

.upload-area.dragover {
    border-color: #15130E;
    background: #e8e8ff;
}

.upload-area .icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.upload-area p {
    color: #666;
    margin-bottom: 10px;
}

.upload-area small {
    color: #999;
}

#fileInput {
    display: none;
}

.canvas-wrap {
    position: relative;
    display: inline-block;
    max-width: 100%;
    cursor: crosshair;
    user-select: none;
}

#cropCanvas {
    display: block;
    max-width: 100%;
    border-radius: 8px;
}

.preview-area {
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: repeating-conic-gradient(#e0e0e0 0% 25%, #fff 0% 50%) 50% / 20px 20px;
    border-radius: 8px;
    overflow: hidden;
}

.preview-area canvas {
    max-width: 100%;
    max-height: 400px;
    object-fit: contain;
}

.preview-placeholder {
    color: #999;
    text-align: center;
}

.controls-panel {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-top: 20px;
}

.controls-panel h3 {
    margin-bottom: 15px;
    font-size: 1.1rem;
}

.control-group {
    margin-bottom: 16px;
}

.control-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.aspect-btns {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.aspect-btn {
    padding: 6px 14px;
    border: 2px solid #ddd;
    background: #fff;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
}

.aspect-btn:hover {
    border-color: #15130E;
    color: #15130E;
}

.aspect-btn.active {
    border-color: #15130E;
    background: #15130E;
    color: white;
}

.crop-info {
    font-size: 13px;
    color: #888;
    margin-top: 8px;
}

.btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background: linear-gradient(135deg, #15130E 0%, #E63946 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(125, 128, 218, 0.4);
}

.btn-secondary {
    background: #f0f0f0;
    color: #333;
}

.btn-secondary:hover {
    background: #e0e0e0;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

.hidden {
    display: none !important;
}

.tip-box {
    background: #e8f4fd;
    border-left: 4px solid #2196F3;
    padding: 15px;
    border-radius: 0 8px 8px 0;
    margin-top: 20px;
}

.tip-box h4 {
    margin-bottom: 8px;
    color: #1976D2;
}

.tip-box ul {
    margin: 0;
    padding-left: 20px;
    color: #555;
}

.tip-box li {
    margin-bottom: 5px;
}

@media (max-width: 768px) {
    .cropper-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="cropper-container">
    <div class="cropper-header">
        <h1> Image Cropper</h1>
        <p>Upload an image, drag to select the crop area, then save the result.</p>
    </div>

    <div class="cropper-grid">
        <!-- Source Panel -->
        <div class="image-panel">
            <h3>📤 Source Image — drag to crop</h3>
            <div class="upload-area" id="uploadArea">
                <div class="icon">🖼️</div>
                <p>Drag & drop an image here</p>
                <p>or click to browse</p>
                <small>Supports: JPG, PNG, GIF, WebP</small>
            </div>
            <input type="file" id="fileInput" accept="image/*">
            <div class="canvas-wrap hidden" id="canvasWrap">
                <canvas id="cropCanvas"></canvas>
            </div>
        </div>

        <!-- Result Panel -->
        <div class="image-panel">
            <h3>✅ Cropped Result</h3>
            <div class="preview-area" id="resultPreview">
                <div class="preview-placeholder" id="resultPlaceholder">
                    <div style="font-size:48px;margin-bottom:10px;">✂️</div>
                    <p>Set crop area and click Apply Crop</p>
                </div>
                <canvas id="resultCanvas" class="hidden"></canvas>
            </div>
        </div>
    </div>

    <!-- Controls -->
    <div class="controls-panel">
        <h3>⚙️ Options</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
            <div class="control-group">
                <label>Aspect Ratio</label>
                <div class="aspect-btns">
                    <button class="aspect-btn active" data-ratio="0">Free</button>
                    <button class="aspect-btn" data-ratio="1">1:1</button>
                    <button class="aspect-btn" data-ratio="1.3333">4:3</button>
                    <button class="aspect-btn" data-ratio="1.7778">16:9</button>
                    <button class="aspect-btn" data-ratio="0.75">3:4</button>
                    <button class="aspect-btn" data-ratio="0.5625">9:16</button>
                </div>
            </div>
            <div class="control-group">
                <label>Crop Area</label>
                <div class="crop-info" id="cropInfo">Upload an image to begin</div>
            </div>
        </div>
        <div class="btn-group" style="margin-top:20px;">
            <button class="btn btn-primary" id="applyCropBtn" disabled onclick="applyCrop()">✂️ Apply Crop</button>
            <button class="btn btn-success" id="downloadBtn" disabled onclick="downloadResult()">💾 Download PNG</button>
            <button class="btn btn-secondary" onclick="resetAll()">🗑️ Clear</button>
        </div>
    </div>

    <div class="tip-box">
        <h4>💡 How to use</h4>
        <ul>
            <li>Upload an image, then <strong>click and drag</strong> on it to define the crop area</li>
            <li>The crop box can be <strong>moved</strong> by dragging inside it, or <strong>resized</strong> by dragging the corner/edge handles</li>
            <li>Choose an <strong>aspect ratio</strong> to lock proportions while resizing</li>
            <li>Click <strong>Apply Crop</strong> to see the result, then <strong>Download PNG</strong> to save</li>
        </ul>
    </div>
</div>

<script>
// ── State ──────────────────────────────────────────────────────────────────
let sourceImage = null;      // HTMLImageElement
let naturalW = 0, naturalH = 0;
let displayScale = 1;        // naturalSize / displaySize

// Crop rect in display-pixel coords (relative to canvas)
let crop = { x: 0, y: 0, w: 0, h: 0 };
let hasCrop = false;

let aspectRatio = 0;         // 0 = free

// Drag state
const HANDLE_SIZE = 9;
const HANDLES = ['nw','n','ne','e','se','s','sw','w','move'];
let drag = null;  // { type, startX, startY, startCrop }

// ── Upload ─────────────────────────────────────────────────────────────────
const uploadArea = document.getElementById('uploadArea');
const fileInput  = document.getElementById('fileInput');
const canvasWrap = document.getElementById('canvasWrap');
const cropCanvas = document.getElementById('cropCanvas');
const ctx        = cropCanvas.getContext('2d');

uploadArea.addEventListener('click', () => fileInput.click());
uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
uploadArea.addEventListener('drop', e => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    const f = e.dataTransfer.files[0];
    if (f && f.type.startsWith('image/')) loadImage(f);
});
fileInput.addEventListener('change', e => { if (e.target.files[0]) loadImage(e.target.files[0]); });

function loadImage(file) {
    const reader = new FileReader();
    reader.onload = e => {
        const img = new Image();
        img.onload = () => {
            sourceImage = img;
            naturalW = img.naturalWidth;
            naturalH = img.naturalHeight;
            initCanvas();
            uploadArea.classList.add('hidden');
            canvasWrap.classList.remove('hidden');
            document.getElementById('applyCropBtn').disabled = false;
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

function initCanvas() {
    // Fit within ~580px width
    const maxW = canvasWrap.parentElement.clientWidth - 40;
    displayScale = naturalW > maxW ? naturalW / maxW : 1;
    const dispW = Math.round(naturalW / displayScale);
    const dispH = Math.round(naturalH / displayScale);

    cropCanvas.width  = dispW;
    cropCanvas.height = dispH;

    // Default crop = full image
    crop = { x: 0, y: 0, w: dispW, h: dispH };
    hasCrop = true;
    drawCanvas();
    updateCropInfo();
}

// ── Draw ───────────────────────────────────────────────────────────────────
function drawCanvas() {
    ctx.clearRect(0, 0, cropCanvas.width, cropCanvas.height);
    ctx.drawImage(sourceImage, 0, 0, cropCanvas.width, cropCanvas.height);

    if (!hasCrop) return;

    const { x, y, w, h } = crop;

    // Dark overlay outside crop
    ctx.fillStyle = 'rgba(0,0,0,0.45)';
    ctx.fillRect(0, 0, cropCanvas.width, y);               // top
    ctx.fillRect(0, y + h, cropCanvas.width, cropCanvas.height - y - h); // bottom
    ctx.fillRect(0, y, x, h);                              // left
    ctx.fillRect(x + w, y, cropCanvas.width - x - w, h);  // right

    // Crop border
    ctx.strokeStyle = '#fff';
    ctx.lineWidth = 1.5;
    ctx.strokeRect(x, y, w, h);

    // Rule-of-thirds grid
    ctx.strokeStyle = 'rgba(255,255,255,0.35)';
    ctx.lineWidth = 0.8;
    for (let i = 1; i < 3; i++) {
        ctx.beginPath();
        ctx.moveTo(x + w * i / 3, y);
        ctx.lineTo(x + w * i / 3, y + h);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(x, y + h * i / 3);
        ctx.lineTo(x + w, y + h * i / 3);
        ctx.stroke();
    }

    // Handles
    ctx.fillStyle = '#fff';
    ctx.strokeStyle = '#15130E';
    ctx.lineWidth = 1.5;
    getHandlePositions().forEach(hp => {
        ctx.beginPath();
        ctx.rect(hp.x - HANDLE_SIZE / 2, hp.y - HANDLE_SIZE / 2, HANDLE_SIZE, HANDLE_SIZE);
        ctx.fill();
        ctx.stroke();
    });
}

function getHandlePositions() {
    const { x, y, w, h } = crop;
    return [
        { id: 'nw', x: x,       y: y       },
        { id: 'n',  x: x + w/2, y: y       },
        { id: 'ne', x: x + w,   y: y       },
        { id: 'e',  x: x + w,   y: y + h/2 },
        { id: 'se', x: x + w,   y: y + h   },
        { id: 's',  x: x + w/2, y: y + h   },
        { id: 'sw', x: x,       y: y + h   },
        { id: 'w',  x: x,       y: y + h/2 },
    ];
}

// ── Mouse ──────────────────────────────────────────────────────────────────
function canvasXY(e) {
    const r = cropCanvas.getBoundingClientRect();
    return {
        x: (e.clientX - r.left) * (cropCanvas.width  / r.width),
        y: (e.clientY - r.top)  * (cropCanvas.height / r.height)
    };
}

function hitHandle(mx, my) {
    for (const hp of getHandlePositions()) {
        if (Math.abs(mx - hp.x) <= HANDLE_SIZE && Math.abs(my - hp.y) <= HANDLE_SIZE)
            return hp.id;
    }
    return null;
}

function insideCrop(mx, my) {
    return mx >= crop.x && mx <= crop.x + crop.w &&
           my >= crop.y && my <= crop.y + crop.h;
}

cropCanvas.addEventListener('mousedown', e => {
    const { x, y } = canvasXY(e);
    const handle = hasCrop ? hitHandle(x, y) : null;

    if (handle) {
        drag = { type: handle, startX: x, startY: y, startCrop: { ...crop } };
    } else if (hasCrop && insideCrop(x, y)) {
        drag = { type: 'move', startX: x, startY: y, startCrop: { ...crop } };
    } else {
        // Start new crop
        drag = { type: 'new', startX: x, startY: y };
        crop = { x, y, w: 0, h: 0 };
        hasCrop = false;
    }
    e.preventDefault();
});

window.addEventListener('mousemove', e => {
    if (!drag) return;
    const { x, y } = canvasXY(e);
    const dx = x - drag.startX;
    const dy = y - drag.startY;
    const sc = drag.startCrop;
    const cw = cropCanvas.width, ch = cropCanvas.height;

    if (drag.type === 'new') {
        let nx = Math.min(drag.startX, x);
        let ny = Math.min(drag.startY, y);
        let nw = Math.abs(x - drag.startX);
        let nh = Math.abs(y - drag.startY);
        if (aspectRatio > 0) nh = nw / aspectRatio;
        crop = { x: clamp(nx, 0, cw - nw), y: clamp(ny, 0, ch - nh), w: nw, h: nh };
        hasCrop = nw > 2 && nh > 2;

    } else if (drag.type === 'move') {
        crop.x = clamp(sc.x + dx, 0, cw - sc.w);
        crop.y = clamp(sc.y + dy, 0, ch - sc.h);

    } else {
        // Handle resize
        let { x: cx, y: cy, w: cW, h: cH } = sc;
        const type = drag.type;

        if (type.includes('e')) { cW = Math.max(10, sc.w + dx); }
        if (type.includes('s')) { cH = Math.max(10, sc.h + dy); }
        if (type.includes('w')) { const nw = Math.max(10, sc.w - dx); cx = sc.x + sc.w - nw; cW = nw; }
        if (type.includes('n')) { const nh = Math.max(10, sc.h - dy); cy = sc.y + sc.h - nh; cH = nh; }

        if (aspectRatio > 0) {
            if (type === 'n' || type === 's') { cW = cH * aspectRatio; }
            else if (type === 'e' || type === 'w') { cH = cW / aspectRatio; }
            else {
                // corner: use width as master
                cH = cW / aspectRatio;
                if (type.includes('n')) cy = sc.y + sc.h - cH;
            }
        }

        // Clamp to canvas
        if (cx < 0) { cW += cx; cx = 0; }
        if (cy < 0) { cH += cy; cy = 0; }
        if (cx + cW > cw) cW = cw - cx;
        if (cy + cH > ch) cH = ch - cy;

        crop = { x: cx, y: cy, w: Math.max(10, cW), h: Math.max(10, cH) };
    }

    drawCanvas();
    updateCropInfo();
});

window.addEventListener('mouseup', () => {
    drag = null;
    if (crop.w > 2 && crop.h > 2) hasCrop = true;
    drawCanvas();
});

// Cursor on hover
cropCanvas.addEventListener('mousemove', e => {
    if (drag) return;
    if (!hasCrop) { cropCanvas.style.cursor = 'crosshair'; return; }
    const { x, y } = canvasXY(e);
    const handle = hitHandle(x, y);
    if (handle) {
        const cursors = { nw:'nw-resize', n:'n-resize', ne:'ne-resize', e:'e-resize',
                          se:'se-resize', s:'s-resize', sw:'sw-resize', w:'w-resize' };
        cropCanvas.style.cursor = cursors[handle] || 'crosshair';
    } else if (insideCrop(x, y)) {
        cropCanvas.style.cursor = 'move';
    } else {
        cropCanvas.style.cursor = 'crosshair';
    }
});

// ── Aspect ratio buttons ───────────────────────────────────────────────────
document.querySelectorAll('.aspect-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.aspect-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        aspectRatio = parseFloat(btn.dataset.ratio);
    });
});

// ── Apply Crop ─────────────────────────────────────────────────────────────
function applyCrop() {
    if (!sourceImage || !hasCrop || crop.w < 2 || crop.h < 2) return;

    const sx = Math.round(crop.x * displayScale);
    const sy = Math.round(crop.y * displayScale);
    const sw = Math.round(crop.w * displayScale);
    const sh = Math.round(crop.h * displayScale);

    const result = document.getElementById('resultCanvas');
    result.width  = sw;
    result.height = sh;
    const rctx = result.getContext('2d');
    rctx.drawImage(sourceImage, sx, sy, sw, sh, 0, 0, sw, sh);

    document.getElementById('resultPlaceholder').classList.add('hidden');
    result.classList.remove('hidden');
    document.getElementById('downloadBtn').disabled = false;
}

// ── Download ───────────────────────────────────────────────────────────────
function downloadResult() {
    const canvas = document.getElementById('resultCanvas');
    const link = document.createElement('a');
    link.download = 'cropped-image.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}

// ── Reset ──────────────────────────────────────────────────────────────────
function resetAll() {
    sourceImage = null;
    hasCrop = false;
    drag = null;
    uploadArea.classList.remove('hidden');
    canvasWrap.classList.add('hidden');
    document.getElementById('resultPlaceholder').classList.remove('hidden');
    document.getElementById('resultCanvas').classList.add('hidden');
    document.getElementById('applyCropBtn').disabled = true;
    document.getElementById('downloadBtn').disabled = true;
    document.getElementById('cropInfo').textContent = 'Upload an image to begin';
    fileInput.value = '';
    ctx.clearRect(0, 0, cropCanvas.width, cropCanvas.height);
}

// ── Helpers ────────────────────────────────────────────────────────────────
function clamp(val, min, max) { return Math.max(min, Math.min(max, val)); }

function updateCropInfo() {
    if (!hasCrop || crop.w < 2) {
        document.getElementById('cropInfo').textContent = 'Drag on the image to set crop area';
        return;
    }
    const sx = Math.round(crop.x * displayScale);
    const sy = Math.round(crop.y * displayScale);
    const sw = Math.round(crop.w * displayScale);
    const sh = Math.round(crop.h * displayScale);
    document.getElementById('cropInfo').textContent =
        `${sw} × ${sh} px  (at ${sx}, ${sy})`;
}
</script>

<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
