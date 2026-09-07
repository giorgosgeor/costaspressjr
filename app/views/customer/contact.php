<?php $title = t('contact.title', false); ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<section class="section page-section">
    <div class="container">
        <h1 class="page-title"><?= t('contact.title') ?></h1>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <div class="contact-grid">
            <div class="contact-info">
                <h2><?= t('contact.get_in_touch') ?></h2>
                <p><?= t('contact.lead') ?></p>

                <div class="contact-details">
                    <div class="contact-item">
                        <span class="contact-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg></span>
                        <div>
                            <h4><?= t('contact.email') ?></h4>
                            <p><?= t('contact.email_value') ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <span class="contact-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
                        <div>
                            <h4><?= t('contact.phone') ?></h4>
                            <p><?= t('contact.phone_value') ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <span class="contact-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                        <div>
                            <h4><?= t('contact.address') ?></h4>
                            <p><?= nl2br(t('contact.address_value')) ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <span class="contact-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                        <div>
                            <h4><?= t('contact.hours') ?></h4>
                            <p><?= nl2br(t('contact.hours_value')) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="contact-form-container">
                <h2><?= t('contact.send_message') ?></h2>
                <form class="contact-form" action="/contact" method="post">
                    <?= Csrf::field() ?>
                    <div class="form-group">
                        <label for="name"><?= t('contact.name') ?></label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email"><?= t('contact.email') ?></label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="subject"><?= t('contact.subject') ?></label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label for="message"><?= t('contact.message') ?></label>
                        <textarea id="message" name="message" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-block"><?= t('contact.send_button') ?></button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
