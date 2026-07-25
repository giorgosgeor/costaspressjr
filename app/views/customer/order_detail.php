<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<style>
.order-detail-page {
    min-height: 100vh;
    background: var(--gradient-dark);
    padding: 40px 0 80px;
}

.order-detail-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem;
}

.order-detail-back {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--text-light-secondary);
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 500;
    margin-bottom: 1.5rem;
    transition: color 0.2s;
}

.order-detail-back:hover {
    color: var(--primary-light);
}

.order-detail-back svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.order-detail-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
}

.order-detail-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-light);
    margin: 0;
}

.od-status-badge {
    display: inline-block;
    padding: 0.4rem 1rem;
    border-radius: 999px;
    font-size: 0.9rem;
    font-weight: 600;
    border: 1px solid;
    letter-spacing: 0.02em;
}

/* Status timeline */
.order-timeline {
    background: var(--bg-card-dark);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
    padding: 1.5rem 2rem;
    margin-bottom: 1.5rem;
}

.order-timeline-title {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-light-muted);
    margin-bottom: 1.25rem;
}

.timeline-steps {
    display: flex;
    align-items: flex-start;
    position: relative;
}

.timeline-steps::before {
    content: '';
    position: absolute;
    top: 18px;
    left: 18px;
    right: 18px;
    height: 2px;
    background: var(--border-light);
    z-index: 0;
}

.timeline-step {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    z-index: 1;
}

.timeline-step-dot {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 2px solid var(--border-light);
    background: var(--bg-dark, #0f172a);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    color: var(--text-light-muted);
    transition: all 0.2s;
}

.timeline-step.completed .timeline-step-dot {
    background: var(--success, #4ade80);
    border-color: var(--success, #4ade80);
    color: #fff;
}

.timeline-step.current .timeline-step-dot {
    background: var(--primary, #15130E);
    border-color: var(--primary, #15130E);
    color: #fff;
    box-shadow: 0 0 0 4px rgba(102,126,234,0.25);
}

.timeline-step-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-light-muted);
    text-align: center;
    text-transform: capitalize;
}

.timeline-step.completed .timeline-step-label,
.timeline-step.current .timeline-step-label {
    color: var(--text-light);
}

.timeline-cancelled {
    text-align: center;
    padding: 0.75rem;
    color: #f87171;
    font-weight: 600;
    font-size: 0.95rem;
    background: rgba(239,68,68,0.1);
    border-radius: var(--radius-md);
    border: 1px solid rgba(239,68,68,0.3);
}

/* Info section */
.order-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.order-info-card {
    background: var(--bg-card-dark);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
    padding: 1.25rem 1.5rem;
}

.order-info-card-label {
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-light-muted);
    margin-bottom: 0.4rem;
}

.order-info-card-value {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-light);
}

.order-info-card-value.highlight {
    color: var(--success, #4ade80);
    font-size: 1.2rem;
}

/* Items */
.od-section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-light);
    margin-bottom: 1rem;
}

.od-items-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.od-item-card {
    background: var(--bg-card-dark);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
    padding: 1rem 1.25rem;
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.od-item-image {
    width: 80px;
    height: 80px;
    border-radius: var(--radius-md);
    overflow: hidden;
    background: rgba(255,255,255,0.06);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.od-item-image img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.od-item-image-placeholder {
    font-size: 1.75rem;
    color: var(--text-light-muted);
}

.od-item-info {
    flex: 1;
    min-width: 0;
}

.od-item-name {
    font-weight: 700;
    font-size: 1rem;
    color: var(--text-light);
    margin-bottom: 0.35rem;
}

.od-item-attrs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem 1rem;
    margin-bottom: 0.5rem;
}

.od-item-attr {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.85rem;
    color: var(--text-light-secondary);
}

.od-color-swatch {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 1px solid rgba(255,255,255,0.2);
    display: inline-block;
    flex-shrink: 0;
}

.od-item-custom-badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    background: rgba(102,126,234,0.15);
    color: var(--primary-light, #a5b4fc);
    border: 1px solid rgba(102,126,234,0.3);
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.od-item-pricing {
    text-align: right;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.25rem;
}

.od-item-qty {
    font-size: 0.82rem;
    color: var(--text-light-muted);
}

.od-item-unit {
    font-size: 0.85rem;
    color: var(--text-light-secondary);
}

.od-item-fee {
    font-size: 0.82rem;
    color: var(--text-light-muted);
}

.od-item-line-total {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-light);
    border-top: 1px solid var(--border-light);
    padding-top: 0.25rem;
    margin-top: 0.1rem;
}

/* Summary */
.od-summary {
    background: var(--bg-card-dark);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
    padding: 1.25rem 1.5rem;
    max-width: 380px;
    margin-left: auto;
}

.od-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.4rem 0;
    font-size: 0.95rem;
    color: var(--text-light-secondary);
}

.od-summary-row.total {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-light);
    border-top: 1px solid var(--border-light);
    margin-top: 0.5rem;
    padding-top: 0.75rem;
}

.od-summary-row.total span:last-child {
    color: var(--success, #4ade80);
}

@media (max-width: 640px) {
    .order-detail-container {
        padding: 1rem;
    }
    .order-detail-title {
        font-size: 1.5rem;
    }
    .timeline-steps {
        gap: 0;
    }
    .timeline-step-label {
        font-size: 0.68rem;
    }
    .od-item-card {
        flex-direction: column;
    }
    .od-item-pricing {
        align-items: flex-start;
        flex-direction: row;
        flex-wrap: wrap;
        gap: 0.5rem 1rem;
    }
    .od-item-line-total {
        border-top: none;
        padding-top: 0;
        margin-left: auto;
    }
    .od-summary {
        max-width: 100%;
    }
}
</style>

<section class="order-detail-page">
<div class="order-detail-container">

    <a href="/orders" class="order-detail-back">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        <?= t('order.back') ?>
    </a>

    <?php
    $statusColors = [
        'pending'    => ['bg' => 'rgba(234,179,8,0.15)',   'color' => '#facc15', 'border' => 'rgba(234,179,8,0.4)'],
        'processing' => ['bg' => 'rgba(59,130,246,0.15)',  'color' => '#60a5fa', 'border' => 'rgba(59,130,246,0.4)'],
        'in-transit' => ['bg' => 'rgba(139,92,246,0.15)',  'color' => '#a78bfa', 'border' => 'rgba(139,92,246,0.4)'],
        'delivered'  => ['bg' => 'rgba(34,197,94,0.15)',   'color' => '#4ade80', 'border' => 'rgba(34,197,94,0.4)'],
        'cancelled'  => ['bg' => 'rgba(239,68,68,0.15)',   'color' => '#f87171', 'border' => 'rgba(239,68,68,0.4)'],
    ];
    $sc = $statusColors[$order['status']] ?? $statusColors['pending'];
    ?>

    <div class="order-detail-header">
        <h1 class="order-detail-title">Order #<?= htmlspecialchars($order['id']) ?></h1>
        <?php $odStatusKeys = ['pending'=>'order.status.pending','processing'=>'order.status.processing','in-transit'=>'order.status.in_transit','delivered'=>'order.status.delivered','cancelled'=>'order.status.cancelled']; ?>
        <span class="od-status-badge" style="background:<?= $sc['bg'] ?>; color:<?= $sc['color'] ?>; border-color:<?= $sc['border'] ?>;">
            <?= isset($odStatusKeys[$order['status']]) ? t($odStatusKeys[$order['status']]) : htmlspecialchars(ucfirst($order['status'])) ?>
        </span>
    </div>

    <!-- Status Timeline -->
    <div class="order-timeline">
        <div class="order-timeline-title"><?= t('order.timeline.title') ?></div>
        <?php if ($order['status'] === 'cancelled'): ?>
            <div class="timeline-cancelled"><?= t('order.timeline.cancelled') ?></div>
        <?php else:
            $steps = ['pending', 'processing', 'in-transit', 'delivered'];
            $currentIdx = array_search($order['status'], $steps);
            if ($currentIdx === false) $currentIdx = 0;
            $stepIcons = [
                'pending'    => '&#x23F3;',
                'processing' => '&#x2699;',
                'in-transit' => '&#x1F4E6;',
                'delivered'  => '&#x2713;',
            ];
        ?>
        <div class="timeline-steps">
            <?php foreach ($steps as $i => $step):
                if ($i < $currentIdx) $cls = 'completed';
                elseif ($i === $currentIdx) $cls = 'current';
                else $cls = '';
            ?>
            <div class="timeline-step <?= $cls ?>">
                <div class="timeline-step-dot"><?= $stepIcons[$step] ?></div>
                <?php $stepKeys = ['pending'=>'order.status.pending','processing'=>'order.status.processing','in-transit'=>'order.status.in_transit','delivered'=>'order.status.delivered']; ?>
                <div class="timeline-step-label"><?= isset($stepKeys[$step]) ? t($stepKeys[$step]) : htmlspecialchars(ucfirst($step)) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Order Info Cards -->
    <div class="order-info-grid">
        <div class="order-info-card">
            <div class="order-info-card-label"><?= t('order.info.date_placed') ?></div>
            <div class="order-info-card-value"><?= date('M j, Y', strtotime($order['created_at'])) ?></div>
        </div>
        <div class="order-info-card">
            <div class="order-info-card-label"><?= t('order.info.payment') ?></div>
            <div class="order-info-card-value">
                <?php if (!empty($order['payment_method'])): ?>
                    <?php if ($order['payment_method'] === 'card' && !empty($order['card_brand'])): ?>
                        <?= htmlspecialchars(ucfirst($order['card_brand'])) ?>
                        &bull;&bull;&bull;&bull; <?= htmlspecialchars($order['card_last4'] ?? '') ?>
                        <?php if (!empty($order['card_exp_month']) && !empty($order['card_exp_year'])): ?>
                            <span style="font-size:0.82rem; color:var(--text-light-muted); display:block;">
                                <?= t('order.info.exp') ?> <?= htmlspecialchars($order['card_exp_month']) ?>/<?= htmlspecialchars($order['card_exp_year']) ?>
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <?= htmlspecialchars(ucfirst($order['payment_method'])) ?>
                    <?php endif; ?>
                <?php else: ?>
                    <?= t('order.info.na') ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="order-info-card">
            <div class="order-info-card-label"><?= t('checkout.order_total') ?></div>
            <div class="order-info-card-value highlight">€<?= number_format((float)$order['total_price'], 2) ?></div>
        </div>
    </div>

    <!-- Items -->
    <div class="od-section-title"><?= t('order.items.title') ?></div>
    <div class="od-items-list">
        <?php
        $subtotal = 0.0;
        $totalDesignFees = 0.0;
        foreach ($orderItems as $item):
            $unitPrice = (float)($item['unit_price'] ?? 0);
            $qty = (int)($item['quantity'] ?? 1);
            $designFee = (float)($item['custom_design_fee'] ?? 0);
            $lineTotal = ($unitPrice + $designFee) * $qty;
            $subtotal += $unitPrice * $qty;
            $totalDesignFees += $designFee * $qty;

            $imgSrc = null;
            if (!empty($item['front_preview'])) {
                $imgSrc = '/' . ltrim($item['front_preview'], '/');
            } elseif (!empty($item['product_image'])) {
                $imgSrc = '/' . ltrim($item['product_image'], '/');
            }
        ?>
        <div class="od-item-card">
            <div class="od-item-image">
                <?php if ($imgSrc): ?>
                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($item['product_name'] ?? 'Product') ?>" loading="lazy">
                <?php else: ?>
                    <span class="od-item-image-placeholder">&#128247;</span>
                <?php endif; ?>
            </div>
            <div class="od-item-info">
                <div class="od-item-name"><?= htmlspecialchars($item['product_name'] ?? 'Custom Product') ?></div>
                <div class="od-item-attrs">
                    <?php if (!empty($item['size_name'])): ?>
                    <span class="od-item-attr">
                        <strong><?= t('cart.item.size') ?>:</strong> <?= htmlspecialchars($item['size_name']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($item['color_name'])): ?>
                    <span class="od-item-attr">
                        <?php if (!empty($item['color_hex'])): ?>
                        <span class="od-color-swatch" style="background:<?= htmlspecialchars($item['color_hex']) ?>;"></span>
                        <?php endif; ?>
                        <?= htmlspecialchars($item['color_name']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($item['is_custom_design'])): ?>
                    <span class="od-item-custom-badge"><?= t('order.item.custom') ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="od-item-pricing">
                <span class="od-item-qty"><?= I18n::t('order.item.qty_label', ['qty' => $qty]) ?></span>
                <span class="od-item-unit"><?= I18n::t('order.item.each', ['price' => '€' . number_format($unitPrice, 2)]) ?></span>
                <?php if ($designFee > 0): ?>
                <span class="od-item-fee"><?= I18n::t('order.item.design_fee', ['price' => '€' . number_format($designFee, 2)]) ?></span>
                <?php endif; ?>
                <span class="od-item-line-total">€<?= number_format($lineTotal, 2) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Order Summary -->
    <div class="od-summary">
        <div class="od-summary-row">
            <span><?= t('cart.summary.subtotal') ?></span>
            <span>€<?= number_format($subtotal, 2) ?></span>
        </div>
        <?php if ($totalDesignFees > 0): ?>
        <div class="od-summary-row">
            <span><?= t('order.summary.design_fees') ?></span>
            <span>€<?= number_format($totalDesignFees, 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="od-summary-row total">
            <span><?= t('cart.summary.total') ?></span>
            <span>€<?= number_format((float)$order['total_price'], 2) ?></span>
        </div>
    </div>

</div>
</section>

<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
