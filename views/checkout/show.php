<?php
// GET /checkout — see CheckoutController::show().
// $cartItems, $cartTotal, $checkout, $accountEmail, $accountPhone
$title        = t('checkout.page.title', false);
$extraCss     = ['/css/pages/checkout.css'];
$checkoutMode = true;
require View::path('layouts/customer_header');

$acsAvailable = !empty($checkout['acsAvailable']);
$acsFee       = $checkout['acsFee'] ?? null;
$storeAddress = (string)($checkout['storeAddress'] ?? '');
$appUrl       = rtrim((string)Env::get('APP_URL', ''), '/');
if ($appUrl === '') {
    $appUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
$isGuest = !Auth::check();
$totalQty = (int)array_sum(array_column($cartItems, 'quantity'));
?>

<div class="co-page">
<div class="container co-container">

    <button type="button" class="co-summary-toggle" id="coSummaryToggle" aria-expanded="false" aria-controls="coSummary">
        <span class="co-summary-toggle-label">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <span data-when="closed"><?= t('checkout.summary.show') ?></span>
            <span data-when="open" hidden><?= t('checkout.summary.hide') ?></span>
            <svg class="co-summary-toggle-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
        </span>
        <strong data-co-total><?= money($cartTotal) ?></strong>
    </button>

    <div class="co-grid">

        <aside class="co-summary" id="coSummary" aria-labelledby="coSummaryTitle">
            <div class="co-summary-card">
                <div class="co-summary-head">
                    <h2 id="coSummaryTitle"><?= t('checkout.summary.title') ?></h2>
                    <a href="/cart"><?= t('checkout.summary.edit_cart') ?></a>
                </div>

                <ul class="co-lines">
                    <?php foreach ($cartItems as $item):
                        $name     = (string)($item['product_name'] ?? ('#' . $item['product_id']));
                        $preview  = !empty($item['front_preview']) ? web_path($item['front_preview']) : '';
                        $image    = $preview !== '' ? $preview : (web_path($item['product_image'] ?? '') ?: '/images/placeholder.png');
                        $tint     = ($preview === '' && !empty($item['color_hex'])) ? Tint::filterFor($item['color_hex']) : '';
                        $meta     = array_filter([
                            $item['size_name'] ?? null,
                            $item['color_name'] ?? null,
                            !empty($item['premade_design_name'])
                                ? I18n::t('cart.item.design_label', ['name' => $item['premade_design_name']])
                                : ((!empty($item['is_custom_design']) || !empty($item['custom_design_fee'])) ? t('cart.item.custom', false) : null),
                        ]);
                        $qty = (int)$item['quantity'];
                    ?>
                    <li class="co-line">
                        <div class="co-thumb">
                            <div class="co-thumb-frame">
                                <img src="<?= htmlspecialchars($image) ?>" alt="" loading="lazy"
                                     <?= $tint !== '' ? 'style="filter: ' . htmlspecialchars($tint) . ';"' : '' ?>
                                     data-fallback="/images/placeholder.png">
                                <?php if ($preview === '' && !empty($item['premade_design_image'])):
                                    $posX = (float)($item['premade_pos_x'] ?? 0);
                                    $posY = (float)($item['premade_pos_y'] ?? 0);
                                    $size = (float)($item['premade_pos_size'] ?? 55); ?>
                                <img class="co-thumb-overlay" src="<?= htmlspecialchars(web_path($item['premade_design_image'])) ?>" alt=""
                                     style="left:<?= 50 + $posX * 0.25 ?>%;top:<?= 55 + $posY * 0.375 ?>%;width:<?= $size * 0.5 ?>%;">
                                <?php endif; ?>
                            </div>
                            <span class="co-qty" aria-label="<?= htmlspecialchars(I18n::t('checkout.summary.qty', ['count' => $qty])) ?>"><?= $qty ?></span>
                        </div>
                        <div>
                            <p class="co-line-name"><?= htmlspecialchars($name) ?></p>
                            <?php if ($meta): ?>
                            <p class="co-line-meta"><?= htmlspecialchars(implode(' · ', $meta)) ?></p>
                            <?php endif; ?>
                        </div>
                        <p class="co-line-price"><?= money($item['line_total'] ?? 0) ?></p>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <dl class="co-totals">
                    <div>
                        <dt><?= I18n::t('checkout.summary.subtotal', ['count' => $totalQty]) ?></dt>
                        <dd data-co-subtotal><?= money($cartTotal) ?></dd>
                    </div>
                    <div>
                        <dt><?= t('checkout.pickup.summary_label') ?></dt>
                        <dd data-co-fee class="is-free"><?= t('checkout.pickup.free') ?></dd>
                    </div>
                    <div class="co-total">
                        <dt><?= t('checkout.review.total') ?></dt>
                        <dd data-co-total><?= money($cartTotal) ?></dd>
                    </div>
                </dl>

                <p class="co-summary-help">
                    <?= t('checkout.summary.help', false, ['contact' => '<a href="/contact" target="_blank" rel="noopener">' . t('checkout.summary.help_link', false) . '</a>']) ?>
                </p>
            </div>
        </aside>

        <div class="co-main">
            <h1 class="co-title"><?= t('checkout.page.title') ?></h1>

            <noscript><p class="co-noscript"><?= t('checkout.page.noscript') ?></p></noscript>

            <form id="checkoutForm" novalidate>

                <!-- 1 · Who's collecting -->
                <section class="co-section" id="coSectionDetails" aria-labelledby="coDetailsTitle">
                    <div class="co-section-head">
                        <span class="co-step" aria-hidden="true">
                            <span class="co-step-num">1</span>
                            <svg class="co-step-check" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        <h2 class="co-section-title" id="coDetailsTitle"><?= t('checkout.contact.title') ?></h2>
                    </div>

                    <div class="co-fields">
                        <div class="co-field">
                            <label for="coName"><?= t('checkout.contact.name') ?></label>
                            <input class="co-input" id="coName" name="name" type="text" autocomplete="name" maxlength="100" required
                                   aria-describedby="coNameErr">
                            <p class="co-field-error" id="coNameErr" hidden></p>
                        </div>
                        <div class="co-field">
                            <label for="coPhone"><?= t('checkout.contact.phone') ?></label>
                            <input class="co-input" id="coPhone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="30" required
                                   placeholder="+357 99 123456" value="<?= htmlspecialchars((string)($accountPhone ?? '')) ?>"
                                   aria-describedby="coPhoneHint coPhoneErr">
                            <p class="co-hint" id="coPhoneHint"><?= t('checkout.contact.phone_note') ?></p>
                            <p class="co-field-error" id="coPhoneErr" hidden></p>
                        </div>
                        <?php if ($isGuest): ?>
                        <div class="co-field co-field-full">
                            <label for="coEmail"><?= t('checkout.contact.email') ?></label>
                            <input class="co-input" id="coEmail" name="email" type="email" autocomplete="email" inputmode="email" maxlength="254" required
                                   spellcheck="false" autocapitalize="off" aria-describedby="coEmailHint coEmailErr">
                            <p class="co-hint" id="coEmailHint"><?= t('checkout.contact.email_note') ?></p>
                            <p class="co-field-error" id="coEmailErr" hidden></p>
                        </div>
                        <?php elseif (!empty($accountEmail)): ?>
                        <p class="co-account-line">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            <span><?= I18n::t('checkout.contact.account_email', ['email' => '<strong>' . htmlspecialchars($accountEmail) . '</strong>']) ?></span>
                        </p>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- 2 · How it's collected -->
                <section class="co-section" id="coSectionCollection" aria-labelledby="coCollectionTitle">
                    <div class="co-section-head">
                        <span class="co-step" aria-hidden="true">
                            <span class="co-step-num">2</span>
                            <svg class="co-step-check" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        <h2 class="co-section-title" id="coCollectionTitle"><?= t('checkout.steps.collection') ?></h2>
                    </div>

                    <fieldset class="co-options">
                        <legend class="visually-hidden"><?= t('checkout.pickup.title') ?></legend>
                        <label class="co-option">
                            <input type="radio" name="deliveryMethod" value="store" checked>
                            <span class="co-option-body">
                                <span class="co-option-head">
                                    <span class="co-option-title"><?= t('checkout.pickup.store') ?></span>
                                    <span class="co-option-price is-free"><?= t('checkout.pickup.free') ?></span>
                                </span>
                                <?php if ($storeAddress !== ''): ?>
                                <span class="co-option-note"><?= htmlspecialchars($storeAddress) ?></span>
                                <?php endif; ?>
                                <span class="co-option-note"><?= t('checkout.pickup.store_note') ?></span>
                            </span>
                        </label>
                        <?php if ($acsAvailable): ?>
                        <label class="co-option">
                            <input type="radio" name="deliveryMethod" value="acs_point">
                            <span class="co-option-body">
                                <span class="co-option-head">
                                    <span class="co-option-title"><?= t('checkout.pickup.acs') ?></span>
                                    <span class="co-option-price"><?= money($acsFee) ?></span>
                                </span>
                                <span class="co-option-note"><?= t('checkout.pickup.acs_note') ?></span>
                            </span>
                        </label>
                        <?php endif; ?>
                    </fieldset>

                    <?php if ($acsAvailable): ?>
                    <div class="acs-locator" id="acsLocator" hidden>
                        <div class="acs-locator-bar">
                            <button type="button" class="acs-locate-btn" id="acsLocateBtn">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="7"/></svg>
                                <?= t('checkout.pickup.use_location') ?>
                            </button>
                            <input type="search" id="acsSearch" class="co-input acs-search" placeholder="<?= t('checkout.pickup.search_placeholder') ?>"
                                   aria-label="<?= t('checkout.pickup.search_placeholder') ?>" autocomplete="off" enterkeyhint="search">
                        </div>
                        <p class="acs-status" id="acsStatus" role="status" aria-live="polite"></p>
                        <ul class="acs-list" id="acsList" hidden></ul>
                        <div class="acs-map" id="acsMap" role="region" aria-label="<?= t('checkout.pickup.map_label') ?>"></div>
                        <div class="acs-selected" id="acsSelected" hidden></div>
                        <p class="co-field-error" id="coPointErr" hidden></p>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- 3 · Payment -->
                <section class="co-section" id="coSectionPayment" aria-labelledby="coPaymentTitle">
                    <div class="co-section-head">
                        <span class="co-step" aria-hidden="true">
                            <span class="co-step-num">3</span>
                            <svg class="co-step-check" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        <h2 class="co-section-title" id="coPaymentTitle"><?= t('checkout.steps.payment') ?></h2>
                    </div>
                    <p class="co-section-lead"><?= t('checkout.payment.lead') ?></p>

                    <?php // Stripe draws its own loading skeleton, styled by the
                          // appearance settings in pages/checkout.js. ?>
                    <div class="co-pe">
                        <div id="paymentElement"></div>
                    </div>
                    <p class="visually-hidden" id="peStatus" role="status"><?= t('checkout.payment.loading') ?></p>
                </section>

                <?php // Required before paying: pages/checkout.js stops at it, and
                      // /api/create-payment-intent refuses a payment without it. ?>
                <div class="co-terms" id="coTerms">
                    <label class="co-terms-label">
                        <input type="checkbox" id="termsAccept" aria-required="true" aria-describedby="termsError">
                        <span><?= t('checkout.terms_accept', false, [
                            'terms'   => '<a href="/terms" target="_blank" rel="noopener">' . t('checkout.review.terms_link', false) . '</a>',
                            'privacy' => '<a href="/privacy" target="_blank" rel="noopener">' . t('checkout.review.privacy_link', false) . '</a>',
                        ]) ?></span>
                    </label>
                    <p class="co-terms-error" id="termsError" hidden><?= t('checkout.errors.terms') ?></p>
                </div>

                <div class="co-alert" id="payError" role="alert" hidden>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span id="payErrorText"></span>
                </div>

                <div class="co-paybar">
                    <button type="submit" class="co-pay" id="payBtn">
                        <span class="co-pay-icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="1"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        </span>
                        <span class="co-pay-label" id="payLabel"><?= t('checkout.pay') ?> <span data-co-total><?= money($cartTotal) ?></span></span>
                    </button>
                </div>

                <ul class="co-trust">
                    <li>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="1"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        <?= t('checkout.badge.stripe') ?>
                    </li>
                    <li>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <?= t('checkout.badge.no_card') ?>
                    </li>
                    <li>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <?= t('checkout.badge.encrypted') ?>
                    </li>
                </ul>
            </form>
        </div>

    </div>
</div>
</div>

<script type="application/json" id="checkoutConfig"><?= json_encode([
    'stripeKey'     => (string)Env::get('STRIPE_PUBLISHABLE_KEY', ''),
    'locale'        => I18n::locale() === 'el' ? 'el' : 'en',
    'subtotalCents' => (int)round((float)$cartTotal * 100),
    'acsFeeCents'   => $acsAvailable ? (int)round((float)$acsFee * 100) : null,
    'accountEmail'  => $accountEmail ?? null,
    'isGuest'       => $isGuest,
    'business'      => t('site.brand', false),
    'returnUrl'     => $appUrl . '/checkout/complete',
    'leafletCss'    => 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css',
    'leafletJs'     => 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>

<script src="https://js.stripe.com/v3/"></script>
<script src="<?= htmlspecialchars(Asset::url('/js/pages/checkout.js')) ?>" defer></script>

<?php require View::path('layouts/customer_footer'); ?>
