<?php $title = t('orders.title', false); ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<style>
.orders-page {
    min-height: 100vh;
    background: var(--gradient-dark);
    padding: 40px 0 80px;
}
.orders-container {
    max-width: 860px;
    margin: 0 auto;
    padding: 2rem;
}
.orders-heading {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-light);
    margin-bottom: 1.75rem;
}
.orders-empty {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-light-secondary);
}
.orders-empty p {
    margin-bottom: 1.5rem;
    font-size: 1.05rem;
}
.order-row {
    background: var(--bg-card-dark);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.875rem;
    text-decoration: none;
    color: inherit;
    transition: border-color 0.2s, background 0.2s;
}
.order-row:hover {
    border-color: var(--primary, #15130E);
    background: rgba(125,128,218,0.06);
}
.order-row-left { flex: 1; min-width: 0; }
.order-id {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-light);
    margin-bottom: 0.2rem;
}
.order-meta {
    font-size: 0.85rem;
    color: var(--text-light-muted);
}
.order-status-badge {
    display: inline-block;
    padding: 0.3rem 0.85rem;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 600;
    border: 1px solid;
    white-space: nowrap;
}
.order-row-right {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-shrink: 0;
}
.order-total {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-light);
    white-space: nowrap;
}
.order-arrow {
    color: var(--text-light-muted);
    flex-shrink: 0;
}
@media (max-width: 580px) {
    .orders-container { padding: 1rem; }
    .orders-heading { font-size: 1.5rem; }
    .order-row { flex-direction: column; align-items: flex-start; }
    .order-row-right { width: 100%; justify-content: space-between; }
}
</style>

<section class="orders-page">
<div class="orders-container">
    <h1 class="orders-heading"><?= t('orders.title') ?></h1>

    <?php if (empty($orders)): ?>
    <div class="orders-empty">
        <p><?= t('orders.empty') ?></p>
        <a href="/shop" class="btn btn-primary"><?= t('orders.shop_now') ?></a>
    </div>
    <?php else: ?>

    <?php
    $statusColors = [
        'pending'    => ['color' => '#facc15', 'border' => 'rgba(234,179,8,0.5)',   'bg' => 'rgba(234,179,8,0.1)'],
        'processing' => ['color' => '#60a5fa', 'border' => 'rgba(59,130,246,0.5)',  'bg' => 'rgba(59,130,246,0.1)'],
        'in-transit' => ['color' => '#a78bfa', 'border' => 'rgba(139,92,246,0.5)',  'bg' => 'rgba(139,92,246,0.1)'],
        'delivered'  => ['color' => '#4ade80', 'border' => 'rgba(34,197,94,0.5)',   'bg' => 'rgba(34,197,94,0.1)'],
        'cancelled'  => ['color' => '#f87171', 'border' => 'rgba(239,68,68,0.5)',   'bg' => 'rgba(239,68,68,0.1)'],
    ];
    $statusKeys = [
        'pending'    => 'order.status.pending',
        'processing' => 'order.status.processing',
        'in-transit' => 'order.status.in_transit',
        'delivered'  => 'order.status.delivered',
        'cancelled'  => 'order.status.cancelled',
    ];
    foreach ($orders as $o):
        $sc  = $statusColors[$o['status']] ?? $statusColors['pending'];
        $lbl = isset($statusKeys[$o['status']]) ? t($statusKeys[$o['status']]) : htmlspecialchars(ucfirst($o['status']));
    ?>
    <a href="/orders/view?id=<?= (int)$o['id'] ?>" class="order-row">
        <div class="order-row-left">
            <div class="order-id"><?= t('orders.order_number', false) ?> #<?= (int)$o['id'] ?></div>
            <div class="order-meta">
                <?= date('M j, Y', strtotime($o['created_at'])) ?>
                &middot;
                <?= I18n::t('orders.item_count', ['count' => (int)$o['item_count']]) ?>
            </div>
        </div>
        <div class="order-row-right">
            <span class="order-status-badge" style="color:<?= $sc['color'] ?>;border-color:<?= $sc['border'] ?>;background:<?= $sc['bg'] ?>;">
                <?= $lbl ?>
            </span>
            <span class="order-total">€<?= number_format((float)$o['total_price'], 2) ?></span>
            <svg class="order-arrow" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </div>
    </a>
    <?php endforeach; ?>

    <?php endif; ?>
</div>
</section>

<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
