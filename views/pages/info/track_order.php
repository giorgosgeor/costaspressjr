<?php
$title        = t('info.track.title', false);
$infoTitle    = t('info.track.h1', false);
$infoSubtitle = t('info.track.subtitle', false);
$infoUpdated  = '2026-10-08';
ob_start();
?>
<?php if (\Auth::check()): ?>
<p><?= t('info.track.signed_in') ?></p>
<p><a href="/account" class="btn"><?= t('info.track.go') ?></a></p>
<?php else: ?>
<p><?= t('info.track.guest_intro') ?></p>
<p><?= t('info.track.guest_lead', false) ?></p>
<?php endif; ?>

<h2><?= t('info.track.search_title') ?></h2>
<form method="get" action="/track-order" class="track-form">
    <input type="text" name="code" value="<?= htmlspecialchars($trackResult['query'] ?? '') ?>"
           placeholder="<?= t('info.track.search_placeholder') ?>" maxlength="24"
           aria-label="<?= t('info.track.search_title') ?>" class="track-input">
    <button type="submit" class="btn"><?= t('info.track.search_btn') ?></button>
</form>

<?php if (isset($trackResult)): ?>
    <?php if (empty($trackResult['order'])): ?>
        <p class="track-not-found" role="alert"><?= t('info.track.not_found') ?></p>
    <?php else: ?>
        <?php
        $trackOrder = $trackResult['order'];
        $trackStatusKeys = [
            'pending'    => 'order.status.pending',
            'processing' => 'order.status.processing',
            'in-transit' => 'order.status.in_transit',
            'delivered'  => 'order.status.delivered',
            'cancelled'  => 'order.status.cancelled',
        ];
        $trackStatusLabel = isset($trackStatusKeys[$trackOrder['status']])
            ? t($trackStatusKeys[$trackOrder['status']])
            : htmlspecialchars(ucfirst((string)$trackOrder['status']));
        ?>
        <div class="track-result">
            <p><strong><?= t('info.track.result_number') ?>:</strong>
                <span class="track-code"><?= htmlspecialchars($trackOrder['tracking_token']) ?></span></p>
            <p><strong><?= t('info.track.result_status') ?>:</strong> <?= $trackStatusLabel ?></p>
            <p><strong><?= t('info.track.result_placed') ?>:</strong>
                <?= htmlspecialchars(date('d/m/Y', strtotime((string)$trackOrder['created_at']))) ?></p>
            <p><strong><?= t('info.track.result_total') ?>:</strong>
                €<?= number_format((float)$trackOrder['total_price'], 2) ?>
                (<?= (int)$trackOrder['total_products'] ?> <?= t('info.track.result_items') ?>)</p>
            <?php if (!empty($trackOrder['items'])): ?>
            <ul>
                <?php foreach ($trackOrder['items'] as $ti): ?>
                <li><?= (int)$ti['quantity'] ?>× <?= htmlspecialchars($ti['product_name'] ?? 'Product') ?><?php
                    $bits = array_filter([$ti['size_name'] ?? '', $ti['color_name'] ?? '']);
                    if ($bits) echo ' — ' . htmlspecialchars(implode(', ', $bits));
                ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<h2><?= t('info.track.h2') ?></h2>
<p><?= t('info.track.p2') ?></p>

<h2><?= t('info.track.h3') ?></h2>
<p><?= t('info.track.p3', false) ?></p>
<?php
$infoBody = ob_get_clean();
require View::path('pages/info/_layout');
