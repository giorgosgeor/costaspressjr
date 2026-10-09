<?php
$title        = t('info.sizing.title', false);
$infoTitle    = t('info.sizing.title', false);
$infoSubtitle = t('info.sizing.subtitle', false);
$infoUpdated  = '08/10/2026';
ob_start();
?>
<h2><?= t('info.sizing.h1') ?></h2>
<p><?= t('info.sizing.p1') ?></p>
<ul>
    <li><?= t('info.sizing.chest', false) ?></li>
    <li><?= t('info.sizing.length', false) ?></li>
</ul>

<?php // Each garment's chart from its maker (products.size_chart_image) — the
      // same one its product page and the designer open. Garments cut the
      // same share a chart, so each chart lists every garment it covers. ?>
<h2><?= t('info.sizing.h2') ?></h2>
<?php foreach ($sizeCharts as $chart): ?>
<figure class="size-chart-figure">
    <figcaption><?= e(implode(' · ', $chart['products'])) ?></figcaption>
    <img src="<?= e(web_path($chart['image'])) ?>" alt="<?= e(t('info.sizing.chart_alt', false, ['products' => implode(', ', $chart['products'])])) ?>" loading="lazy">
</figure>
<?php endforeach; ?>
<?php
$infoBody = ob_get_clean();
require View::path('pages/info/_layout');
