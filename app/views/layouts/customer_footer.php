</main>

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

    <script>
    /* Translation strings exposed to client-side JS. */
    window.I18N = <?= json_encode([
        'locale' => I18n::locale(),
        'messages' => I18n::all(),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.I18N.t = function(key, params) {
        var s = (this.messages && this.messages[key]) || key;
        if (params) {
            for (var k in params) {
                s = s.split('{' + k + '}').join(params[k]);
            }
        }
        return s;
    };
    </script>
    <script src="<?= htmlspecialchars(Asset::url('/js/app.js')) ?>" defer></script>
    <script>
    initCookiePopup(<?= json_encode(Auth::check()) ?>, <?= isset($user) ? (int)$user['cookie_accepted'] : 0 ?>);
    </script>
</body>
</html>
