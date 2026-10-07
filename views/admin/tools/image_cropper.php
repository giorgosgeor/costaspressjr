<?php $title = 'Image Cropper - Admin'; ?>
<?php $extraCss[] = '/css/admin/image-cropper.css'; require View::path('layouts/admin_header'); ?>


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

<?= View::script('/js/admin/image-cropper.js') ?>

<?php require View::path('layouts/admin_footer'); ?>
