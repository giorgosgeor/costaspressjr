<?php $title = 'Background Remover - Admin'; ?>
<?php $extraCss[] = '/css/admin/background-remover.css'; require View::path('layouts/admin_header'); ?>


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
                    <button class="btn btn-secondary" data-on-click="enableColorPicker" id="pickerBtn"> Pick from Image</button>
                </div>
            </div>

            <div class="control-group">
                <label>Tolerance: <span id="toleranceValue">30</span>%</label>
                <input type="range" id="tolerance" min="0" max="100" value="30" data-on-input="updateToleranceValue">
                <div class="range-value">Lower = more precise, Higher = removes more similar colors</div>
            </div>

            <div class="control-group">
                <label>Edge Softness: <span id="softnessValue">0</span>px</label>
                <input type="range" id="softness" min="0" max="10" value="0" data-on-input="updateSoftnessValue">
                <div class="range-value">Smooths edges of the cutout</div>
            </div>
        </div>

        <div class="btn-group" style="margin-top: 20px;">
            <button class="btn btn-primary" data-on-click="processImage" id="processBtn" disabled>🔄 Remove Background</button>
            <button class="btn btn-success" data-on-click="downloadResult" id="downloadBtn" disabled>💾 Download PNG</button>
            <button class="btn btn-secondary" data-on-click="resetAll">🗑️ Clear</button>
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

<?= View::script('/js/admin/background-remover.js') ?>

<?php require View::path('layouts/admin_footer'); ?>
