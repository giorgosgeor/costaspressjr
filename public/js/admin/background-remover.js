/* background-remover.js — from views/admin/tools/background_remover.php, loaded where the inline script used to run. */

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
        UI.error('Please upload an image first');
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
