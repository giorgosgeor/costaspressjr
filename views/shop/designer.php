<?php $title = t('studio.title', false); ?>
<?php $pageCss[] = '/css/pages/designer.css'; require View::path('layouts/customer_header'); ?>

<?php // The studio's pop-ups (add to cart, design saved, save design). They used to
      // be printed before the layout, ahead of <!DOCTYPE html>, which put the whole
      // page in the browser's quirks mode. ?>
<!-- Add to Cart Modal (with size/color/quantity selection) -->
<div id="addToCartModal" style="display:none; position:fixed; z-index:35000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.25); align-items:center; justify-content:center;" onclick="if(event.target === this) closeAddToCartModal();">
    <div style="background:#fff; border-radius:18px; max-width:520px; width:95vw; margin:auto; box-shadow:0 2px 24px rgba(0,0,0,0.16); padding:2rem; position:relative; max-height:90vh; overflow-y:auto;" onclick="event.stopPropagation();">
        <button onclick="closeAddToCartModal()" style="position:absolute; top:1rem; right:1rem; background:none; border:none; font-size:2rem; color:#888; cursor:pointer;">&times;</button>
        <h2 style="font-size:1.4rem; font-weight:700; margin-bottom:1rem; text-align:center; color:#333;"><?= t('studio.cart.title') ?></h2>
        
        <!-- Design Preview - HTML based for reliability -->
        <div id="cartDesignPreview" style="text-align:center; margin-bottom:1.5rem; background:#f5f5f5; border-radius:12px; padding:1rem; position:relative;">
            <div id="cartPreviewContainer" style="position:relative; width:200px; height:200px; margin:0 auto; overflow:hidden; border-radius:8px;">
                <img id="cartPreviewProduct" src="" alt="Product" style="width:100%; height:100%; object-fit:contain;">
                <div id="cartPreviewDesignArea" style="position:absolute; left:50%; top:25%; width:45%; height:60%; transform:translateX(-50%); overflow:hidden;"></div>
            </div>
            <div id="cartProductName" style="font-weight:600; margin-top:0.5rem; color:#333;"></div>
            <div id="cartDesignName" style="font-size:0.9rem; color:#666;"></div>
        </div>
        
        <!-- Size Selection -->
        <div style="margin-bottom:1.2rem;">
            <label style="font-weight:600; display:block; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                <span><?= t('studio.cart.size') ?></span>
                <a id="studioSizeGuideLink" href="#"
                   onclick="event.preventDefault(); if (window.currentProduct && window.currentProduct.sizeChartImage) openSizeGuide(window.currentProduct.sizeChartImage, window.currentProduct.name || 'Product');"
                   style="display:none; font-size:0.8rem; color:#2A4FE0; text-decoration:none; font-weight:500;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="vertical-align:-2px;margin-right:4px;"><path d="M2 12h20"/><path d="M6 9v6M10 7v10M14 9v6M18 7v10"/></svg>Size guide
                </a>
            </label>
            <div id="cartSizeOptions" style="display:flex; flex-wrap:wrap; gap:8px;"></div>
        </div>
        
        <!-- Color Selection -->
        <div style="margin-bottom:1.2rem;">
            <label style="font-weight:600; display:block; margin-bottom:0.5rem;"><?= t('studio.cart.color') ?></label>
            <div id="cartColorOptions" style="display:flex; flex-wrap:wrap; gap:8px;"></div>
        </div>
        
        <!-- Availability Grid -->
        <div id="availabilityGrid" style="margin-bottom:1.2rem; overflow-x:auto; display:none;">
            <table id="variantTable" style="border-collapse:collapse; width:100%; font-size:0.85rem;">
                <thead id="variantTableHead"></thead>
                <tbody id="variantTableBody"></tbody>
            </table>
        </div>
        
        <!-- Quantity Selector -->
        <div style="margin-bottom:1.5rem;">
            <label style="font-weight:600; display:block; margin-bottom:0.5rem;"><?= t('studio.cart.quantity') ?></label>
            <div style="display:flex; align-items:center; gap:12px;">
                <button onclick="adjustCartQuantity(-1)" style="width:36px; height:36px; background:#eee; border:1px solid #ddd; border-radius:8px; font-size:1.2rem; cursor:pointer;">−</button>
                <input id="cartQuantity" type="number" value="1" min="1" max="100" style="width:60px; text-align:center; padding:8px; border:1px solid #ddd; border-radius:8px; font-size:1rem;">
                <button onclick="adjustCartQuantity(1)" style="width:36px; height:36px; background:#eee; border:1px solid #ddd; border-radius:8px; font-size:1.2rem; cursor:pointer;">+</button>
            </div>
        </div>
        
        <!-- Price Summary -->
        <div id="cartPriceSummary" style="background:#f9f9f9; padding:1rem; border-radius:10px; margin-bottom:1.2rem;">
            <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                <span><?= t('studio.cart.base_price') ?></span>
                <span id="cartBasePrice">€0.00</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                <span><?= t('studio.cart.design_fee') ?></span>
                <span id="cartDesignFee">€0.00</span>
            </div>
            <div style="display:flex; justify-content:space-between; font-weight:700; border-top:1px solid #ddd; padding-top:0.5rem; margin-top:0.5rem;">
                <span><?= t('studio.cart.total') ?></span>
                <span id="cartTotalPrice">€0.00</span>
            </div>
        </div>
        
        <!-- Error Message -->
        <div id="cartError" style="display:none; color:#dc3545; text-align:center; margin-bottom:1rem; font-size:0.95rem;"></div>
        
        <!-- Add to Cart Button -->
        <button id="confirmAddToCartBtn" style="width:100%; background:#2d5fff; color:#fff; font-weight:600; font-size:1.1rem; padding:12px 0; border:none; border-radius:8px; cursor:pointer; margin-bottom:0.7rem;"><?= t('studio.cart.add') ?></button>

        <!-- Go to Checkout Button -->
        <button id="goToCheckoutFromCartBtn" onclick="window.location.href='/cart'" style="width:100%; background:#28a745; color:#fff; font-weight:600; font-size:1rem; padding:10px 0; border:none; border-radius:8px; cursor:pointer;"><?= t('studio.cart.checkout') ?></button>
    </div>
</div>

<?= View::script('/js/pages/designer/cart-modal.js') ?>

<!-- Design Saved Success Modal -->
<div id="designSavedModal" style="display:none; position:fixed; z-index:30000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:18px; max-width:420px; width:92vw; margin:auto; box-shadow:0 2px 24px rgba(0,0,0,0.13); padding:2.2rem 2.2rem 1.5rem 2.2rem; position:relative; text-align:center;">
        <button onclick="closeDesignSavedModal()" style="position:absolute; top:1.1rem; right:1.1rem; background:none; border:none; font-size:2rem; color:#888; cursor:pointer;">&times;</button>
        <h2 style="font-size:1.5rem; font-weight:700; margin-bottom:0.7rem; color:#2d5fff;"><?= t('studio.saved.title') ?></h2>
        <div style="font-size:1.08rem; color:#444; margin-bottom:1.2rem;"><?= t('studio.saved.lead') ?></div>
        <button id="addToCartNowBtn" style="background:#2d5fff; color:#fff; font-weight:600; font-size:1.1rem; padding:12px 32px; border-radius:8px; border:none; margin-bottom:0.7rem; cursor:pointer; width:100%;"><?= t('studio.saved.add_to_cart') ?></button>
        <button onclick="closeDesignSavedModal(); window.location.href='/';" style="background:#eee; color:#666; font-weight:600; font-size:1rem; padding:10px 24px; border-radius:8px; border:none; cursor:pointer; width:100%;"><?= t('studio.saved.exit') ?></button>
    </div>
</div>
<!-- Save Design Modal -->
<div id="saveDesignModal" style="display:none; position:fixed; z-index:20000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:18px; max-width:420px; width:95vw; margin:auto; box-shadow:0 2px 24px rgba(0,0,0,0.13); padding:2.2rem 2.2rem 1.5rem 2.2rem; position:relative;">
        <button onclick="closeSaveDesignModal()" style="position:absolute; top:1.1rem; right:1.1rem; background:none; border:none; font-size:2rem; color:#888; cursor:pointer;">&times;</button>
        <h2 id="saveModalTitle" style="font-size:2rem; font-weight:700; margin-bottom:0.5rem; text-align:center;"><?= t('studio.save_modal.title') ?></h2>
        <div style="text-align:center; color:#444; font-size:1.08rem; margin-bottom:1.2rem;"><?= t('studio.save_modal.subtitle') ?></div>
        
        <!-- Editing mode: Show update options -->
        <div id="saveEditingMode" style="display:none; margin-bottom:1.5rem;">
            <div style="background:#f0f4ff; border-radius:10px; padding:1rem; margin-bottom:1rem;">
                <div style="font-weight:600; color:#333; margin-bottom:0.3rem;"><?= t('studio.save_modal.editing_prefix') ?> <span id="editingDesignName"></span></div>
                <div style="font-size:0.9rem; color:#666;"><?= t('studio.save_modal.editing_note') ?></div>
            </div>
            <button id="updateDesignBtn" type="button" style="width:100%;background:#2d5fff;color:#fff;font-size:1.13rem;font-weight:600;padding:12px 0;border:none;border-radius:8px;cursor:pointer;margin-bottom:0.8rem;"><?= t('studio.save_modal.update') ?></button>
            <button id="deleteDesignBtn" type="button" style="width:100%;background:#dc3545;color:#fff;font-size:1rem;font-weight:600;padding:10px 0;border:none;border-radius:8px;cursor:pointer;margin-bottom:0.8rem;"><?= t('studio.save_modal.delete') ?></button>
            <div style="text-align:center; color:#888; font-size:0.9rem; margin-bottom:0.8rem;"><?= t('studio.save_modal.or') ?></div>
        </div>
        
        <!-- Login required notice (shown when guest tries to save) -->
        <div id="saveLoginNotice" style="display:none; background:#fff3cd; border:1.5px solid #ffc107; border-radius:10px; padding:1rem 1.2rem; margin-bottom:1.2rem; text-align:center;">
            <div style="font-size:1rem; font-weight:600; color:#856404; margin-bottom:0.5rem;"><?= t('studio.save_modal.login_title') ?></div>
            <div style="font-size:0.9rem; color:#856404; margin-bottom:0.8rem;"><?= t('studio.save_modal.login_note') ?></div>
            <a href="/login" target="_blank" onclick="this.closest('#saveLoginNotice').querySelector('.retry-hint').style.display='block'" style="display:inline-block; background:#2d5fff; color:#fff; font-weight:600; padding:8px 24px; border-radius:7px; text-decoration:none; font-size:1rem;"><?= t('studio.save_modal.login_btn') ?></a>
            <div class="retry-hint" style="display:none; margin-top:0.7rem; font-size:0.88rem; color:#555;"><?= t('studio.save_modal.login_retry', false) ?></div>
        </div>

        <div id="saveNewMode">
            <div style="margin-bottom:1.1rem;">
                <label for="saveDesignName" style="font-weight:500;"><?= t('studio.save_modal.name_label') ?></label>
                <input id="saveDesignName" maxlength="25" placeholder="<?= t('studio.save_modal.name_placeholder') ?>" style="width:100%;margin-top:6px;padding:8px 10px;font-size:1rem;border:1.5px solid #ddd;border-radius:7px;">
                <div style="font-size:0.92rem;color:#888;margin-top:2px;"><?= t('studio.save_modal.name_hint') ?></div>
            </div>
            <div style="margin-bottom:1.1rem;">
                <label for="saveDesignEmail" style="font-weight:500;"><?= t('studio.save_modal.email_label') ?></label>
                <input id="saveDesignEmail" type="email" placeholder="<?= t('studio.save_modal.email_placeholder') ?>" style="width:100%;margin-top:6px;padding:8px 10px;font-size:1rem;border:1.5px solid #ddd;border-radius:7px;">
            </div>
            <div style="margin-bottom:1.1rem;display:flex;align-items:center;gap:8px;">
                <input id="saveDesignPrivacy" type="checkbox" style="width:18px;height:18px;">
                <label for="saveDesignPrivacy" style="font-size:0.98rem;"><?= t('studio.save_modal.privacy', false, ['link' => '<a href="/privacy" style="color:#2d5fff;" target="_blank">' . t('studio.save_modal.privacy_link', false) . '</a>']) ?></label>
            </div>
            <button id="saveDesignModalBtn" type="button" style="width:100%;background:#eee;color:#aaa;font-size:1.13rem;font-weight:600;padding:12px 0;border:none;border-radius:8px;cursor:not-allowed;margin-bottom:1.2rem;"><?= t('studio.save_modal.save_new') ?></button>
        </div>
    </div>
</div>
<?= View::script('/js/pages/designer/save-design.js') ?>

<!-- Interact.js for drag & resize -->
<script src="/js/vendor/interact.min.js"></script>

<section class="section custom-design-section">
    <div class="container">
        <?php
            $crumbs  = [[t('header.nav.home', false), '/'], [t('header.nav.shop', false), '/shop'], [t('studio.title', false), null]];
            $heading = t('studio.title', false);
            $lead    = t('studio.subtitle', false);
            require View::path('partials/page_head');
        ?>

        <?php if (empty($products)): ?>
            <div class="no-products-message">
                <p><?= t('studio.no_products') ?></p>
            </div>
        <?php else: ?>

        <?php // ---- Phone-only studio chrome -------------------------------
              // On a phone the tools column cannot sit beside the garment, and
              // stacking it pushed the mockup - the thing being designed - most
              // of a screen down the page, under a wall of controls. The tools
              // move into a drawer instead, opened from a slim tab on the left
              // edge, so the garment is what you see when the page loads.
              // Both are inert above 900px, where the two-column layout works. ?>
        <button type="button" class="studio-tools-tab" id="studioToolsTab"
                aria-controls="studioControls" aria-expanded="false"
                aria-label="<?= t('studio.tools_open') ?>"
                data-label-open="<?= t('studio.tools_open') ?>"
                data-label-close="<?= t('studio.tools_close') ?>">
            <span class="studio-tools-tab-arrow" aria-hidden="true">&raquo;</span>
            <span class="studio-tools-tab-label"><?= t('studio.tools') ?></span>
        </button>
        <div class="studio-scrim" id="studioScrim" hidden></div>

        <div class="custom-studio-layout">
            <!-- Preview Area (visually on right via CSS order) -->
            <div class="studio-preview">
                <!-- Placement switcher: dots (left) + current view name (right).
                     The dots are the original view buttons restyled — same ids,
                     data-view and .active handling, so existing JS is unchanged. -->
                <div class="view-toggle" id="viewToggle">
                    <div class="view-dots" id="viewDots" role="tablist" aria-label="<?= t('view_design.placement') ?>">
                        <button type="button" class="view-btn active" data-view="front" data-label="<?= t('studio.view.front') ?>" aria-label="<?= t('studio.view.front') ?>" title="<?= t('studio.view.front') ?>"></button>
                        <button type="button" class="view-btn" data-view="back" data-label="<?= t('studio.view.back') ?>" aria-label="<?= t('studio.view.back') ?>" title="<?= t('studio.view.back') ?>"></button>
                        <button type="button" class="view-btn" data-view="left-sleeve" id="leftSleeveBtn" style="display: none;" data-label="<?= t('studio.view.left_sleeve') ?>" aria-label="<?= t('studio.view.left_sleeve') ?>" title="<?= t('studio.view.left_sleeve') ?>"></button>
                        <button type="button" class="view-btn" data-view="right-sleeve" id="rightSleeveBtn" style="display: none;" data-label="<?= t('studio.view.right_sleeve') ?>" aria-label="<?= t('studio.view.right_sleeve') ?>" title="<?= t('studio.view.right_sleeve') ?>"></button>
                    </div>
                    <span class="view-current-label" id="viewCurrentLabel" aria-live="polite"><?= t('studio.view.front') ?></span>
                </div>

                <div class="mockup-container" id="mockupContainer">
                    <!-- Product image -->
                    <!-- Hidden until a product is chosen: an empty src renders a
                         broken-image icon next to its alt text. -->
                    <img src="" alt="" class="mockup-product" id="mockupProduct" style="display:none;">
                    <div class="mockup-placeholder" id="mockupPlaceholder"><?= t('studio.placeholder') ?></div>
                    
                    <!-- Design area -->
                    <div class="design-area" id="designArea">
                        <div class="design-area-label" id="designAreaLabel"><?= t('studio.design_area') ?></div>
                        <!-- Design elements will be added here dynamically -->
                    </div>
                </div>

                <!-- Product Color & Size Selection -->
                <div class="product-options-panel" id="productOptionsPanel" style="display:none; background:#fff; border-radius:12px; padding:1rem; margin-top:1rem; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                    <h4 style="margin:0 0 0.75rem 0; font-size:1rem; color:#333;"><?= t('studio.panel.color') ?></h4>
                    <div id="studioColorSwatches" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:1rem;"></div>

                    <h4 style="margin:0 0 0.5rem 0; font-size:1rem; color:#333;"><?= t('studio.panel.sizes') ?></h4>
                    <div id="studioAvailableSizes" style="display:flex; flex-wrap:wrap; gap:6px;">
                        <span style="color:#888; font-size:0.9rem;"><?= t('studio.panel.select_color_first') ?></span>
                    </div>
                </div>

                <!-- Layer Controls -->
                <div class="layer-controls">
                    <h4><?= t('studio.layers.title') ?></h4>
                    <div class="layer-list" id="layerList">
                        <div class="layer-empty"><?= t('studio.layers.empty') ?></div>
                    </div>
                </div>
            </div>

            <!-- Controls Panel (visually on left via CSS order) -->
            <div class="studio-controls" id="studioControls">
               
               

                <!-- Move Text Editor Modal inside Whats Next panel -->
                <div class="whats-next-panel">
                    <!-- Upload Editor Modal -->
                    <div id="uploadEditorModal" class="upload-editor-modal" style="display:none;">
                        <div class="upload-editor-content">
                            <button class="upload-editor-close" onclick="closeUploadEditor()">&times;</button>
                            <h2 style="font-size:1.1rem;margin-bottom:0.7rem;padding-right:2.5rem;"><?= t('studio.upload.title') ?></h2>
                            <div id="uploadDropArea" class="upload-drop-area" style="padding:0.7rem;" ondrop="handleUploadDrop(event)" ondragover="event.preventDefault();this.classList.add('dragover');" ondragleave="this.classList.remove('dragover');">
                                <button type="button" class="upload-browse-btn" onclick="document.getElementById('uploadFileInput').click()"><?= t('studio.upload.browse') ?></button>
                                <div class="upload-or" style="font-size:0.85rem;margin:0.3rem 0;"><?= t('studio.upload.drag', false) ?></div>
                                <input type="file" id="uploadFileInput" accept="image/*" style="display:none;" onchange="handleUploadFile(this.files)">
                            </div>
                            <div class="upload-hint" style="font-size:0.78rem;color:#999;margin-top:0.4rem;text-align:center;"><?= t('studio.upload.hint') ?></div>
                            <div id="uploadRecentList" style="display:none;margin-top:0.7rem;width:100%;min-width:0;box-sizing:border-box;">
                                <div style="font-size:0.8rem;font-weight:600;color:#555;margin-bottom:0.3rem;"><?= t('studio.upload.recent') ?></div>
                                <div class="upload-recent-list" id="uploadRecentStrip"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Text Editor Modal (styled like upload) -->
                    <div id="textEditorModal" class="upload-editor-modal" style="display:none;">
                        <div class="upload-editor-content">
                            <button class="upload-editor-close" onclick="hideTextEditor()">&times;</button>
                            <h2 id="textEditorTitle" style="margin-bottom:18px;"><?= t('studio.text.title') ?></h2>
                            <div class="option-group" style="margin-bottom:14px;">
                                <label for="textContent" style="font-weight:500;"><?= t('studio.text.label') ?>:</label>
                                <input type="text" id="textContent" placeholder="<?= t('studio.text.placeholder') ?>" maxlength="50" style="width:100%;margin-top:6px;">
                            </div>
                            <div class="option-group" style="margin-bottom:14px;">
                                <label for="fontFamily" style="font-weight:500;"><?= t('studio.text.font') ?>:</label>
                                <select id="fontFamily" style="width:100%;margin-top:6px;">
                                    <option value="Arial, sans-serif" selected>Arial</option>
                                    <option value="'Times New Roman', serif">Times New Roman</option>
                                    <option value="'Courier New', monospace">Courier New</option>
                                    <option value="Georgia, serif">Georgia</option>
                                    <option value="Verdana, sans-serif">Verdana</option>
                                    <option value="'Trebuchet MS', sans-serif">Trebuchet MS</option>
                                    <option value="Impact, sans-serif">Impact</option>
                                    <option value="'Lucida Console', monospace">Lucida Console</option>
                                </select>
                            </div>
                            <div style="display:flex; gap:16px; margin-bottom:14px;">
                                <div style="flex:1;">
                                    <label for="fontSize" style="font-weight:500;"><?= t('studio.text.size') ?>:</label>
                                    <input type="range" id="fontSize" min="12" max="200" value="24" style="width:100%;margin-top:6px;">
                                    <span id="fontSizeDisplay">24px</span>
                                </div>
                                <div style="flex:1;">
                                    <label for="textColor" style="font-weight:500;"><?= t('studio.text.color') ?>:</label>
                                    <input type="color" id="textColor" value="#000000" style="width:100%;margin-top:6px;">
                                </div>
                            </div>
                            <div class="option-group" style="margin-bottom:14px;">
                                <label style="font-weight:500;"><?= t('studio.text.style') ?>:</label>
                                <div class="style-buttons" style="margin-top:6px;display:flex;gap:10px;">
                                    <button type="button" class="img-edit-btn" id="boldBtn" title="Bold"><b>B</b></button>
                                    <button type="button" class="img-edit-btn" id="italicBtn" title="Italic"><i>/</i></button>
                                    <button type="button" class="img-edit-btn" id="underlineBtn" title="Underline"><u>U</u></button>
                                </div>
                            </div>
                            <div style="display:flex; gap:10px; margin-top:18px;">
                                <button type="button" class="img-edit-btn" style="flex:1; background:#2d5fff; color:#fff;" id="applyTextBtn"><?= t('studio.text.apply') ?></button>
                                <button type="button" class="img-edit-btn" style="flex:1; background:#eee; color:#888;" id="cancelTextBtn"><?= t('studio.text.cancel') ?></button>
                            </div>
                        </div>
                    </div>
                    <h2 class="whats-next-title"><?= t('studio.whats_next') ?></h2>
                    <div class="whats-next-actions">
                        <div id="imageEditorPanel" style="display:none;">
                            <div class="upload-editor-content">
                                <button class="upload-editor-close" onclick="hideImageEditor()">&times;</button>
                                <h2 style="margin-bottom:18px;"><?= t('studio.image_edit.title') ?></h2>
                                <div style="margin-bottom:12px;">
                                    <div style="font-size:13px; color:#888;"><?= t('studio.image_edit.size') ?></div>
                                    <div style="display:flex; gap:8px; align-items:center; margin-top:2px;">
                                        <input id="imgEditWidth" type="number" min="0.1" step="0.01" style="width:60px;" onchange="updateImageSize('width')"> in ×
                                        <input id="imgEditHeight" type="number" min="0.1" step="0.01" style="width:60px;" onchange="updateImageSize('height')"> in
                                    </div>
                                </div>
                                <div style="margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                                    <label style="font-size:13px;"><?= t('studio.image_edit.color') ?></label>
                                    <input id="imgEditColor" type="color" onchange="updateImageColor()">
                                </div>
                                <div style="margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                                    <!-- Background remover removed for customers -->
                                </div>
                                <?= View::script('/js/pages/designer/image-editor.js') ?>
                                <hr style="margin:12px 0;">
                                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
                                    <button class="img-edit-btn" onclick="centerImage()"><?= t('studio.image_edit.center') ?></button>
                                    <button class="img-edit-btn" onclick="layerImage('up')"><?= t('studio.image_edit.layer') ?></button>
                                    <button class="img-edit-btn" onclick="flipImage()"><?= t('studio.image_edit.flip') ?></button>
                                    <button class="img-edit-btn" onclick="duplicateImage()"><?= t('studio.image_edit.duplicate') ?></button>
                                    <button class="img-edit-btn" onclick="cropImage()"><?= t('studio.image_edit.crop') ?></button>
                                </div>
                                <!-- Save Design button moved to main panel below -->
                                <div style="margin-bottom:10px;">
                                    <label style="font-size:13px;"><?= t('studio.image_edit.rotation') ?></label>
                                    <input id="imgEditRotation" type="range" min="0" max="360" value="0" style="width:140px; vertical-align:middle;" oninput="updateImageRotation()">
                                    <input id="imgEditRotationVal" type="number" min="0" max="360" value="0" style="width:48px;" oninput="updateImageRotation()">
                                </div>
                                <div style="display:flex; gap:10px; margin-top:10px;">
                                    <button class="img-edit-btn" style="flex:1; background:#eee; color:#888;" onclick="resetImageEdit()"><?= t('studio.image_edit.reset') ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                        <div class="whats-next-action" onclick="openUploadEditor()">
                            <div class="whats-next-icon">
                                <!-- Upload Icon -->
                                <svg width="48" height="48" fill="none" stroke="#15130E" stroke-width="2" viewBox="0 0 48 48"><path d="M24 34V14M24 14l-8 8M24 14l8 8"/><rect x="8" y="36" width="32" height="6" rx="3"/></svg>
                            </div>
                            <div class="whats-next-label"><?= t('studio.action.uploads') ?></div>
                        </div>
                        <div class="whats-next-action" onclick="triggerWhatsNextAddText()">
                            <!-- Hidden input for uploads (for Whats Next panel) -->
                            <div class="whats-next-icon">
                                <!-- Text Icon -->
                                <svg width="48" height="48" fill="none" stroke="#15130E" stroke-width="2" viewBox="0 0 48 48"><text x="8" y="36" font-size="28" font-family="Arial" fill="#15130E">abc</text></svg>
                            </div>
                            <div class="whats-next-label"><?= t('studio.action.add_text') ?></div>
                        </div>
                        <div class="whats-next-action" onclick="openChangeColorModal()">
                            <div class="whats-next-icon">
                                <!-- Palette Icon -->
                                <svg width="48" height="48" fill="none" stroke="#15130E" stroke-width="2" viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="#f8fafd" stroke="#15130E"/><circle cx="16" cy="20" r="3" fill="#15130E"/><circle cx="32" cy="20" r="3" fill="#15130E"/><circle cx="24" cy="32" r="3" fill="#15130E"/></svg>
                            </div>
                            <div class="whats-next-label"><?= t('studio.action.change_color') ?></div>
                        </div>
                        <div class="whats-next-action" onclick="window.location.href='/shop/select_product'">
                            <div class="whats-next-icon">
                                <!-- Change Products Icon -->
                                <svg width="48" height="48" fill="none" stroke="#15130E" stroke-width="2" viewBox="0 0 48 48"><rect x="10" y="16" width="28" height="20" rx="4"/><path d="M14 16V12a4 4 0 014-4h12a4 4 0 014 4v4"/><circle cx="24" cy="26" r="4"/></svg>
                            </div>
                            <div class="whats-next-label"><?= t('studio.action.change_product') ?></div>
                        </div>
                    </div>
                    <!-- Save Design + Add to Cart. Stacked, full width: this
                         column is only 220px wide, so side-by-side buttons wrap
                         their labels onto three lines each. -->
                    <div class="studio-actions">
                        <button id="addToCartDirectBtn" class="studio-action studio-action-primary"><?= t('studio.saved.add_to_cart') ?></button>
                        <button id="saveDesignBtn" class="studio-action studio-action-secondary" onclick="openSaveDesignModal()"><?= t('studio.save_design') ?></button>
                    </div>
                    <!-- Change Color Modal -->
                    <div id="changeColorModal" class="change-color-modal" style="display:none;" onclick="if(event.target === this) closeChangeColorModal();">
                        <div class="change-color-modal-content" onclick="event.stopPropagation();">
                            <button class="change-color-modal-close" onclick="closeChangeColorModal()">&times;</button>
                            <h2><?= t('studio.color_modal.title') ?></h2>
                            <div id="changeColorOptions"></div>
                        </div>
                    </div>
                    
                    <?= View::script('/js/pages/designer/save-design-modal.js') ?>
                </div>
 
            </div>
        </div>

        <?php endif; ?>
    </div>
</section>


<?= View::json('designer-data', ['products' => $products, 'currentUserId' => Auth::check() ? (int)Auth::userId() : null, 'loadDesign' => (isset($loadDesign) && $loadDesign) ? $loadDesign : null]) ?>
<?= View::script('/js/pages/designer/studio.js') ?>

<?php require View::path('partials/size_guide_modal'); ?>
<?php // ---- Phone-only action bar --------------------------------------
      // Save and Add to Cart pinned under the design rather than sitting
      // above it. These do NOT reimplement anything: each forwards its tap
      // to the button already in the tools column, so there is one code
      // path for saving and one for adding to cart. The originals are
      // hidden by CSS below 900px, not removed, which is what keeps that
      // forwarding valid. ?>
<div class="studio-mobile-actions" id="studioMobileActions">
    <button type="button" class="studio-action studio-action-primary" id="mobAddToCart"><?= t('studio.saved.add_to_cart') ?></button>
    <button type="button" class="studio-action studio-action-secondary" id="mobSaveDesign"><?= t('studio.save_design') ?></button>
</div>

<?= View::script('/js/pages/designer/mobile-bar.js') ?>

<?php require View::path('layouts/customer_footer'); ?>
