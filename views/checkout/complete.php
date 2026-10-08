<?php
// Where every payment ends — see CheckoutController::complete().
// $state: placed | processing | not_paid | failed
// $result from OrderPlacement::place(); $placed from orderForConfirmation() (may be null)
$title    = $state === 'placed' ? t('checkout.success.title', false) : t('checkout.complete.title', false);
$extraCss = ['/css/pages/checkout.css'];
require View::path('layouts/customer_header');

?>

<section class="cc-page">
<div class="container cc-wrap">

<?php if ($state === 'placed'):
    $order    = $placed ?? null;
    $isAcs    = ($order['delivery_method'] ?? '') === 'acs_point';
    $point    = $order['point'] ?? null;
    $isOwner  = Auth::check() && (int)$result['user_id'] === (int)Auth::userId();
    $fee      = (float)($order['shipping_fee'] ?? 0);
    $method   = (string)($order['payment_method'] ?? '');
    $methodNames = ['card' => 'Card', 'link' => 'Link', 'paypal' => 'PayPal', 'revolut_pay' => 'Revolut Pay',
                    'apple_pay' => 'Apple Pay', 'google_pay' => 'Google Pay', 'amazon_pay' => 'Amazon Pay',
                    'bancontact' => 'Bancontact', 'eps' => 'EPS', 'mb_way' => 'MB WAY'];
    $paidWith = !empty($order['card_last4'])
        ? ucfirst((string)($order['card_brand'] ?: 'Card')) . ' •••• ' . $order['card_last4']
        : ($methodNames[$method] ?? ucwords(str_replace('_', ' ', $method)));
?>
    <div class="cc-hero">
        <span class="cc-stamp">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
            <?= t('checkout.success.title') ?>
        </span>
        <h1><?= t('checkout.complete.thanks_title') ?></h1>
        <p><?= t($isAcs ? 'checkout.success.next_acs' : 'checkout.success.next_store') ?></p>
    </div>

    <div class="cc-facts">
        <div class="cc-fact">
            <span class="cc-fact-label"><?= t('checkout.complete.order_number') ?></span>
            <span class="cc-fact-value">#<?= (int)$result['order_id'] ?></span>
        </div>
        <?php if (!empty($result['tracking'])): ?>
        <div class="cc-fact">
            <span class="cc-fact-label"><?= t('checkout.complete.tracking') ?></span>
            <span class="cc-fact-value">
                <code id="ccTracking"><?= htmlspecialchars($result['tracking']) ?></code>
                <button type="button" class="cc-copy" id="ccCopy" data-done="<?= t('checkout.complete.copied') ?>"><?= t('checkout.complete.copy') ?></button>
            </span>
            <p class="cc-fact-note"><?= t('checkout.success.tracking_note') ?></p>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($order): ?>
    <div class="cc-grid">
        <div class="cc-card">
            <h2><?= t('checkout.complete.next_title') ?></h2>
            <div class="cc-where">
                <span class="cc-where-label"><?= t('checkout.complete.collect_from') ?></span>
                <?php if ($isAcs && $point): ?>
                <strong><?= htmlspecialchars((string)$point['name']) ?></strong>
                <span><?= htmlspecialchars(trim(($point['address'] ?? '') . ', ' . ($point['city'] ?? ''), ', ')) ?></span>
                <?php else: ?>
                <strong><?= t('checkout.pickup.store') ?></strong>
                <?php if (Pickup::storeAddress() !== ''): ?>
                <span><?= htmlspecialchars(Pickup::storeAddress()) ?></span>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <ol class="cc-steps">
                <li><?= t('checkout.complete.step_print') ?></li>
                <li><?= t($isAcs ? 'checkout.success.next_acs' : 'checkout.success.next_store') ?></li>
                <li><?= t('checkout.complete.step_collect') ?></li>
            </ol>
            <?php if ($paidWith !== ''): ?>
            <p class="cc-paid"><?= t('checkout.complete.paid_with') ?> <strong><?= htmlspecialchars($paidWith) ?></strong></p>
            <?php endif; ?>
            <?php // The refund policy again, now that it applies to this order. ?>
            <p class="cc-policy"><?= t('checkout.complete.policy') ?></p>
        </div>

        <div class="cc-card">
            <h2><?= t('checkout.summary.title') ?></h2>
            <ul class="co-lines">
                <?php foreach ($order['items'] as $item):
                    $previews = !empty($item['preview_images']) ? json_decode($item['preview_images'], true) : null;
                    $image    = !empty($previews['front']) ? web_path($previews['front']) : (web_path($item['product_image'] ?? '') ?: '/images/placeholder.png');
                    $meta     = array_filter([$item['size_name'] ?? null, $item['color_name'] ?? null]);
                    $qty      = (int)$item['quantity'];
                    $line     = ((float)$item['unit_price'] + (float)($item['custom_design_fee'] ?? 0)) * $qty;
                ?>
                <li class="co-line">
                    <div class="co-thumb">
                        <div class="co-thumb-frame">
                            <img src="<?= htmlspecialchars($image) ?>" alt="" loading="lazy" data-fallback="/images/placeholder.png">
                        </div>
                        <span class="co-qty" aria-label="<?= htmlspecialchars(I18n::t('checkout.summary.qty', ['count' => $qty])) ?>"><?= $qty ?></span>
                    </div>
                    <div>
                        <p class="co-line-name"><?= htmlspecialchars((string)($item['product_name'] ?? '')) ?></p>
                        <?php if ($meta): ?><p class="co-line-meta"><?= htmlspecialchars(implode(' · ', $meta)) ?></p><?php endif; ?>
                    </div>
                    <p class="co-line-price"><?= money($line) ?></p>
                </li>
                <?php endforeach; ?>
            </ul>
            <dl class="co-totals">
                <div><dt><?= t('checkout.review.subtotal') ?></dt><dd><?= money((float)$order['total_price'] - $fee) ?></dd></div>
                <div><dt><?= t('checkout.pickup.summary_label') ?></dt><dd class="<?= $fee > 0 ? '' : 'is-free' ?>"><?= $fee > 0 ? money($fee) : t('checkout.pickup.free') ?></dd></div>
                <div class="co-total"><dt><?= t('checkout.review.total') ?></dt><dd><?= money($order['total_price']) ?></dd></div>
            </dl>
        </div>
    </div>
    <?php endif; ?>

    <div class="cc-actions">
        <?php if ($isOwner): ?>
        <a href="/orders/view?id=<?= (int)$result['order_id'] ?>" class="btn btn-lg"><?= t('checkout.success.view_order') ?></a>
        <?php else: ?>
        <a href="/track-order?code=<?= urlencode((string)$result['tracking']) ?>" class="btn btn-lg"><?= t('checkout.complete.track_order') ?></a>
        <?php endif; ?>
        <a href="/shop" class="btn btn-lg btn-secondary"><?= t('checkout.success.continue') ?></a>
    </div>

    <?= View::script('/js/pages/checkout-complete.js') ?>

<?php else: ?>
    <div class="cc-message">
        <div class="cc-hero">
        <?php if ($state === 'processing'): ?>
            <span class="cc-stamp is-pending"><?= t('checkout.complete.processing_stamp') ?></span>
            <h1><?= t('checkout.complete.processing_title') ?></h1>
            <p><?= t('checkout.complete.processing_body') ?></p>
        <?php elseif ($state === 'not_paid'): ?>
            <span class="cc-stamp is-pending"><?= t('checkout.complete.not_paid_stamp') ?></span>
            <h1><?= t('checkout.complete.not_paid_title') ?></h1>
            <p><?= t('checkout.complete.not_paid_body') ?></p>
        <?php else: ?>
            <span class="cc-stamp is-bad"><?= t('checkout.complete.failed_stamp') ?></span>
            <h1><?= t('checkout.complete.failed_title') ?></h1>
            <p><?= htmlspecialchars($message ?? t('checkout.errors.generic', false)) ?></p>
        <?php endif; ?>
        </div>
        <div class="cc-actions">
        <?php if ($state === 'processing'): ?>
            <a href="/shop" class="btn btn-lg btn-secondary"><?= t('checkout.success.continue') ?></a>
        <?php elseif ($state === 'not_paid'): ?>
            <a href="/checkout" class="btn btn-lg"><?= t('checkout.complete.try_again') ?></a>
            <a href="/cart" class="btn btn-lg btn-secondary"><?= t('checkout.complete.back_to_cart') ?></a>
        <?php else: ?>
            <a href="/contact" class="btn btn-lg"><?= t('assistant.link.contact') ?></a>
            <a href="/cart" class="btn btn-lg btn-secondary"><?= t('checkout.complete.back_to_cart') ?></a>
        <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

</div>
</section>

<?php require View::path('layouts/customer_footer'); ?>
