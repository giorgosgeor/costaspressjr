<?php
$title        = t('info.track.title', false);
$infoTitle    = t('info.track.h1', false);
$infoSubtitle = t('info.track.subtitle', false);
$infoUpdated  = date('F Y');
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
<form method="get" action="/track-order" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0.6rem 0 1rem;">
    <input type="text" name="code" value="<?= htmlspecialchars($trackResult['query'] ?? '') ?>"
           placeholder="<?= t('info.track.search_placeholder') ?>" maxlength="24"
           style="flex:1;min-width:220px;padding:10px 12px;border:1.5px solid var(--border,#ccc);border-radius:8px;font-size:1rem;letter-spacing:0.06em;text-transform:uppercase;">
    <button type="submit" class="btn"><?= t('info.track.search_btn') ?></button>
</form>

<?php if (isset($trackResult)): ?>
    <?php if (empty($trackResult['order'])): ?>
        <p style="color:#dc3545;"><?= t('info.track.not_found') ?></p>
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
        <div style="border:1.5px solid var(--border,#ccc);border-radius:10px;padding:16px 18px;margin-bottom:1rem;">
            <p style="margin:0 0 6px;"><strong><?= t('info.track.result_number') ?>:</strong>
                <span style="letter-spacing:0.08em;"><?= htmlspecialchars($trackOrder['tracking_token']) ?></span></p>
            <p style="margin:0 0 6px;"><strong><?= t('info.track.result_status') ?>:</strong> <?= $trackStatusLabel ?></p>
            <p style="margin:0 0 6px;"><strong><?= t('info.track.result_placed') ?>:</strong>
                <?= htmlspecialchars(date('d/m/Y', strtotime((string)$trackOrder['created_at']))) ?></p>
            <p style="margin:0;"><strong><?= t('info.track.result_total') ?>:</strong>
                €<?= number_format((float)$trackOrder['total_price'], 2) ?>
                (<?= (int)$trackOrder['total_products'] ?> <?= t('info.track.result_items') ?>)</p>
            <?php if (!empty($trackOrder['items'])): ?>
            <ul style="margin:10px 0 0;padding-left:18px;">
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
require __DIR__ . '/_layout.php';
