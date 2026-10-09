<?php $title = I18n::t('order.title', ['id' => (int)$order['id']]); $verifyBannerAlways = true; ?>
<?php $pageCss[] = '/css/pages/order.css'; require View::path('layouts/customer_header'); ?>
<?= View::script('/js/pages/order.js') ?>


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
$paidAmount  = (float)($order['payment_amount'] ?? $order['total_price']);
$refundedAmt = $order['refunded_amount'] !== null ? (float)$order['refunded_amount'] : null;
// A cancellation's sums, before it happens (OrderCancellation::quote, in cents).
$q = !empty($quote) ? ['refund' => money($quote['refund'] / 100), 'paid' => money($quote['paid'] / 100), 'fee' => money($quote['fee'] / 100)] : null;
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

    <?php if ($notice === 'cancelled'): ?>
    <?php // A pre-made design is refunded in full; otherwise minus the fee. ?>
    <div class="alert alert-success od-notice" role="status"><?= !empty($hasPremade)
        ? I18n::t('order.cancel.done_full', ['refund' => money($refundedAmt ?? $paidAmount)])
        : ($refundedAmt !== null
            ? I18n::t('order.cancel.done', ['refund' => money($refundedAmt), 'paid' => money($paidAmount), 'fee' => money(max(0, $paidAmount - $refundedAmt))])
            : t('order.cancel.done_plain')) ?></div>
    <?php elseif ($notice === 'not_allowed'): ?>
    <div class="alert alert-error od-notice" role="alert"><?= t('order.cancel.not_allowed') ?></div>
    <?php elseif ($notice === 'refund_failed'): ?>
    <div class="alert alert-error od-notice" role="alert"><?= t('order.cancel.failed') ?></div>
    <?php endif; ?>

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
            <?php if (in_array($order['payment_status'] ?? '', ['refunded', 'partially_refunded'], true)): ?>
            <div class="od-row od-refund">
                <span><?= t('order.info.refund') ?></span>
                <span>
                <?php if ($refundedAmt !== null && $refundedAmt < $paidAmount): ?>
                    <?= I18n::t('order.info.refunded', ['amount' => money($refundedAmt)]) ?>
                    <small><?= I18n::t('order.info.fee_kept', ['fee' => money($paidAmount - $refundedAmt)]) ?></small>
                <?php else: ?>
                    <?= I18n::t('order.info.refunded', ['amount' => money($refundedAmt ?? $paidAmount)]) ?>
                <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>

            <?php if (!empty($canCancel)): ?>
            <?php // Only while the order is still pending (OrderCancellation):
                  // everything back with a pre-made design, otherwise minus the
                  // payment processing fee. ?>
            <div class="od-cancel">
                <p class="od-cancel-lead"><?= !empty($hasPremade)
                    ? t('order.cancel.lead_full')
                    : ($q ? I18n::t('order.cancel.lead_quote', $q) : t('order.cancel.lead')) ?></p>
                <button type="button" class="btn btn-danger btn-block" data-on-click="openCancelOrder"><?= t('order.cancel.button') ?></button>
            </div>
            <?php elseif (in_array($status, ['processing', 'in-transit', 'delivered'], true)): ?>
            <?php // In production: pre-made designs can still be withdrawn within
                  // 14 days of collection, by telling the shop; custom ones can't. ?>
            <p class="od-policy"><?= t(!empty($hasPremade) ? 'order.policy.ready_made' : 'order.policy.final') ?> <a href="/returns"><?= t('order.policy.link') ?></a></p>
            <?php endif; ?>
            <p class="od-help"><?= t('assistant.answer.human', false) ?> <a href="/contact"><?= t('assistant.link.contact') ?></a></p>
        </aside>
    </div>

</div>
</section>

<?php if (!empty($canCancel)):
ob_start(); ?>
<div id="cancelOrderOverlay" class="confirm-overlay" data-on-click="closeCancelOrder" data-click-self role="dialog" aria-modal="true" aria-labelledby="cancelOrderTitle">
    <div class="confirm-dialog">
        <h3 id="cancelOrderTitle" class="confirm-title"><?= t('order.cancel.title') ?></h3>
        <p class="confirm-lead"><?= !empty($hasPremade)
            ? ($q ? I18n::t('order.cancel.confirm_lead_full', $q) : t('order.cancel.confirm_lead_full_plain'))
            : ($q ? I18n::t('order.cancel.confirm_lead', $q) : t('order.cancel.confirm_lead_plain')) ?></p>
        <form method="post" action="/orders/cancel" class="confirm-actions" data-cancel-order-form>
            <?= Csrf::field() ?>
            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
            <button type="button" class="confirm-btn confirm-btn-secondary" data-on-click="closeCancelOrder"><?= t('order.cancel.keep') ?></button>
            <button type="submit" class="confirm-btn confirm-btn-danger"><?= t('order.cancel.confirm') ?></button>
        </form>
    </div>
</div>
<?php $overlays = ($overlays ?? '') . ob_get_clean();
endif; ?>

<?php require View::path('layouts/customer_footer'); ?>
