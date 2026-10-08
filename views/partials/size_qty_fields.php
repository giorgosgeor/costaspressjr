<?php
/**
 * The size × quantity grid and price summary of the add-to-cart pop-ups
 * (premade design, designer, account). public/js/lib/size-qty.js fills it.
 *
 * Optional: $sizeQtyLabelExtra — HTML placed beside the label (the
 * designer's size-guide link).
 */
?>
<div class="popup-field">
    <div class="popup-label size-qty-heading">
        <span><?= t('size_qty.title') ?></span>
        <?= $sizeQtyLabelExtra ?? '' ?>
    </div>
    <div class="size-qty-grid" data-sq-grid aria-live="polite"></div>
</div>

<div class="popup-prices">
    <div class="size-qty-lines" data-sq-lines></div>
    <div class="popup-price-row popup-price-total"><span data-sq-total-label><?= t('size_qty.total') ?></span><span data-sq-total>€0.00</span></div>
    <p class="size-qty-note" data-sq-note hidden></p>
</div>
