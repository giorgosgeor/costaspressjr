<?php $title = t('orders.title', false); ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<style>
/* This page was still on the old dark theme — --gradient-dark, --text-light and
   --bg-card-dark are leftovers from a palette the rest of the site moved off.
   Landing here from a light page looked like a different website. Now on the
   current tokens and spacing scale. */
.orders-page {
    min-height: 100vh;
    background: var(--paper);
    padding: var(--space-7) 0 var(--space-8);
}
.orders-container {
    max-width: 860px;
    margin: 0 auto;
    padding: 0 var(--space-5);
}
.orders-heading {
    font-size: 2rem;
    font-weight: 700;
    color: var(--ink);
    letter-spacing: var(--tracking-snug);
    margin-bottom: var(--space-6);
}
.orders-empty {
    text-align: center;
    padding: var(--space-8) var(--space-5);
    color: var(--ink-soft);
}
.orders-empty p {
    margin-bottom: var(--space-5);
    font-size: 1.05rem;
}
.order-row {
    background: var(--paper-2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: var(--space-4) var(--space-5);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
    margin-bottom: var(--space-3);
    text-decoration: none;
    color: inherit;
    transition: border-color var(--dur-fast), background-color var(--dur-fast), transform var(--dur-fast);
}
.order-row:hover {
    border-color: var(--border-strong);
    background: var(--paper-soft);
    transform: translateY(-1px);
}
.order-row:focus-visible { outline: 2px solid var(--spot); outline-offset: 2px; }
.order-row-left { flex: 1; min-width: 0; }
.order-id {
    font-size: 1rem;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 2px;
}
.order-meta {
    font-size: 0.85rem;
    color: var(--ink-muted);
}
.order-status-badge {
    display: inline-block;
    padding: var(--space-1) var(--space-3);
    border-radius: var(--radius-pill);
    font-size: 0.82rem;
    font-weight: 600;
    border: 1px solid;
    white-space: nowrap;
}
.order-row-right {
    display: flex;
    align-items: center;
    gap: var(--space-4);
    flex-shrink: 0;
}
.order-total {
    font-size: 1rem;
    font-weight: 700;
    color: var(--ink);
    white-space: nowrap;
}
.order-arrow {
    color: var(--ink-muted);
    flex-shrink: 0;
}
@media (max-width: 580px) {
    .orders-container { padding: 0 var(--space-4); }
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
    // Text tones are the 800-weight of each hue. The previous values (#facc15,
    // #4ade80 …) were picked for a dark card and fell to roughly 2:1 against
    // these pale tinted backgrounds once the page went light.
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
