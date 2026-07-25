<?php $title = 'Background Remover - Admin'; ?>
<?php require __DIR__ . '/../layouts/admin_header.php'; ?>

<style>
.remover-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.remover-header {
    margin-bottom: 30px;
}

.remover-header h1 {
    font-size: 1.8rem;
    margin-bottom: 10px;
}

.remover-header p {
    color: #666;
}

.remover-grid {
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

.preview-area {
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: repeating-conic-gradient(#e0e0e0 0% 25%, #fff 0% 50%) 50% / 20px 20px;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
}

.preview-area img,
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
    margin-bottom: 20px;
}

.control-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.control-group input[type="range"] {
    width: 100%;
}

.control-group .range-value {
    text-align: right;
    font-size: 12px;
    color: #666;
}

.color-picker-row {
    display: flex;
    gap: 10px;
    align-items: center;
}

.color-picker-row input[type="color"] {
    width: 50px;
    height: 35px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.color-picker-row span {
    color: #666;
    font-size: 14px;
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

.processing-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 10px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f0f0f0;
    border-top-color: #15130E;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
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
    .remover-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="remover-container">
    <div class="remover-header">
        <h1> Background Remover</h1>
        <p>Upload a design image to remove its background. Best results with solid color backgrounds.</p>
    </div>

    <div class="remover-grid">
        <!-- Original Image Panel -->
        <div class="image-panel">
            <h3>📤 Original Image</h3>
            <div class="upload-area" id="uploadArea">
                <div class="icon">🖼️</div>
                <p>Drag & drop an image here</p>
                <p>or click to browse</p>
                <small>Supports: JPG, PNG, GIF, WebP</small>
            </div>
            <input type="file" id="fileInput" accept="image/*">
            <div class="preview-area hidden" id="originalPreview">
                <img id="originalImage" alt="Original">
                <div class="processing-overlay hidden" id="processingOverlay">
                    <div class="spinner"></div>
                    <span>Processing...</span>
                </div>
            </div>
        </div>

        <!-- Result Panel -->
        <div class="image-panel">
            <h3> Result (Transparent Background)</h3>
            <div class="preview-area" id="resultPreview">
                <div class="preview-placeholder" id="resultPlaceholder">
                    <div style="font-size: 48px; margin-bottom: 10px;"></div>
                    <p>Upload an image to see the result</p>
                </div>
                <canvas id="resultCanvas" class="hidden"></canvas>
            </div>
        </div>
    </div>

    <!-- Controls -->
    <div class="controls-panel">
        <h3>⚙️ Settings</h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="control-group">
                <label>Background Color to Remove</label>
                <div class="color-picker-row">
                    <input type="color" id="bgColor" value="#ffffff">
                    <span>Click to pick the background color</span>
                    <button class="btn btn-secondary" onclick="enableColorPicker()" id="pickerBtn"> Pick from Image</button>
                </div>
            </div>

            <div class="control-group">
                <label>Tolerance: <span id="toleranceValue">30</span>%</label>
                <input type="range" id="tolerance" min="0" max="100" value="30" oninput="updateToleranceValue()">
                <div class="range-value">Lower = more precise, Higher = removes more similar colors</div>
            </div>

            <div class="control-group">
                <label>Edge Softness: <span id="softnessValue">0</span>px</label>
                <input type="range" id="softness" min="0" max="10" value="0" oninput="updateSoftnessValue()">
                <div class="range-value">Smooths edges of the cutout</div>
            </div>
        </div>

        <div class="btn-group" style="margin-top: 20px;">
            <button class="btn btn-primary" onclick="processImage()" id="processBtn" disabled>🔄 Remove Background</button>
            <button class="btn btn-success" onclick="downloadResult()" id="downloadBtn" disabled>💾 Download PNG</button>
            <button class="btn btn-secondary" onclick="resetAll()">🗑️ Clear</button>
        </div>
    </div>

    <div class="tip-box">
        <h4> Tips for Best Results</h4>
        <ul>
            <li>Works best with <strong>solid color backgrounds</strong> (white, green screen, etc.)</li>
            <li>Use the <strong>color picker</strong> to select the exact background color from your image</li>
            <li>Increase <strong>tolerance</strong> if some background remains, decrease if too much is removed</li>
            <li>Use <strong>edge softness</strong> to smooth jagged edges</li>
            <li>For complex backgrounds, consider using a dedicated tool like remove.bg</li>
        </ul>
    </div>
</div>

<script>
let originalImage = null;
let isPickingColor = false;

// File upload handling
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileInput');
const originalPreview = document.getElementById('originalPreview');
const originalImg = document.getElementById('originalImage');

uploadArea.addEventListener('click', () => fileInput.click());

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        loadImage(file);
    }
});

fileInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
        loadImage(file);
    }
});

function loadImage(file) {
    const reader = new FileReader();
    reader.onload = (e) => {
        originalImg.src = e.target.result;
        originalImg.onload = () => {
            uploadArea.classList.add('hidden');
            originalPreview.classList.remove('hidden');
            document.getElementById('processBtn').disabled = false;
            originalImage = originalImg;
        };
    };
    reader.readAsDataURL(file);
}

// Color picker from image
function enableColorPicker() {
    if (!originalImage) {
        alert('Please upload an image first');
        return;
    }
    isPickingColor = true;
    originalImg.style.cursor = 'crosshair';
    document.getElementById('pickerBtn').textContent = '🎯 Click on image...';
    document.getElementById('pickerBtn').disabled = true;
}

originalImg.addEventListener('click', (e) => {
    if (!isPickingColor) return;
    
    // Get click coordinates relative to image
    const rect = originalImg.getBoundingClientRect();
    const scaleX = originalImg.naturalWidth / rect.width;
    const scaleY = originalImg.naturalHeight / rect.height;
    const x = Math.floor((e.clientX - rect.left) * scaleX);
    const y = Math.floor((e.clientY - rect.top) * scaleY);
    
    // Draw image to canvas to get pixel color
    const canvas = document.createElement('canvas');
    canvas.width = originalImg.naturalWidth;
    canvas.height = originalImg.naturalHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(originalImg, 0, 0);
    
    const pixel = ctx.getImageData(x, y, 1, 1).data;
    const hex = '#' + [pixel[0], pixel[1], pixel[2]].map(c => c.toString(16).padStart(2, '0')).join('');
    
    document.getElementById('bgColor').value = hex;
    
    isPickingColor = false;
    originalImg.style.cursor = 'default';
    document.getElementById('pickerBtn').textContent = '🎯 Pick from Image';
    document.getElementById('pickerBtn').disabled = false;
});

// Range value updates
function updateToleranceValue() {
    document.getElementById('toleranceValue').textContent = document.getElementById('tolerance').value;
}

function updateSoftnessValue() {
    document.getElementById('softnessValue').textContent = document.getElementById('softness').value;
}

// Process image
function processImage() {
    if (!originalImage) return;
    
    const processingOverlay = document.getElementById('processingOverlay');
    processingOverlay.classList.remove('hidden');
    
    setTimeout(() => {
        const canvas = document.getElementById('resultCanvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = originalImage.naturalWidth;
        canvas.height = originalImage.naturalHeight;
        
        ctx.drawImage(originalImage, 0, 0);
        
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const data = imageData.data;
        
        // Get settings
        const bgColorHex = document.getElementById('bgColor').value;
        const tolerance = parseInt(document.getElementById('tolerance').value) * 2.55; // Convert to 0-255 range
        const softness = parseInt(document.getElementById('softness').value);
        
        // Parse background color
        const bgR = parseInt(bgColorHex.substring(1, 3), 16);
        const bgG = parseInt(bgColorHex.substring(3, 5), 16);
        const bgB = parseInt(bgColorHex.substring(5, 7), 16);
        
        // Process each pixel
        for (let i = 0; i < data.length; i += 4) {
            const r = data[i];
            const g = data[i + 1];
            const b = data[i + 2];
            
            // Calculate color distance
            const distance = Math.sqrt(
                Math.pow(r - bgR, 2) +
                Math.pow(g - bgG, 2) +
                Math.pow(b - bgB, 2)
            );
            
            // Apply tolerance
            if (distance < tolerance) {
                if (softness > 0 && distance > tolerance - (softness * 10)) {
                    // Soft edge - gradual transparency
                    const alpha = Math.floor(255 * (distance / tolerance));
                    data[i + 3] = alpha;
                } else {
                    // Make transparent
                    data[i + 3] = 0;
                }
            }
        }
        
        ctx.putImageData(imageData, 0, 0);
        
        // Show result
        document.getElementById('resultPlaceholder').classList.add('hidden');
        canvas.classList.remove('hidden');
        document.getElementById('downloadBtn').disabled = false;
        
        processingOverlay.classList.add('hidden');
    }, 100);
}

// Download result
function downloadResult() {
    const canvas = document.getElementById('resultCanvas');
    const link = document.createElement('a');
    link.download = 'design-transparent.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}

// Reset
function resetAll() {
    uploadArea.classList.remove('hidden');
    originalPreview.classList.add('hidden');
    document.getElementById('resultPlaceholder').classList.remove('hidden');
    document.getElementById('resultCanvas').classList.add('hidden');
    document.getElementById('processBtn').disabled = true;
    document.getElementById('downloadBtn').disabled = true;
    document.getElementById('tolerance').value = 30;
    document.getElementById('softness').value = 0;
    document.getElementById('bgColor').value = '#ffffff';
    updateToleranceValue();
    updateSoftnessValue();
    fileInput.value = '';
    originalImage = null;
}
</script>

<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
