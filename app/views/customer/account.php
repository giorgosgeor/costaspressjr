<?php $extraCss = ['/css/account.css']; ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>


<section class="account-page">
<div class="account-container">
    <div class="account-header">
        <h1><?= t('account.title') ?></h1>
        <p><?= I18n::t('account.welcome_back', ['name' => $user['username'] ?? 'User']) ?></p>
    </div>

    <div class="account-tabs">
        <button class="account-tab active" data-tab="designs"><?= t('account.tabs.designs') ?></button>
        <button class="account-tab" data-tab="orders"><?= t('account.tabs.orders') ?></button>
        <button class="account-tab" data-tab="profile"><?= t('account.tabs.profile') ?></button>
    </div>

    <!-- Saved Designs Tab -->
    <div id="tab-designs" class="tab-content active">
        <?php if (empty($savedDesigns)): ?>
            <div class="account-empty-state">
                <h3><?= t('account.no_designs.title') ?></h3>
                <p><?= t('account.no_designs.lead') ?></p>
                <a href="/shop/custom" class="btn btn-primary"><?= t('account.no_designs.button') ?></a>
            </div>
        <?php else: ?>
            <div class="designs-grid">
                <?php foreach ($savedDesigns as $design): 
                    $colorHex = $design['color_hex'] ?? '#000000';
                    // Default to black if no color or empty
                    if (empty($colorHex)) {
                        $colorHex = '#000000';
                    }
                    
                    // Calculate CSS filter for product colorization (same as shop_custom.php)
                    $hex = ltrim($colorHex, '#');
                    $r = hexdec(substr($hex, 0, 2)) / 255;
                    $g = hexdec(substr($hex, 2, 2)) / 255;
                    $b = hexdec(substr($hex, 4, 2)) / 255;
                    $max = max($r, $g, $b);
                    $min = min($r, $g, $b);
                    $l = ($max + $min) / 2;
                    $s = 0;
                    $h = 0;
                    if ($max !== $min) {
                        $d = $max - $min;
                        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
                        if ($max === $r) $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6;
                        elseif ($max === $g) $h = (($b - $r) / $d + 2) / 6;
                        else $h = (($r - $g) / $d + 4) / 6;
                    }
                    $hsl = ['h' => $h * 360, 's' => $s * 100, 'l' => $l * 100];
                    
                    $isBlack = strtolower($colorHex) === '#000000' || strtolower($colorHex) === '#000' || $hsl['l'] < 10;
                    $isWhite = strtolower($colorHex) === '#ffffff' || strtolower($colorHex) === '#fff' || $hsl['l'] > 95;
                    $isGray = $hsl['s'] < 10;
                    
                    if ($isWhite) {
                        $productFilter = 'saturate(0) brightness(2) contrast(0.8)';
                    } elseif ($isBlack) {
                        $productFilter = 'saturate(0) brightness(0.65) contrast(1.1)';
                    } elseif ($isGray) {
                        $brightness = 0.2 + ($hsl['l'] / 100) * 1.5;
                        $productFilter = "saturate(0) brightness({$brightness})";
                    } else {
                        $hueRotate = $hsl['h'] - 50;
                        $isReddish = $hsl['h'] <= 20 || $hsl['h'] >= 340;
                        $saturate = ($hsl['s'] / 100) * 2 + 0.5;
                        if ($isReddish) $saturate = ($hsl['s'] / 100) * 3 + 1;
                        if ($hsl['l'] < 30) {
                            $brightness = 0.3 + ($hsl['l'] / 100) * 0.7;
                        } elseif ($hsl['l'] < 50) {
                            $brightness = 0.5 + ($hsl['l'] / 100) * 0.6;
                        } else {
                            $brightness = 0.6 + ($hsl['l'] / 100) * 0.5;
                        }
                        $productFilter = "sepia(1) saturate({$saturate}) hue-rotate({$hueRotate}deg) brightness({$brightness})";
                    }
                    
                    $savedPreviews = !empty($design['preview_images']) ? json_decode($design['preview_images'], true) : [];
                    $frontPreviewPath = $savedPreviews['front'] ?? null;
                    $frontDesignPreviewPath = $savedPreviews['front_design'] ?? null;

                    // Element coordinates are in EDITOR pixels, relative to the print
                    // area as it was sized when the design was saved. That size is
                    // recorded in elements_json._meta — use it, because the estimate
                    // below is only a fallback for designs saved before _meta existed.
                    // Getting this wrong scales every element (411x548 read as 225x300
                    // misplaces and shrinks everything by ~1.8x).
                    $metaDAW = null; $metaDAH = null;
                    if (!empty($design['elements_json'])) {
                        $ej = json_decode($design['elements_json'], true);
                        if (isset($ej['_meta']['editorDAWidth'])  && $ej['_meta']['editorDAWidth']  > 0) $metaDAW = (float)$ej['_meta']['editorDAWidth'];
                        if (isset($ej['_meta']['editorDAHeight']) && $ej['_meta']['editorDAHeight'] > 0) $metaDAH = (float)$ej['_meta']['editorDAHeight'];
                    }

                    // Compute editor design-area dimensions from the product image's natural size.
                    // In the editor: #designArea = 45% wide × 60% tall of the rendered mockup image.
                    // With object-fit:contain in a square ~510px container:
                    //   editorDAW = 0.45 × rendered_imgW ≈ 225 (fixed baseline)
                    //   editorDAH = 0.60 × rendered_imgH = 300 × (naturalH / naturalW)
                    $phpEditorDAW = 225;
                    $phpEditorDAH = 300;
                    if (!empty($design['product_image'])) {
                        $imgPath = ltrim($design['product_image'], '/');
                        // Strip 'public/' prefix if already present, then rebuild absolute path
                        if (strpos($imgPath, 'public/') === 0) $imgPath = substr($imgPath, 7);
                        // app/views/customer -> three levels up reaches the project root.
                        $fullImgPath = __DIR__ . '/../../../public/' . $imgPath;
                        $dim = @getimagesize($fullImgPath);
                        if ($dim && $dim[0] > 0 && $dim[1] > 0) {
                            $phpEditorDAH = round(300 * ($dim[1] / $dim[0]), 2);
                        }
                    }
                ?>
                    <?php
                        // Compose the card live: current product image + the design's
                        // own front elements, positioned as percentages of the print
                        // area. The stored composite previews are NOT used here —
                        // they were rendered against the previous product artwork, so
                        // they showed a garment that no longer matches the one you get
                        // when you open the design. Designs saved before previews
                        // existed had none at all and showed a blank garment.
                        $daX = (float)($design['da_front_x'] ?? 27.5);
                        $daY = (float)($design['da_front_y'] ?? 25);
                        $daW = (float)($design['da_front_w'] ?? 45);
                        $daH = (float)($design['da_front_h'] ?? 60);
                        $frontUploads = array_values(array_filter($design['uploads'] ?? [], function ($u) {
                            return ($u['view_placement'] ?? 'front') === 'front' && !empty($u['stored_file_path']);
                        }));
                        $frontTexts = array_values(array_filter($design['texts'] ?? [], function ($t) {
                            return ($t['view_placement'] ?? 'front') === 'front' && !empty($t['text_content']);
                        }));
                        // Scale against the print-area size recorded WITH the design,
                        // never the current estimate. The product artwork was replaced
                        // (different pixel sizes and framing), so anything derived from
                        // today's image would misplace designs saved against the old art.
                        $refW = $metaDAW ?: $phpEditorDAW;
                        $refH = $metaDAH ?: $phpEditorDAH;
                        $pct = function ($v, $of) { return $of > 0 ? ($v / $of) * 100 : 0; };
                    ?>
                    <div class="design-card" data-design-id="<?= $design['id'] ?>">
                        <div class="design-card-image" style="position:relative; background:#fff; overflow:hidden;">
                            <?php if (!empty($design['product_image'])): ?>
                                <img src="/<?= htmlspecialchars(ltrim($design['product_image'], '/')) ?>"
                                     alt="<?= htmlspecialchars($design['product_name'] ?? '') ?>" loading="lazy"
                                     style="width:100%; height:100%; object-fit:contain; filter:<?= $productFilter ?>;">
                                <?php if ($frontUploads || $frontTexts): ?>
                                <div style="position:absolute; left:<?= $daX ?>%; top:<?= $daY ?>%; width:<?= $daW ?>%; height:<?= $daH ?>%; overflow:hidden; pointer-events:none; container-type:inline-size;">
                                    <?php foreach ($frontUploads as $u):
                                        $src = ltrim((string)$u['stored_file_path'], '/');
                                        if (strpos($src, 'public/') === 0) $src = substr($src, 7);
                                    ?>
                                    <img src="/<?= htmlspecialchars($src) ?>" alt="" loading="lazy"
                                         style="position:absolute;
                                                left:<?= round($pct((float)($u['position_x'] ?? 0), $refW), 3) ?>%;
                                                top:<?= round($pct((float)($u['position_y'] ?? 0), $refH), 3) ?>%;
                                                width:<?= round($pct((float)($u['width'] ?? $phpEditorDAW), $refW), 3) ?>%;
                                                height:<?= round($pct((float)($u['height'] ?? $phpEditorDAH), $refH), 3) ?>%;
                                                object-fit:contain;">
                                    <?php endforeach; ?>
                                    <?php foreach ($frontTexts as $tx): ?>
                                    <div style="position:absolute;
                                                left:<?= round($pct((float)($tx['position_x'] ?? 0), $refW), 3) ?>%;
                                                top:<?= round($pct((float)($tx['position_y'] ?? 0), $refH), 3) ?>%;
                                                font-size:<?= round($pct((float)($tx['font_size'] ?? 24), $refW), 3) ?>cqw;
                                                color:<?= htmlspecialchars($tx['text_color'] ?? '#000') ?>;
                                                font-family:<?= htmlspecialchars($tx['font_family'] ?? 'inherit') ?>;
                                                white-space:nowrap; line-height:1;"><?= htmlspecialchars($tx['text_content']) ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#aaa;"><?= t('account.no_preview') ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="design-card-body">
                            <div class="design-card-title"><?= htmlspecialchars($design['name']) ?></div>
                            <div class="design-card-product"><?= htmlspecialchars($design['product_name'] ?? t('custom.title', false)) ?></div>
                            <div class="design-card-date"><?= I18n::t('account.created', ['date' => date('M j, Y', strtotime($design['created_at']))]) ?></div>
                            <div class="design-card-actions">
                                <button class="btn btn-primary" onclick="addDesignToCart(<?= htmlspecialchars(json_encode([
                                    'id' => $design['id'],
                                    'productId' => $design['product_id'],
                                    'productName' => $design['product_name'] ?? 'Custom Product',
                                    'designName' => $design['name'],
                                    'basePrice' => $design['base_price'] ?? 0,
                                    'productImage' => '/' . ltrim($design['product_image'] ?? '', '/'),
                                    'colorHex' => $design['color_hex'] ?? '#000000',
                                    'elementsJson' => $design['elements_json'] ?? '{}',
                                    'uploads' => $design['uploads'] ?? [],
                                    'texts' => $design['texts'] ?? [],
                                    // Deliberately null: the stored composites were
                                    // rendered against the previous product artwork,
                                    // so the modal would show a different garment from
                                    // the card beside it. Passing null makes the modal
                                    // compose from the live product image and the
                                    // design's own coordinates, exactly as the card
                                    // above does. They regenerate on the next save.
                                    'frontPreviewPath' => null,
                                    'frontDesignPreviewPath' => null,
                                    'editorDAWidth'  => $phpEditorDAW,
                                    'editorDAHeight' => $phpEditorDAH,
                                ]), ENT_QUOTES, 'UTF-8') ?>)">
                                    <?= t('account.add_to_cart') ?>
                                </button>
                                <a href="/shop/custom?load=<?= $design['id'] ?>" class="btn btn-outline"><?= t('account.edit') ?></a>
                                <button class="btn btn-danger" onclick="deleteDesign(<?= $design['id'] ?>, '<?= htmlspecialchars(addslashes($design['name'])) ?>')"><?= t('account.delete') ?></button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Order History Tab -->
    <div id="tab-orders" class="tab-content">
        <?php if (empty($orders)): ?>
            <div class="account-empty-state">
                <h3><?= t('account.no_orders.title') ?></h3>
                <p><?= t('account.no_orders.lead') ?></p>
                <a href="/shop" class="btn btn-primary"><?= t('account.no_orders.button') ?></a>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $order):
                    $statusColors = [
                        'pending'    => ['bg' => 'rgba(234,179,8,0.15)',   'color' => '#facc15', 'border' => 'rgba(234,179,8,0.4)'],
                        'processing' => ['bg' => 'rgba(59,130,246,0.15)',  'color' => '#60a5fa', 'border' => 'rgba(59,130,246,0.4)'],
                        'in-transit' => ['bg' => 'rgba(139,92,246,0.15)',  'color' => '#a78bfa', 'border' => 'rgba(139,92,246,0.4)'],
                        'delivered'  => ['bg' => 'rgba(34,197,94,0.15)',   'color' => '#4ade80', 'border' => 'rgba(34,197,94,0.4)'],
                        'cancelled'  => ['bg' => 'rgba(239,68,68,0.15)',   'color' => '#f87171', 'border' => 'rgba(239,68,68,0.4)'],
                    ];
                    $sc = $statusColors[$order['status']] ?? $statusColors['pending'];
                    $paymentLabel = '';
                    if (!empty($order['payment_method'])) {
                        if ($order['payment_method'] === 'card' && !empty($order['card_brand'])) {
                            $paymentLabel = ucfirst($order['card_brand']) . ' &bull;&bull;&bull;&bull; ' . htmlspecialchars($order['card_last4'] ?? '');
                        } else {
                            $paymentLabel = ucfirst(htmlspecialchars($order['payment_method']));
                        }
                    }
                ?>
                <div class="order-card">
                    <div class="order-card-header">
                        <div class="order-card-meta">
                            <span class="order-number"><?= I18n::t('account.order_number', ['id' => htmlspecialchars($order['id'])]) ?></span>
                            <span class="order-date"><?= date('M j, Y', strtotime($order['created_at'])) ?></span>
                        </div>
                        <span class="order-status-badge" style="background:<?= $sc['bg'] ?>; color:<?= $sc['color'] ?>; border-color:<?= $sc['border'] ?>;">
                            <?= htmlspecialchars(I18n::t('status.' . $order['status'], [], ucfirst($order['status']))) ?>
                        </span>
                    </div>
                    <div class="order-card-body">
                        <div class="order-card-detail">
                            <span class="order-detail-label"><?= t('account.items') ?></span>
                            <span class="order-detail-value"><?= (int)$order['item_count'] ?></span>
                        </div>
                        <?php if ($paymentLabel): ?>
                        <div class="order-card-detail">
                            <span class="order-detail-label"><?= t('account.payment') ?></span>
                            <span class="order-detail-value"><?= $paymentLabel ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="order-card-detail">
                            <span class="order-detail-label"><?= t('account.total') ?></span>
                            <span class="order-detail-value order-total">$<?= number_format((float)$order['total_price'], 2) ?></span>
                        </div>
                    </div>
                    <div class="order-card-footer">
                        <a href="/orders?id=<?= (int)$order['id'] ?>" class="btn btn-outline order-view-btn"><?= t('account.view_details') ?></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Profile Tab -->
    <div id="tab-profile" class="tab-content">
        <div class="profile-section">
            <div class="profile-field">
                <label><?= t('account.profile.email') ?></label>
                <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
            </div>
            <div class="profile-field">
                <label><?= t('account.profile.username') ?></label>
                <input type="text" value="<?= htmlspecialchars($user['username'] ?? '') ?>" disabled>
            </div>
            <div class="profile-field">
                <label><?= t('account.profile.member_since') ?></label>
                <input type="text" value="<?= isset($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : t('account.profile.na', false) ?>" disabled>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="confirm-overlay" style="z-index:40000;" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle">
    <div class="confirm-dialog">
        <div class="confirm-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
        </div>
        <h3 id="deleteConfirmTitle" class="confirm-title"><?= t('account.delete_modal.title') ?></h3>
        <p class="confirm-lead"><?= I18n::t('account.delete_modal.lead', ['name' => '<span id=\"deleteDesignNameText\"></span>']) ?><br><strong style="color:var(--ink);"><?= t('account.delete_modal.warning') ?></strong></p>
        <div class="confirm-actions">
            <button type="button" class="confirm-btn confirm-btn-secondary" onclick="closeDeleteModal()"><?= t('account.delete_modal.cancel') ?></button>
            <button type="button" id="confirmDeleteBtn" class="confirm-btn confirm-btn-danger" onclick="confirmDelete()"><?= t('account.delete_modal.confirm') ?></button>
        </div>
    </div>
</div>

<!-- Include the Add to Cart Modal from shop_custom -->
<div id="addToCartModal" style="display:none; position:fixed; z-index:35000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.7); align-items:center; justify-content:center;">
    <div style="background:var(--bg-dark-secondary, #16213e); border-radius:18px; max-width:520px; width:95vw; margin:auto; box-shadow:0 2px 24px rgba(0,0,0,0.3); padding:2rem; position:relative; max-height:90vh; overflow-y:auto; border:1px solid var(--border-light, rgba(255,255,255,0.1));">
        <button onclick="closeAddToCartModal()" style="position:absolute; top:1rem; right:1rem; background:none; border:none; font-size:2rem; color:var(--text-light-muted, #888); cursor:pointer;">&times;</button>
        <h2 style="font-size:1.4rem; font-weight:700; margin-bottom:1rem; text-align:center; color:var(--text-light, #fff);"><?= t('account.cart_modal.title') ?></h2>
        
        <div id="cartDesignPreview" style="text-align:center; margin-bottom:1.5rem; background:var(--bg-card-dark, rgba(255,255,255,0.05)); border-radius:12px; padding:1rem; position:relative;">
            <div id="cartPreviewContainer" style="position:relative; width:220px; height:220px; margin:0 auto; border-radius:8px; overflow:hidden; background:#fff;">
                <!-- Pre-rendered composite preview (preferred, shown until color changed) -->
                <img id="cartPreviewImg" src="" alt="Preview" style="display:none; width:100%; height:100%; object-fit:contain; position:absolute; top:0; left:0; z-index:10;">
                <!-- Colored shirt base -->
                <img id="cartProductImage" src="" alt="Product" style="width:100%; height:100%; object-fit:contain; position:relative; z-index:1;">
                <div id="cartColorOverlay" style="position:absolute; top:0; left:0; width:100%; height:100%; mix-blend-mode:multiply; pointer-events:none; z-index:2;"></div>
                <!-- Design-only transparent PNG overlay (exact match to composite, no coordinate math needed) -->
                <img id="cartDesignOverlay" src="" alt="" style="display:none; width:100%; height:100%; object-fit:contain; position:absolute; top:0; left:0; z-index:3; pointer-events:none;">
                <!-- Fallback: DOM-based positioned elements (used when no design-only PNG available) -->
                <div id="cartPreviewDesignArea" style="position:absolute; top:25%; left:27.5%; width:45%; height:60%; pointer-events:none; z-index:3;"></div>
            </div>
            <div id="cartProductName" style="font-weight:600; margin-top:0.75rem; color:var(--text-light, #fff);"></div>
            <div id="cartDesignName" style="font-size:0.9rem; color:var(--text-light-secondary, #aaa);"></div>
        </div>
        
        <div style="margin-bottom:1.2rem;">
            <label style="font-weight:600; display:block; margin-bottom:0.5rem; color:var(--text-light, #fff);"><?= t('account.cart_modal.select_size') ?></label>
            <div id="cartSizeOptions" style="display:flex; flex-wrap:wrap; gap:8px;"></div>
        </div>

        <div style="margin-bottom:1.2rem;">
            <label style="font-weight:600; display:block; margin-bottom:0.5rem; color:var(--text-light, #fff);"><?= t('account.cart_modal.select_color') ?></label>
            <div id="cartColorOptions" style="display:flex; flex-wrap:wrap; gap:8px;"></div>
        </div>

        <div style="margin-bottom:1.5rem;">
            <label style="font-weight:600; display:block; margin-bottom:0.5rem; color:var(--text-light, #fff);"><?= t('account.cart_modal.quantity') ?></label>
            <div style="display:flex; align-items:center; gap:12px;">
                <button onclick="adjustCartQuantity(-1)" class="qty-btn" style="width:36px; height:36px; background:rgba(255,255,255,0.1); border:none; border-radius:8px; font-size:1.2rem; cursor:pointer; color:#fff;">−</button>
                <input id="cartQuantity" type="number" value="1" min="1" max="100" style="width:60px; text-align:center; padding:8px; border:1px solid var(--border-light, rgba(255,255,255,0.2)); border-radius:8px; font-size:1rem; background:rgba(255,255,255,0.05); color:#fff;">
                <button onclick="adjustCartQuantity(1)" class="qty-btn" style="width:36px; height:36px; background:rgba(255,255,255,0.1); border:none; border-radius:8px; font-size:1.2rem; cursor:pointer; color:#fff;">+</button>
            </div>
        </div>
        
        <div id="cartPriceSummary" style="background:var(--bg-card-dark, rgba(255,255,255,0.05)); padding:1rem; border-radius:10px; margin-bottom:1.2rem; border:1px solid var(--border-light, rgba(255,255,255,0.1));">
            <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem; color:var(--text-light-secondary, #aaa);">
                <span><?= t('account.cart_modal.base_price') ?></span>
                <span id="cartBasePrice">$0.00</span>
            </div>
            <div style="display:flex; justify-content:space-between; font-weight:700; border-top:1px solid var(--border-light, rgba(255,255,255,0.1)); padding-top:0.5rem; margin-top:0.5rem; color:var(--success, #4ade80);">
                <span style="color:var(--text-light, #fff);"><?= t('account.cart_modal.total') ?></span>
                <span id="cartTotalPrice">$0.00</span>
            </div>
        </div>

        <div id="cartError" style="display:none; color:#ef4444; text-align:center; margin-bottom:1rem; font-size:0.95rem;"></div>

        <button id="confirmAddToCartBtn" class="btn-primary-gradient" style="width:100%; font-weight:600; font-size:1.1rem; padding:12px 0; border:none; border-radius:8px; cursor:pointer;"><?= t('account.cart_modal.cta') ?></button>
    </div>
</div>

<script src="<?= htmlspecialchars(Asset::url('/js/account.js')) ?>" defer></script>
</section>
<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
