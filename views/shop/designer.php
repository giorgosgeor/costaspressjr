<?php $title = t('studio.title', false); ?>
<?php $pageCss[] = '/css/pages/designer.css'; require View::path('layouts/customer_header'); ?>

<?= View::script('/js/pages/designer/cart-modal.js') ?>
<?= View::script('/js/pages/designer/save-design.js') ?>

<?php // The studio's pop-ups (add to cart, design saved, save design). The footer
      // prints them after </main> (see $overlays). They used to be printed before
      // the layout, ahead of <!DOCTYPE html>, which put the page in quirks mode.
ob_start(); ?>
<?php // All three use the shared pop-up classes in studio.css (.popup-overlay,
      // .popup, .popup-title …) — the same look as the premade design page's and
      // the account page's add-to-cart pop-ups. Inline styles left here are state
      // the scripts flip (display) or positions of the preview layers. ?>
<!-- Add to Cart Modal (with size/color/quantity selection) -->
<div id="addToCartModal" class="popup-overlay" style="display:none;" data-on-click="closeAddToCartModal" data-click-self>
    <div class="popup" role="dialog" aria-modal="true" aria-labelledby="studioCartTitle" data-stop-click>
        <button type="button" class="popup-close" data-on-click="closeAddToCartModal" aria-label="<?= t('common.close') ?>">&times;</button>
        <h2 class="popup-title" id="studioCartTitle"><?= t('studio.cart.title') ?></h2>

        <!-- Design Preview - HTML based for reliability -->
        <div id="cartDesignPreview" class="popup-stage">
            <div id="cartPreviewContainer" class="studio-cart-preview">
                <img id="cartPreviewProduct" src="" alt="Product">
                <div id="cartPreviewDesignArea"></div>
            </div>
            <div id="cartProductName" class="popup-stage-name"></div>
            <div id="cartDesignName" class="popup-stage-meta"></div>
            <?php // The colour picked in the studio, locked here. ?>
            <div id="cartColorLine" class="popup-stage-meta popup-color-line"></div>
            <p class="popup-note"><?= t('size_qty.color_locked') ?></p>
        </div>

        <?php $sizeQtyLabelExtra = '<a id="studioSizeGuideLink" href="#" class="size-guide-link" style="display:none;" data-on-click="openCurrentProductSizeGuide" data-prevent-default>'
            . '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12h20"/><path d="M6 9v6M10 7v10M14 9v6M18 7v10"/></svg>'
            . t('footer.size_guide') . '</a>';
        require View::path('partials/size_qty_fields');
        unset($sizeQtyLabelExtra); ?>

        <div id="cartError" class="popup-error" style="display:none;"></div>

        <div class="popup-actions">
            <button type="button" id="confirmAddToCartBtn" class="btn btn-lg"><?= t('studio.cart.add') ?></button>
            <button type="button" id="goToCheckoutFromCartBtn" class="btn btn-lg btn-secondary" data-href="/cart"><?= t('view_design.modal.go_cart') ?></button>
        </div>
    </div>
</div>

<!-- Design Saved Success Modal -->
<div id="designSavedModal" class="popup-overlay" style="display:none;">
    <div class="popup popup-narrow" role="dialog" aria-modal="true" aria-labelledby="designSavedTitle">
        <button type="button" class="popup-close" data-on-click="closeDesignSavedModal" aria-label="<?= t('common.close') ?>">&times;</button>
        <h2 class="popup-title" id="designSavedTitle"><?= t('studio.saved.title') ?></h2>
        <p class="popup-lead"><?= t('studio.saved.lead') ?></p>
        <div class="popup-actions">
            <button type="button" id="addToCartNowBtn" class="btn btn-lg"><?= t('studio.saved.add_to_cart') ?></button>
            <button type="button" class="btn btn-lg btn-secondary" data-on-click="closeDesignSavedModal" data-href="/"><?= t('studio.saved.exit') ?></button>
        </div>
    </div>
</div>
<!-- Save Design Modal -->
<div id="saveDesignModal" class="popup-overlay" style="display:none;">
    <div class="popup popup-narrow" role="dialog" aria-modal="true" aria-labelledby="saveModalTitle">
        <button type="button" class="popup-close" data-on-click="closeSaveDesignModal" aria-label="<?= t('common.close') ?>">&times;</button>
        <h2 id="saveModalTitle" class="popup-title"><?= t('studio.save_modal.title') ?></h2>
        <p class="popup-lead"><?= t('studio.save_modal.subtitle') ?></p>

        <!-- Editing mode: Show update options -->
        <div id="saveEditingMode" style="display:none;">
            <div class="popup-panel">
                <div class="popup-stage-name"><?= t('studio.save_modal.editing_prefix') ?> <span id="editingDesignName"></span></div>
                <div class="popup-stage-meta"><?= t('studio.save_modal.editing_note') ?></div>
            </div>
            <div class="popup-actions">
                <button id="updateDesignBtn" type="button" class="btn btn-lg"><?= t('studio.save_modal.update') ?></button>
                <?php // Destructive, so the red outline — never as loud as Update. ?>
                <button id="deleteDesignBtn" type="button" class="btn btn-lg btn-danger"><?= t('studio.save_modal.delete') ?></button>
            </div>
            <p class="popup-divider"><?= t('studio.save_modal.or') ?></p>
        </div>

        <!-- Login required notice (shown when guest tries to save) -->
        <div id="saveLoginNotice" class="popup-panel popup-notice" style="display:none;">
            <p class="popup-stage-name"><?= t('studio.save_modal.login_title') ?></p>
            <p class="popup-stage-meta"><?= t('studio.save_modal.login_note') ?></p>
            <a href="/login" target="_blank" class="btn" data-on-click="showSaveRetryHint" data-args='["$this"]'><?= t('studio.save_modal.login_btn') ?></a>
            <p class="retry-hint popup-note" style="display:none;"><?= t('studio.save_modal.login_retry', false) ?></p>
        </div>

        <div id="saveNewMode">
            <div class="form-group">
                <label for="saveDesignName"><?= t('studio.save_modal.name_label') ?></label>
                <input id="saveDesignName" maxlength="25" placeholder="<?= t('studio.save_modal.name_placeholder') ?>">
                <p class="popup-note"><?= t('studio.save_modal.name_hint') ?></p>
            </div>
            <div class="form-group">
                <label for="saveDesignEmail"><?= t('studio.save_modal.email_label') ?></label>
                <input id="saveDesignEmail" type="email" placeholder="<?= t('studio.save_modal.email_placeholder') ?>">
            </div>
            <div class="popup-check">
                <input id="saveDesignPrivacy" type="checkbox">
                <label for="saveDesignPrivacy"><?= t('studio.save_modal.privacy', false, ['link' => '<a href="/privacy" target="_blank">' . t('studio.save_modal.privacy_link', false) . '</a>']) ?></label>
            </div>
            <div class="popup-actions">
                <button id="saveDesignModalBtn" type="button" class="btn btn-lg" disabled><?= t('studio.save_modal.save_new') ?></button>
            </div>
        </div>
    </div>
</div>
<?php $overlays = ob_get_clean(); ?>

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
                <div class="product-options-panel" id="productOptionsPanel" style="display:none;">
                    <h4><?= t('studio.panel.color') ?></h4>
                    <div id="studioColorSwatches" class="studio-swatch-row"></div>

                    <h4><?= t('studio.panel.sizes') ?></h4>
                    <div id="studioAvailableSizes" class="studio-size-row">
                        <span class="studio-empty-note"><?= t('studio.panel.select_color_first') ?></span>
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
                            <button class="upload-editor-close" data-on-click="closeUploadEditor">&times;</button>
                            <h2 style="font-size:1.1rem;margin-bottom:0.7rem;padding-right:2.5rem;"><?= t('studio.upload.title') ?></h2>
                            <div id="uploadDropArea" class="upload-drop-area" style="padding:0.7rem;" data-on-drop="handleUploadDrop" data-args='["$event"]' data-on-dragover="uploadDragOver" data-args='["$event", "$this"]' data-on-dragleave="uploadDragLeave" data-args='["$this"]'>
                                <button type="button" class="upload-browse-btn" data-on-click="openUploadPicker"><?= t('studio.upload.browse') ?></button>
                                <div class="upload-or" style="font-size:0.85rem;margin:0.3rem 0;"><?= t('studio.upload.drag', false) ?></div>
                                <input type="file" id="uploadFileInput" accept="image/*" style="display:none;" data-on-change="handleUploadFile" data-args='["$files"]'>
                            </div>
                            <div class="upload-hint"><?= t('studio.upload.hint') ?></div>
                            <div id="uploadRecentList" style="display:none;margin-top:0.7rem;width:100%;min-width:0;box-sizing:border-box;">
                                <div class="upload-recent-title"><?= t('studio.upload.recent') ?></div>
                                <div class="upload-recent-list" id="uploadRecentStrip"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Text Editor Modal (styled like upload) -->
                    <div id="textEditorModal" class="upload-editor-modal" style="display:none;">
                        <div class="upload-editor-content">
                            <button class="upload-editor-close" data-on-click="hideTextEditor">&times;</button>
                            <h2 id="textEditorTitle" style="margin-bottom:18px;"><?= t('studio.text.title') ?></h2>
                            <div class="option-group" style="margin-bottom:14px;">
                                <label for="textContent" style="font-weight:500;"><?= t('studio.text.label') ?></label>
                                <input type="text" id="textContent" placeholder="<?= t('studio.text.placeholder') ?>" maxlength="50" style="width:100%;margin-top:6px;">
                            </div>
                            <div class="option-group" style="margin-bottom:14px;">
                                <label for="fontFamily" style="font-weight:500;"><?= t('studio.text.font') ?></label>
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
                                    <label for="fontSize" style="font-weight:500;"><?= t('studio.text.size') ?></label>
                                    <input type="range" id="fontSize" min="12" max="200" value="24" style="width:100%;margin-top:6px;">
                                    <span id="fontSizeDisplay">24px</span>
                                </div>
                                <div style="flex:1;">
                                    <label for="textColor" style="font-weight:500;"><?= t('studio.text.color') ?></label>
                                    <input type="color" id="textColor" value="#000000" style="width:100%;margin-top:6px;">
                                </div>
                            </div>
                            <div class="option-group" style="margin-bottom:14px;">
                                <label style="font-weight:500;"><?= t('studio.text.style') ?></label>
                                <div class="style-buttons" style="margin-top:6px;display:flex;gap:10px;">
                                    <button type="button" class="img-edit-btn" id="boldBtn" title="Bold"><b>B</b></button>
                                    <button type="button" class="img-edit-btn" id="italicBtn" title="Italic"><i>/</i></button>
                                    <button type="button" class="img-edit-btn" id="underlineBtn" title="Underline"><u>U</u></button>
                                </div>
                            </div>
                            <div style="display:flex; gap:10px; margin-top:18px;">
                                <button type="button" class="btn btn-block" id="applyTextBtn"><?= t('studio.text.apply') ?></button>
                                <button type="button" class="btn btn-block btn-secondary" id="cancelTextBtn"><?= t('studio.text.cancel') ?></button>
                            </div>
                        </div>
                    </div>
                    <h2 class="whats-next-title"><?= t('studio.whats_next') ?></h2>
                    <div class="whats-next-actions">
                        <div id="imageEditorPanel" style="display:none;">
                            <div class="upload-editor-content">
                                <button class="upload-editor-close" data-on-click="hideImageEditor">&times;</button>
                                <h2 style="margin-bottom:18px;"><?= t('studio.image_edit.title') ?></h2>
                                <div style="margin-bottom:12px;">
                                    <div class="studio-field-caption"><?= t('studio.image_edit.size') ?></div>
                                    <div style="display:flex; gap:8px; align-items:center; margin-top:2px;">
                                        <input id="imgEditWidth" type="number" min="0.1" step="0.01" style="width:60px;" data-on-change="updateImageSize" data-args='["width"]'> in ×
                                        <input id="imgEditHeight" type="number" min="0.1" step="0.01" style="width:60px;" data-on-change="updateImageSize" data-args='["height"]'> in
                                    </div>
                                </div>
                                <div style="margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                                    <label style="font-size:13px;"><?= t('studio.image_edit.color') ?></label>
                                    <input id="imgEditColor" type="color" data-on-change="updateImageColor">
                                </div>
                                <div style="margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                                    <!-- Background remover removed for customers -->
                                </div>
                                <?= View::script('/js/pages/designer/image-editor.js') ?>
                                <hr style="margin:12px 0;">
                                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
                                    <button class="img-edit-btn" data-on-click="centerImage"><?= t('studio.image_edit.center') ?></button>
                                    <button class="img-edit-btn" data-on-click="layerImage" data-args='["up"]'><?= t('studio.image_edit.layer') ?></button>
                                    <button class="img-edit-btn" data-on-click="flipImage"><?= t('studio.image_edit.flip') ?></button>
                                    <button class="img-edit-btn" data-on-click="duplicateImage"><?= t('studio.image_edit.duplicate') ?></button>
                                    <button class="img-edit-btn" data-on-click="cropImage"><?= t('studio.image_edit.crop') ?></button>
                                </div>
                                <!-- Save Design button moved to main panel below -->
                                <div style="margin-bottom:10px;">
                                    <label style="font-size:13px;"><?= t('studio.image_edit.rotation') ?></label>
                                    <input id="imgEditRotation" type="range" min="0" max="360" value="0" style="width:140px; vertical-align:middle;" data-on-input="updateImageRotation">
                                    <input id="imgEditRotationVal" type="number" min="0" max="360" value="0" style="width:48px;" data-on-input="updateImageRotation">
                                </div>
                                <div style="display:flex; gap:10px; margin-top:10px;">
                                    <button type="button" class="btn btn-block btn-secondary" data-on-click="resetImageEdit"><?= t('studio.image_edit.reset') ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                        <div class="whats-next-action" data-on-click="openUploadEditor">
                            <div class="whats-next-icon">
                                <!-- Upload Icon -->
                                <svg width="48" height="48" fill="none" stroke="#15130E" stroke-width="2" viewBox="0 0 48 48"><path d="M24 34V14M24 14l-8 8M24 14l8 8"/><rect x="8" y="36" width="32" height="6" rx="3"/></svg>
                            </div>
                            <div class="whats-next-label"><?= t('studio.action.uploads') ?></div>
                        </div>
                        <div class="whats-next-action" data-on-click="triggerWhatsNextAddText">
                            <!-- Hidden input for uploads (for Whats Next panel) -->
                            <div class="whats-next-icon">
                                <!-- Text Icon -->
                                <svg width="48" height="48" fill="none" stroke="#15130E" stroke-width="2" viewBox="0 0 48 48"><text x="8" y="36" font-size="28" font-family="Arial" fill="#15130E">abc</text></svg>
                            </div>
                            <div class="whats-next-label"><?= t('studio.action.add_text') ?></div>
                        </div>
                        <div class="whats-next-action" data-on-click="openChangeColorModal">
                            <div class="whats-next-icon">
                                <!-- Palette Icon -->
                                <svg width="48" height="48" fill="none" stroke="#15130E" stroke-width="2" viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="#f8fafd" stroke="#15130E"/><circle cx="16" cy="20" r="3" fill="#15130E"/><circle cx="32" cy="20" r="3" fill="#15130E"/><circle cx="24" cy="32" r="3" fill="#15130E"/></svg>
                            </div>
                            <div class="whats-next-label"><?= t('studio.action.change_color') ?></div>
                        </div>
                        <div class="whats-next-action" data-href="/shop/select_product">
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
                        <button id="saveDesignBtn" class="studio-action studio-action-secondary" data-on-click="openSaveDesignModal"><?= t('studio.save_design') ?></button>
                    </div>
<?php // Change-colour pop-up: printed by the footer after </main> (see $overlays).
ob_start(); ?>
                    <!-- Change Color Modal -->
                    <div id="changeColorModal" class="change-color-modal" style="display:none;" data-on-click="closeChangeColorModal" data-click-self>
                        <div class="change-color-modal-content" data-stop-click>
                            <button class="change-color-modal-close" data-on-click="closeChangeColorModal">&times;</button>
                            <h2><?= t('studio.color_modal.title') ?></h2>
                            <div id="changeColorOptions"></div>
                        </div>
                    </div>
<?php $overlays = ($overlays ?? '') . ob_get_clean(); ?>
                    
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
