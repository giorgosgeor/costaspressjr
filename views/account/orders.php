<?php $title = t('orders.title', false); $verifyBannerAlways = true; ?>
<?php $pageCss[] = '/css/pages/orders.css'; require View::path('layouts/customer_header'); ?>


<section class="section orders-page">
<div class="container">
    <?php
        $crumbs  = [[t('header.nav.home', false), '/'], [t('header.my_account', false), '/account'], [t('orders.title', false), null]];
        $heading = t('orders.title', false);
        $lead    = t('orders.subtitle', false);
        require View::path('partials/page_head');
    ?>

    <?php if (empty($orders)): ?>
    <div class="empty-state">
        <p><?= t('orders.empty') ?></p>
        <a href="/shop" class="btn btn-lg"><?= t('orders.shop_now') ?></a>
    </div>
    <?php else: ?>
    <div class="orders-rows">

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
    </div>

    <?php endif; ?>
</div>
</section>

<?php require View::path('layouts/customer_footer'); ?>
