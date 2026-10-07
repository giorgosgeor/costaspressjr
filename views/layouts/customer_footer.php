</main>
<?php // Full-screen pop-ups a page hands over in $overlays. They belong out here:
      // <main> has its own z-index, so inside it they would sit under the
      // sticky header. ?>
<?= $overlays ?? '' ?>

<?php
// Consent state for the cookie notice.
//
// This used to be read inline as `$user['cookie_accepted']`, with a fallback
// of 0 when $user was not set — and $user is only populated by a couple of
// controller actions. On every other page the fallback said "has not
// accepted", so once the notice actually started running it reappeared on
// each page no matter how many times it was dismissed.
//
// The session carries the answer now, and is filled from the users table on
// the first page that needs it, so this costs at most one query per session.
$cookieAccepted = CurrentUser::cookieAccepted($db ?? null, $user ?? null);
?>

    <?php if (!empty($checkoutMode)): ?>
    <footer class="co-footer">
        <div class="container co-footer-inner">
            <nav class="co-footer-links" aria-label="<?= t('footer.legal') ?>">
                <a href="/terms" target="_blank" rel="noopener"><?= t('footer.terms') ?></a>
                <a href="/privacy" target="_blank" rel="noopener"><?= t('footer.privacy') ?></a>
                <a href="/returns" target="_blank" rel="noopener"><?= t('footer.returns') ?></a>
                <a href="/contact" target="_blank" rel="noopener"><?= t('footer.contact') ?></a>
            </nav>
            <p><?= I18n::t('footer.copyright', ['year' => date('Y')]) ?></p>
        </div>
    </footer>
    <?php else: ?>
    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-section">
                    <a href="/" class="footer-brand">
                        <img src="/images/logo.png" alt="<?= t('site.brand') ?>">
                    </a>
                    <p><?= t('footer.tagline') ?></p>
                </div>
                <div class="footer-section">
                    <h4><?= t('footer.quick_links') ?></h4>
                    <ul>
                        <li><a href="/shop"><?= t('footer.shop') ?></a></li>
                        <li><a href="/about"><?= t('footer.about_us') ?></a></li>
                        <li><a href="/contact"><?= t('footer.contact') ?></a></li>
                        <li><a href="/faq"><?= t('footer.faq') ?></a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4><?= t('footer.customer_service') ?></h4>
                    <ul>
                        <li><a href="/shipping"><?= t('footer.shipping_info') ?></a></li>
                        <li><a href="/returns"><?= t('footer.returns') ?></a></li>
                        <li><a href="/sizing"><?= t('footer.size_guide') ?></a></li>
                        <li><a href="/track-order"><?= t('footer.track_order') ?></a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4><?= t('footer.legal') ?></h4>
                    <ul>
                        <li><a href="/terms"><?= t('footer.terms') ?></a></li>
                        <li><a href="/privacy"><?= t('footer.privacy') ?></a></li>
                        <li><a href="/cookies"><?= t('footer.cookies') ?></a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4><?= t('footer.connect') ?></h4>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.84c0-2.52 1.49-3.92 3.77-3.92 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.78-1.63 1.57v1.89h2.78l-.44 2.91h-2.34V22c4.78-.76 8.44-4.92 8.44-9.94z"/></svg>
                        </a>
                        <a href="#" aria-label="Instagram">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                        </a>
                        <a href="#" aria-label="Twitter">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </a>
                        <a href="#" aria-label="TikTok">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5.8 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1.84-.1z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p><?= I18n::t('footer.copyright', ['year' => date('Y')]) ?></p>
            </div>
        </div>
    </footer>
    <?php endif; // checkout mode ?>

    <?= View::json('i18n-data', ['locale' => I18n::locale(), 'messages' => I18n::all()]) ?>
    <?= View::script('/js/site/i18n.js') ?>
<?php // ---- Shop assistant ---------------------------------------------
      // Answers come from ShopAssistant.php, which reads the shop's own FAQ,
      // info pages, products table and pricing engine - so it can quote a
      // real bulk price instead of guessing one. No third-party script, no
      // API key, and nothing about the customer leaves this server.
      //
      // Rendered on every customer page except the studio, where the CSS
      // hides it: someone mid-design already has a tools drawer on one edge
      // and an action bar on the other. Not on checkout either: the floating
      // button sat on top of the Pay bar on phones, and help is linked there. ?>
<?php if (empty($checkoutMode)): ?>
<button type="button" class="assistant-launcher" id="assistantLauncher"
        aria-expanded="false" aria-controls="assistantPanel"
        aria-label="<?= t('assistant.open') ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
    <span class="assistant-launcher-label"><?= t('assistant.title') ?></span>
</button>

<div class="assistant-panel" id="assistantPanel" role="dialog" aria-modal="false"
     aria-labelledby="assistantTitle" hidden>
    <div class="assistant-head">
        <div>
            <h2 id="assistantTitle"><?= t('assistant.title') ?></h2>
            <p><?= t('assistant.subtitle') ?></p>
        </div>
        <button type="button" class="assistant-close" id="assistantClose"
                aria-label="<?= t('assistant.close') ?>">&times;</button>
    </div>

    <?php // aria-live so a screen reader hears each reply as it arrives,
          // rather than the panel changing silently underneath it. ?>
    <div class="assistant-log" id="assistantLog" aria-live="polite" aria-atomic="false"></div>

    <form class="assistant-form" id="assistantForm" autocomplete="off">
        <label class="visually-hidden" for="assistantInput"><?= t('assistant.placeholder') ?></label>
        <input type="text" id="assistantInput" name="q" maxlength="500"
               placeholder="<?= t('assistant.placeholder') ?>">
        <button type="submit"><?= t('assistant.send') ?></button>
    </form>

    <p class="assistant-note"><?= t('assistant.disclaimer') ?></p>
</div>
<?php endif; ?>

    <script src="<?= htmlspecialchars(Asset::url('/js/site/ui.js')) ?>" defer></script>
    <script src="<?= htmlspecialchars(Asset::url('/js/site/app.js')) ?>" defer></script>
    <script src="<?= htmlspecialchars(Asset::url('/js/site/assistant.js')) ?>" defer></script>
    <?= View::json('cookie-consent-data', ['signedIn' => Auth::check(), 'accepted' => (int)$cookieAccepted]) ?>
    <?= View::script('/js/site/cookie-consent.js') ?>
</body>
</html>
