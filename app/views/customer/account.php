<?php $extraCss = ['/css/account.css']; ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>


<?php
    // Sidebar entries. Each drives one .tab-content panel below; `icon` is the
    // path data for a 20px stroked SVG so the nav needs no icon font.
    $accountNav = [
        'overview'  => ['label' => t('account.tabs.overview', false),  'icon' => '<path d="M3 9.5 12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>'],
        'designs'   => ['label' => t('account.tabs.designs', false),   'icon' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>'],
        'uploads'   => ['label' => t('account.tabs.uploads', false),   'icon' => '<path d="M12 15V4"/><path d="m7 9 5-5 5 5"/><path d="M4 17v2a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2"/>'],
        'favorites' => ['label' => t('account.tabs.favorites', false), 'icon' => '<path d="M20.8 5.6a5 5 0 0 0-7.1 0L12 7.3l-1.7-1.7a5 5 0 1 0-7.1 7.1l8.8 8.8 8.8-8.8a5 5 0 0 0 0-7.1z"/>'],
        'orders'    => ['label' => t('account.tabs.orders', false),    'icon' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/>'],
        'profile'   => ['label' => t('account.tabs.profile', false),   'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2 2 2 0 1 1-4 0 1.7 1.7 0 0 0-2.9-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 4.6 15a2 2 0 1 1 0-4 1.7 1.7 0 0 0 1.2-2.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 11.5 4a2 2 0 1 1 4 0 1.7 1.7 0 0 0 2.9 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0 1.2 2.9 2 2 0 1 1 0 4z"/>'],
    ];
?>
<section class="account-page">
<div class="account-container account-layout">

    <nav class="account-nav" aria-label="<?= t('account.nav_label') ?>">
        <?php foreach ($accountNav as $key => $item): ?>
        <button class="account-tab<?= $key === 'overview' ? ' active' : '' ?>" data-tab="<?= $key ?>" type="button">
            <svg class="account-tab-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true"><?= $item['icon'] ?></svg>
            <span class="account-tab-label"><?= htmlspecialchars($item['label']) ?></span>
        </button>
        <?php endforeach; ?>
    </nav>

    <div class="account-main">

    <?php // I18n::t interpolates raw, so the username is escaped before it goes in. ?>
    <div class="account-header">
        <h1><?= I18n::t('account.welcome_back', ['name' => htmlspecialchars($user['username'] ?? 'User')]) ?></h1>
    </div>

    <!-- Overview Tab -->
    <div id="tab-overview" class="tab-content active">
        <p class="account-lead"><?= t('account.overview.lead') ?></p>
        <div class="account-stats">
            <?php
                $stats = [
                    ['designs',   count($savedDesigns), t('account.tabs.designs', false)],
                    ['orders',    count($orders),       t('account.tabs.orders', false)],
                    ['favorites', count($favorites),    t('account.tabs.favorites', false)],
                    ['uploads',   count($uploads),      t('account.tabs.uploads', false)],
                ];
                foreach ($stats as [$target, $count, $label]):
            ?>
            <button class="account-stat" type="button" data-goto="<?= $target ?>">
                <span class="account-stat-value"><?= (int)$count ?></span>
                <span class="account-stat-label"><?= htmlspecialchars($label) ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <?php // A short strip of the newest designs, then straight through to the full tab. ?>
        <div class="account-section-head">
            <h2><?= t('account.tabs.designs') ?></h2>
            <?php if (!empty($savedDesigns)): ?>
            <button class="account-viewall" type="button" data-goto="designs"><?= t('account.view_all') ?></button>
            <?php endif; ?>
        </div>
        <?php if (empty($savedDesigns)): ?>
            <div class="account-empty-state">
                <h3><?= t('account.no_designs.title') ?></h3>
                <p><?= t('account.no_designs.lead') ?></p>
                <a href="/shop/custom" class="btn btn-primary"><?= t('account.no_designs.button') ?></a>
            </div>
        <?php else: ?>
            <div class="designs-grid">
                <?php foreach (array_slice($savedDesigns, 0, 3) as $design): ?>
                    <?php include __DIR__ . '/_design_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="account-section-head">
            <h2><?= t('account.tabs.orders') ?></h2>
            <?php if (!empty($orders)): ?>
            <button class="account-viewall" type="button" data-goto="orders"><?= t('account.view_all') ?></button>
            <?php endif; ?>
        </div>
        <?php if (empty($orders)): ?>
            <div class="account-empty-state">
                <h3><?= t('account.no_orders.title') ?></h3>
                <p><?= t('account.no_orders.lead') ?></p>
                <a href="/shop" class="btn btn-primary"><?= t('account.no_orders.button') ?></a>
            </div>
        <?php else: ?>
            <div class="account-recent-orders">
                <?php foreach (array_slice($orders, 0, 3) as $o): ?>
                <a href="/orders/view?id=<?= (int)$o['id'] ?>" class="account-recent-order">
                    <span class="account-recent-order-id"><?= I18n::t('account.order_number', ['id' => htmlspecialchars($o['id'])]) ?></span>
                    <span class="account-recent-order-date"><?= date('M j, Y', strtotime($o['created_at'])) ?></span>
                    <span class="account-recent-order-total">&euro;<?= number_format((float)$o['total_price'], 2) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Saved Designs Tab -->
    <div id="tab-designs" class="tab-content">
        <?php if (empty($savedDesigns)): ?>
            <div class="account-empty-state">
                <h3><?= t('account.no_designs.title') ?></h3>
                <p><?= t('account.no_designs.lead') ?></p>
                <a href="/shop/custom" class="btn btn-primary"><?= t('account.no_designs.button') ?></a>
            </div>
        <?php else: ?>
            <div class="designs-grid">
                <?php foreach ($savedDesigns as $design): ?>
                    <?php include __DIR__ . '/_design_card.php'; ?>
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

    <!-- My Uploads Tab -->
    <div id="tab-uploads" class="tab-content">
        <?php if (empty($uploads)): ?>
            <div class="account-empty-state">
                <h3><?= t('account.no_uploads.title') ?></h3>
                <p><?= t('account.no_uploads.lead') ?></p>
                <a href="/shop/custom" class="btn btn-primary"><?= t('account.no_uploads.button') ?></a>
            </div>
        <?php else: ?>
            <div class="uploads-grid">
                <?php foreach ($uploads as $up):
                    $src = ltrim((string)$up['stored_file_path'], '/');
                    if (strpos($src, 'public/') === 0) $src = substr($src, 7);
                    // file_size is stored in bytes; show whichever unit reads cleanly.
                    $bytes = (int)($up['file_size'] ?? 0);
                    $sizeLabel = $bytes >= 1048576
                        ? number_format($bytes / 1048576, 1) . ' MB'
                        : ($bytes > 0 ? max(1, (int)round($bytes / 1024)) . ' KB' : '—');

                    // The editor posts artwork as base64 with no filename, so
                    // original_filename is usually NULL. Fall back to the design
                    // it belongs to plus the extension — more use than a hash,
                    // and far better than a blank line.
                    $label = trim((string)($up['original_filename'] ?? ''));
                    if ($label === '') {
                        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
                        $firstDesign = trim(explode(',', (string)($up['design_names'] ?? ''))[0]);
                        $label = $firstDesign !== ''
                            ? $firstDesign . ($ext ? '.' . $ext : '')
                            : basename($src);
                    }
                ?>
                <div class="upload-card">
                    <div class="upload-card-image">
                        <img src="/<?= htmlspecialchars($src) ?>" alt="<?= htmlspecialchars($up['original_filename'] ?? '') ?>" loading="lazy">
                    </div>
                    <div class="upload-card-body">
                        <div class="upload-card-name" title="<?= htmlspecialchars($label) ?>"><?= htmlspecialchars($label) ?></div>
                        <div class="upload-card-meta">
                            <span><?= htmlspecialchars($sizeLabel) ?></span>
                            <span><?= date('M j, Y', strtotime($up['created_at'])) ?></span>
                        </div>
                        <div class="upload-card-used" title="<?= htmlspecialchars($up['design_names'] ?? '') ?>">
                            <?= I18n::t('account.uploads.used_in', ['count' => (int)$up['design_count']]) ?>
                        </div>
                        <a class="btn btn-outline" href="/<?= htmlspecialchars($src) ?>" download><?= t('account.uploads.download') ?></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Favorites Tab -->
    <div id="tab-favorites" class="tab-content">
        <?php // Always rendered so removing the last favourite can reveal it. ?>
        <div class="account-empty-state" id="favoritesEmpty"<?= empty($favorites) ? '' : ' style="display:none;"' ?>>
            <h3><?= t('account.no_favorites.title') ?></h3>
            <p><?= t('account.no_favorites.lead') ?></p>
            <a href="/shop" class="btn btn-primary"><?= t('account.no_favorites.button') ?></a>
        </div>
        <?php if (!empty($favorites)): ?>
            <div class="favorites-grid">
                <?php foreach ($favorites as $fav):
                    $img  = ltrim((string)($fav['image_path'] ?? ''), '/');
                    if (strpos($img, 'public/') === 0) $img = substr($img, 7);
                    // Products open the customiser via the picker; designs have their own page.
                    $href = $fav['kind'] === 'design'
                        ? '/shop/design/' . (int)$fav['item_id']
                        : '/shop/select_product';
                ?>
                <div class="favorite-card<?= empty($fav['active']) ? ' is-inactive' : '' ?>">
                    <button class="favorite-remove" type="button"
                            data-kind="<?= htmlspecialchars($fav['kind']) ?>"
                            data-id="<?= (int)$fav['item_id'] ?>"
                            aria-label="<?= t('account.favorites.remove') ?>"
                            title="<?= t('account.favorites.remove') ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.8 5.6a5 5 0 0 0-7.1 0L12 7.3l-1.7-1.7a5 5 0 1 0-7.1 7.1l8.8 8.8 8.8-8.8a5 5 0 0 0 0-7.1z"/></svg>
                    </button>
                    <a href="<?= htmlspecialchars($href) ?>" class="favorite-card-link">
                        <div class="favorite-card-image">
                            <?php if ($img !== ''): ?>
                                <img src="/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($fav['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="favorite-noimage"><?= t('account.no_preview') ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="favorite-card-body">
                            <div class="favorite-card-kind"><?= $fav['kind'] === 'design' ? t('account.favorites.kind_design') : t('account.favorites.kind_product') ?></div>
                            <div class="favorite-card-name"><?= htmlspecialchars($fav['name']) ?></div>
                            <div class="favorite-card-price">&euro;<?= number_format((float)$fav['display_price'], 2) ?></div>
                            <?php if (empty($fav['active'])): ?>
                            <div class="favorite-card-inactive"><?= t('account.favorites.unavailable') ?></div>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Account Settings Tab -->
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

    </div><!-- /.account-main -->
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="confirm-overlay" style="z-index:40000;" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle">
    <div class="confirm-dialog">
        <div class="confirm-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
        </div>
        <h3 id="deleteConfirmTitle" class="confirm-title"><?= t('account.delete_modal.title') ?></h3>
        <?php // Plain double quotes: in a single-quoted PHP string \" is not an
              // escape, so it emitted id=\"...\" literally and the browser never
              // saw an element with this id — deleteDesign() then threw on its
              // first line and the whole delete flow died on click. ?>
        <p class="confirm-lead"><?= I18n::t('account.delete_modal.lead', ['name' => '<span id="deleteDesignNameText"></span>']) ?><br><strong style="color:var(--ink);"><?= t('account.delete_modal.warning') ?></strong></p>
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
                <button onclick="adjustCartQuantity(-1)" class="qty-btn" style="width:36px; height:36px; background:rgba(255,255,255,0.1); border:none; border-radius:8px; font-size:1.2rem; cursor:pointer; color:#fff;">âˆ’</button>
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
