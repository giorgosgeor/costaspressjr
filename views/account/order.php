<?php $title = I18n::t('order.title', ['id' => (int)$order['id']]); ?>
<?php require View::path('layouts/customer_header'); ?>

<style>
/* Was the last page still on the old dark theme (--gradient-dark,
   --bg-card-dark …). Rebuilt on the zine tokens with the same two-column
   shape as the cart, so an order reads like the receipt of the cart it
   came from. */
.od-page {
    padding-bottom: clamp(56px, 7vw, 112px);
}

.od-header-side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: var(--space-2);
}
.od-status {
    display: inline-block;
    padding: var(--space-1) var(--space-3);
    border-radius: var(--radius-pill);
    font-size: 0.85rem;
    font-weight: 700;
    border: 1px solid;
    white-space: nowrap;
}
.od-tracking {
    font-size: 0.85rem;
    color: var(--ink-muted);
}
.od-tracking a {
    letter-spacing: 0.06em;
    font-weight: 500;
    color: var(--st-ink);
    text-decoration: underline;
    text-decoration-color: var(--st-field);
    text-underline-offset: 3px;
}
.od-tracking a:hover { color: var(--st-red-txt); }

/* Progress */
.od-progress {
    background: var(--st-tile);
    border-radius: var(--st-r);
    padding: var(--space-5) var(--space-5) var(--space-4);
    margin-bottom: var(--space-6);
}
.od-steps {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    position: relative;
}
.od-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-2);
    text-align: center;
    position: relative;
}
/* Connector from this step's dot to the previous one. */
.od-step + .od-step::before {
    content: '';
    position: absolute;
    top: 13px;
    right: 50%;
    width: 100%;
    height: 2px;
    background: var(--border);
    z-index: 0;
}
.od-step.done::before,
.od-step.current::before { background: var(--ink); }
.od-step-dot {
    position: relative;
    z-index: 1;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid var(--border);
    background: var(--paper-2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--paper-2);
}
.od-step-dot svg { width: 14px; height: 14px; }
.od-step.done .od-step-dot {
    background: var(--ink);
    border-color: var(--ink);
}
.od-step.current .od-step-dot {
    background: var(--spot);
    border-color: var(--spot);
    box-shadow: 0 0 0 4px rgba(var(--spot-rgb), 0.18);
}
.od-step-label {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--ink-muted);
}
.od-step.done .od-step-label { color: var(--ink-soft); }
.od-step.current .od-step-label { color: var(--ink); font-weight: 700; }
.od-cancelled {
    padding: var(--space-3) var(--space-4);
    color: var(--bad);
    font-weight: 700;
    background: rgba(179, 39, 27, 0.08);
    border: 1px solid rgba(179, 39, 27, 0.35);
}

/* Items + receipt */
.od-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: var(--space-6);
    align-items: start;
}
.od-section-title {
    font-size: clamp(1.3rem, 1.7vw, 1.6rem);
    color: var(--st-ink);
    margin: 0 0 var(--space-4);
}
.od-items {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
}
.od-item {
    background: #FFFFFF;
    border: 1px solid var(--st-line);
    border-radius: var(--st-r);
    padding: 12px 20px 12px 12px;
    display: grid;
    grid-template-columns: 104px 1fr auto;
    gap: var(--space-4);
    align-items: center;
}
.od-item-img {
    width: 104px;
    height: 104px;
    background: var(--st-tile);
    border-radius: var(--st-r-img);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.od-item-img img { width: 100%; height: 100%; object-fit: contain; display: block; }
.od-item-img-empty { color: var(--ink-muted); }
.od-item-name {
    font-size: 1.1rem;
    font-weight: 500;
    letter-spacing: -0.01em;
    color: var(--st-ink);
    margin: 0 0 var(--space-2);
}
.od-item-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-2) var(--space-4);
    font-size: 0.88rem;
    color: var(--ink-soft);
}
.od-item-meta span { display: inline-flex; align-items: center; gap: 6px; }
.od-swatch {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 1px solid rgba(22, 19, 15, 0.25);
    flex-shrink: 0;
}
.od-tag {
    padding: 2px 10px;
    border-radius: var(--radius-pill);
    background: var(--st-tile);
    color: var(--st-ink);
    font-size: 0.78rem;
    font-weight: 500;
}
.od-item-price {
    text-align: right;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
    white-space: nowrap;
}
.od-item-calc { font-size: 0.85rem; color: var(--ink-muted); }
.od-item-total {
    font-size: 1.25rem;
    font-weight: 500;
    letter-spacing: -0.02em;
    color: var(--st-ink);
}

.od-receipt {
    background: var(--st-tile);
    border-radius: var(--st-r);
    padding: 28px;
    position: sticky;
    top: 100px;
}
.od-receipt .od-section-title {
    padding-bottom: var(--space-3);
    border-bottom: 1px solid rgba(13, 13, 13, 0.1);
}
.od-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: var(--space-4);
    padding: var(--space-2) 0;
    font-size: 0.95rem;
    color: var(--st-muted);
    border-bottom: 1px solid rgba(13, 13, 13, 0.08);
}
.od-row span:last-child { color: var(--st-ink); font-weight: 500; text-align: right; }
.od-row small { display: block; font-size: 0.8rem; color: var(--ink-muted); font-weight: 400; }
.od-row.total {
    border-bottom: none;
    border-top: 1px solid var(--st-ink);
    margin-top: var(--space-3);
    padding-top: var(--space-3);
    color: var(--st-ink);
    font-weight: 500;
}
.od-row.total span:last-child {
    font-size: 1.9rem;
    font-weight: 300;
    letter-spacing: -0.03em;
    color: var(--st-ink);
}
.od-help {
    margin: var(--space-4) 0 0;
    font-size: 0.85rem;
    color: var(--ink-muted);
}
.od-help a { color: var(--ink); font-weight: 600; }

@media (max-width: 900px) {
    .od-grid { grid-template-columns: 1fr; }
    .od-receipt { position: static; }
}
@media (max-width: 600px) {
    .od-header-side { align-items: flex-start; }
    .od-progress { padding: var(--space-4) var(--space-2) var(--space-3); }
    .od-step-label { font-size: 0.7rem; }
    .od-item { grid-template-columns: 72px 1fr; padding: var(--space-3); gap: var(--space-3); }
    .od-item-img { width: 72px; height: 72px; }
    .od-item-price {
        grid-column: 1 / -1;
        flex-direction: row;
        justify-content: space-between;
        align-items: baseline;
        border-top: 1px solid var(--st-line);
        padding-top: var(--space-2);
    }
}
</style>

<?php
// Same 800-weight tones as the order list, so a status looks identical on both pages.
$statusColors = [
    'pending'    => ['color' => '#92400E', 'border' => 'rgba(245,158,11,0.40)', 'bg' => 'rgba(245,158,11,0.12)'],
    'processing' => ['color' => '#1E40AF', 'border' => 'rgba(59,130,246,0.40)', 'bg' => 'rgba(59,130,246,0.12)'],
    'in-transit' => ['color' => '#5B21B6', 'border' => 'rgba(139,92,246,0.40)', 'bg' => 'rgba(139,92,246,0.12)'],
    'delivered'  => ['color' => '#166534', 'border' => 'rgba(34,197,94,0.40)',  'bg' => 'rgba(34,197,94,0.12)'],
    'cancelled'  => ['color' => '#991B1B', 'border' => 'rgba(239,68,68,0.40)',  'bg' => 'rgba(239,68,68,0.12)'],
];
$statusKeys = [
    'pending'    => 'order.status.pending',
    'processing' => 'order.status.processing',
    'in-transit' => 'order.status.in_transit',
    'delivered'  => 'order.status.delivered',
    'cancelled'  => 'order.status.cancelled',
];
$status      = $order['status'];
$sc          = $statusColors[$status] ?? $statusColors['pending'];
$statusLabel = isset($statusKeys[$status]) ? t($statusKeys[$status]) : htmlspecialchars(ucfirst($status));
$placedOn    = date('d/m/Y', strtotime($order['created_at']));
?>

<section class="section od-page">
<div class="container">

    <?php ob_start(); ?>
        <div class="od-header-side">
            <span class="od-status" style="color:<?= $sc['color'] ?>;border-color:<?= $sc['border'] ?>;background:<?= $sc['bg'] ?>;"><?= $statusLabel ?></span>
            <?php if (!empty($order['tracking_token'])): ?>
            <span class="od-tracking">
                <?= t('info.track.result_number') ?>:
                <a href="/track-order?code=<?= urlencode($order['tracking_token']) ?>"><?= htmlspecialchars($order['tracking_token']) ?></a>
            </span>
            <?php endif; ?>
        </div>
    <?php
        $headNote = ob_get_clean();
        $orderName = I18n::t('order.title', ['id' => (int)$order['id']]);
        $crumbs  = [[t('header.nav.home', false), '/'], [t('orders.title', false), '/orders'], [$orderName, null]];
        $heading = $orderName;
        $lead    = I18n::t('order.placed_on', ['date' => $placedOn]);
        require View::path('partials/page_head');
    ?>

    <div class="od-progress" aria-label="<?= t('order.timeline.title') ?>">
        <?php if ($status === 'cancelled'): ?>
            <div class="od-cancelled"><?= t('order.timeline.cancelled') ?></div>
        <?php else:
            $steps      = ['pending', 'processing', 'in-transit', 'delivered'];
            $currentIdx = array_search($status, $steps, true);
            if ($currentIdx === false) $currentIdx = 0;
            // A delivered order is finished, not "in progress" — show every step as done.
            if ($status === 'delivered') $currentIdx = count($steps);
        ?>
        <ol class="od-steps">
            <?php foreach ($steps as $i => $step):
                $cls = $i < $currentIdx ? 'done' : ($i === $currentIdx ? 'current' : '');
            ?>
            <li class="od-step <?= $cls ?>"<?= $cls === 'current' ? ' aria-current="step"' : '' ?>>
                <span class="od-step-dot" aria-hidden="true">
                    <?php if ($cls === 'done'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                    <?php endif; ?>
                </span>
                <span class="od-step-label"><?= t($statusKeys[$step]) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </div>

    <div class="od-grid">
        <div>
            <h2 class="od-section-title"><?= t('order.items.title') ?></h2>
            <div class="od-items">
                <?php
                $subtotal = 0.0;
                $totalDesignFees = 0.0;
                foreach ($orderItems as $item):
                    $unitPrice = (float)($item['unit_price'] ?? 0);
                    $qty       = (int)($item['quantity'] ?? 1);
                    $designFee = (float)($item['custom_design_fee'] ?? 0);
                    $lineTotal = ($unitPrice + $designFee) * $qty;
                    $subtotal        += $unitPrice * $qty;
                    $totalDesignFees += $designFee * $qty;

                    $imgSrc = null;
                    if (!empty($item['front_preview'])) {
                        $imgSrc = '/' . ltrim($item['front_preview'], '/');
                    } elseif (!empty($item['product_image'])) {
                        $imgSrc = '/' . ltrim($item['product_image'], '/');
                    }
                ?>
                <article class="od-item">
                    <div class="od-item-img">
                        <?php if ($imgSrc): ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($item['product_name'] ?? 'Product') ?>" loading="lazy">
                        <?php else: ?>
                            <svg class="od-item-img-empty" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M8 3 3 6l2 4 2-1v12h10V9l2 1 2-4-5-3a4 4 0 0 1-8 0z"/></svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="od-item-name"><?= htmlspecialchars($item['product_name'] ?? 'Custom Product') ?></h3>
                        <div class="od-item-meta">
                            <?php if (!empty($item['size_name'])): ?>
                            <span><?= t('cart.item.size') ?>: <strong><?= htmlspecialchars($item['size_name']) ?></strong></span>
                            <?php endif; ?>
                            <?php if (!empty($item['color_name'])): ?>
                            <span>
                                <?php if (!empty($item['color_hex'])): ?>
                                <i class="od-swatch" style="background:<?= htmlspecialchars($item['color_hex']) ?>;"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars($item['color_name']) ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($item['is_custom_design'])): ?>
                            <span class="od-tag"><?= t('order.item.custom') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="od-item-price">
                        <span class="od-item-calc">
                            <?= $qty ?> × €<?= number_format($unitPrice, 2) ?>
                            <?php if ($designFee > 0): ?>
                            <br><?= I18n::t('order.item.design_fee', ['price' => '€' . number_format($designFee, 2)]) ?>
                            <?php endif; ?>
                        </span>
                        <span class="od-item-total">€<?= number_format($lineTotal, 2) ?></span>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>

        <aside class="od-receipt">
            <h2 class="od-section-title"><?= t('cart.summary.title') ?></h2>
            <div class="od-row">
                <span><?= t('order.info.date_placed') ?></span>
                <span><?= $placedOn ?></span>
            </div>
            <div class="od-row">
                <span><?= t('order.info.payment') ?></span>
                <span>
                <?php if (!empty($order['payment_method'])): ?>
                    <?php if ($order['payment_method'] === 'card' && !empty($order['card_brand'])): ?>
                        <?= htmlspecialchars(ucfirst($order['card_brand'])) ?> •••• <?= htmlspecialchars($order['card_last4'] ?? '') ?>
                        <?php if (!empty($order['card_exp_month']) && !empty($order['card_exp_year'])): ?>
                        <small><?= t('order.info.exp') ?> <?= htmlspecialchars($order['card_exp_month']) ?>/<?= htmlspecialchars($order['card_exp_year']) ?></small>
                        <?php endif; ?>
                    <?php else:
                        $pmLabels = ['apple_pay' => 'Apple Pay', 'google_pay' => 'Google Pay', 'revolut_pay' => 'Revolut Pay', 'paypal' => 'PayPal', 'revolut' => 'Revolut'];
                    ?>
                        <?= htmlspecialchars($pmLabels[$order['payment_method']] ?? ucfirst(str_replace('_', ' ', $order['payment_method']))) ?>
                    <?php endif; ?>
                <?php else: ?>
                    <?= t('order.info.na') ?>
                <?php endif; ?>
                </span>
            </div>
            <?php
            // Orders from before collection-only checkout have no method.
            $pickup = !empty($order['pickup_point']) ? json_decode($order['pickup_point'], true) : null;
            if (($order['delivery_method'] ?? '') === 'acs_point' || ($order['delivery_method'] ?? '') === 'store'):
            ?>
            <div class="od-row">
                <span><?= t('checkout.pickup.summary_label') ?></span>
                <span>
                    <?php if ($order['delivery_method'] === 'acs_point' && is_array($pickup)): ?>
                        <?= htmlspecialchars($pickup['name'] ?? '') ?>
                        <small><?= htmlspecialchars(trim(($pickup['address'] ?? '') . ', ' . ($pickup['city'] ?? ''), ', ')) ?></small>
                    <?php else: ?>
                        <?= t('checkout.pickup.store') ?>
                        <?php if (Pickup::storeAddress() !== ''): ?><small><?= htmlspecialchars(Pickup::storeAddress()) ?></small><?php endif; ?>
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
            <div class="od-row">
                <span><?= t('cart.summary.subtotal') ?></span>
                <span>€<?= number_format($subtotal, 2) ?></span>
            </div>
            <?php if ($totalDesignFees > 0): ?>
            <div class="od-row">
                <span><?= t('order.summary.design_fees') ?></span>
                <span>€<?= number_format($totalDesignFees, 2) ?></span>
            </div>
            <?php endif; ?>
            <?php if ((float)($order['shipping_fee'] ?? 0) > 0): ?>
            <div class="od-row">
                <span><?= t('checkout.pickup.fee_label') ?></span>
                <span>€<?= number_format((float)$order['shipping_fee'], 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="od-row total">
                <span><?= t('cart.summary.total') ?></span>
                <span>€<?= number_format((float)$order['total_price'], 2) ?></span>
            </div>
            <p class="od-help"><?= t('assistant.answer.human', false) ?> <a href="/contact"><?= t('assistant.link.contact') ?></a></p>
        </aside>
    </div>

</div>
</section>

<?php require View::path('layouts/customer_footer'); ?>
