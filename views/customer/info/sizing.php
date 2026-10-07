<?php
$title        = t('info.sizing.title', false);
$infoTitle    = t('info.sizing.title', false);
$infoSubtitle = t('info.sizing.subtitle', false);
$infoUpdated  = date('F Y');
ob_start();
?>
<h2><?= t('info.sizing.h1') ?></h2>
<p><?= t('info.sizing.p1') ?></p>
<ul>
    <li><?= t('info.sizing.chest', false) ?></li>
    <li><?= t('info.sizing.waist', false) ?></li>
    <li><?= t('info.sizing.length', false) ?></li>
</ul>

<h2><?= t('info.sizing.h2') ?></h2>
<table class="size-chart">
    <thead>
        <tr><th><?= t('info.sizing.col_size') ?></th><th><?= t('info.sizing.col_chest') ?></th><th><?= t('info.sizing.col_length') ?></th></tr>
    </thead>
    <tbody>
        <tr><td>XS</td><td>86–91</td><td>68</td></tr>
        <tr><td>S</td><td>91–96</td><td>70</td></tr>
        <tr><td>M</td><td>96–101</td><td>72</td></tr>
        <tr><td>L</td><td>101–106</td><td>74</td></tr>
        <tr><td>XL</td><td>106–114</td><td>76</td></tr>
        <tr><td>XXL</td><td>114–122</td><td>78</td></tr>
    </tbody>
</table>

<?php if (Env::get('APP_ENV', 'production') !== 'production'): ?>
<p class="info-disclaimer"><?= t('info.sizing.placeholder', false) ?></p>
<?php endif; ?>
<?php
$infoBody = ob_get_clean();
require __DIR__ . '/_layout.php';
