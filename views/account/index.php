<?php $extraCss = ['/css/pages/account.css']; $verifyBannerAlways = true; ?>
<?php require View::path('layouts/customer_header'); ?>


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
                    <?php include View::path('account/_design_card'); ?>
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
                    <?php include View::path('account/_design_card'); ?>
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
                        'pending'    => ['bg' => 'rgba(245,158,11,0.12)', 'color' => '#92400E', 'border' => 'rgba(245,158,11,0.40)'],
                        'processing' => ['bg' => 'rgba(59,130,246,0.12)', 'color' => '#1E40AF', 'border' => 'rgba(59,130,246,0.40)'],
                        'in-transit' => ['bg' => 'rgba(139,92,246,0.12)', 'color' => '#5B21B6', 'border' => 'rgba(139,92,246,0.40)'],
                        'delivered'  => ['bg' => 'rgba(34,197,94,0.12)',  'color' => '#166534', 'border' => 'rgba(34,197,94,0.40)'],
                        'cancelled'  => ['bg' => 'rgba(239,68,68,0.12)',  'color' => '#991B1B', 'border' => 'rgba(239,68,68,0.40)'],
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

<?php // Delete-design confirmation: printed by the footer after </main> (see $overlays).
ob_start(); ?>
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
            <button type="button" class="confirm-btn confirm-btn-secondary" data-on-click="closeDeleteModal"><?= t('account.delete_modal.cancel') ?></button>
            <button type="button" id="confirmDeleteBtn" class="confirm-btn confirm-btn-danger" data-on-click="confirmDelete"><?= t('account.delete_modal.confirm') ?></button>
        </div>
    </div>
</div>
<?php $overlays = ($overlays ?? '') . ob_get_clean(); ?>

<?php // Add-to-cart pop-up: printed by the footer after </main> (see $overlays).
ob_start(); ?>
<?php // Add a saved design to the cart. The shared pop-up (studio.css .popup*),
      // like the premade design page's and the designer's; it was the last
      // piece of an old dark theme (navy box, white text, a red button). ?>
<div id="addToCartModal" class="popup-overlay" style="display:none;" data-on-click="closeAddToCartModal" data-click-self>
    <div class="popup" role="dialog" aria-modal="true" aria-labelledby="accountCartTitle" data-stop-click>
        <button type="button" class="popup-close" data-on-click="closeAddToCartModal" aria-label="<?= t('common.close') ?>">&times;</button>
        <h2 class="popup-title" id="accountCartTitle"><?= t('account.cart_modal.title') ?></h2>

        <div id="cartDesignPreview" class="popup-stage">
            <div id="cartPreviewContainer" class="account-cart-preview">
                <!-- Pre-rendered composite preview (preferred, shown until color changed) -->
                <img id="cartPreviewImg" src="" alt="Preview" class="account-cart-layer account-cart-composite" style="display:none;">
                <!-- Colored shirt base -->
                <img id="cartProductImage" src="" alt="Product" class="account-cart-base">
                <div id="cartColorOverlay" class="account-cart-layer account-cart-tint"></div>
                <!-- Design-only transparent PNG overlay (exact match to composite, no coordinate math needed) -->
                <img id="cartDesignOverlay" src="" alt="" class="account-cart-layer account-cart-design" style="display:none;">
                <!-- Fallback: DOM-based positioned elements (used when no design-only PNG available) -->
                <div id="cartPreviewDesignArea" class="account-cart-design-area"></div>
            </div>
            <div id="cartProductName" class="popup-stage-name"></div>
            <div id="cartDesignName" class="popup-stage-meta"></div>
        </div>

        <div class="popup-field">
            <p class="popup-label"><?= t('account.cart_modal.select_size') ?></p>
            <div id="cartSizeOptions" class="popup-options"></div>
        </div>

        <div class="popup-field">
            <p class="popup-label"><?= t('account.cart_modal.select_color') ?></p>
            <div id="cartColorOptions" class="popup-options"></div>
        </div>

        <div class="popup-row">
            <label class="popup-label" for="cartQuantity"><?= t('account.cart_modal.quantity') ?></label>
            <div class="qty-stepper">
                <button type="button" data-on-click="adjustCartQuantity" data-args='[-1]' aria-label="&minus;">&minus;</button>
                <input id="cartQuantity" type="number" value="1" min="1" max="100">
                <button type="button" data-on-click="adjustCartQuantity" data-args='[1]' aria-label="+">+</button>
            </div>
        </div>

        <div id="cartPriceSummary" class="popup-prices">
            <div class="popup-price-row">
                <span><?= t('account.cart_modal.base_price') ?></span>
                <span id="cartBasePrice">€0.00</span>
            </div>
            <div class="popup-price-row popup-price-total">
                <span><?= t('account.cart_modal.total') ?></span>
                <span id="cartTotalPrice">€0.00</span>
            </div>
        </div>

        <div id="cartError" class="popup-error" style="display:none;"></div>

        <div class="popup-actions">
            <button type="button" id="confirmAddToCartBtn" class="btn btn-lg"><?= t('account.cart_modal.cta') ?></button>
        </div>
    </div>
</div>
<?php $overlays = ($overlays ?? '') . ob_get_clean(); ?>

<script src="<?= htmlspecialchars(Asset::url('/js/pages/account.js')) ?>" defer></script>
</section>
<?php require View::path('layouts/customer_footer'); ?>
