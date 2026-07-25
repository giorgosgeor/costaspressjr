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
                        <span class="contact-icon">📧</span>
                        <div>
                            <h4><?= t('contact.email') ?></h4>
                            <p><?= t('contact.email_value') ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <span class="contact-icon">📞</span>
                        <div>
                            <h4><?= t('contact.phone') ?></h4>
                            <p><?= t('contact.phone_value') ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <span class="contact-icon">📍</span>
                        <div>
                            <h4><?= t('contact.address') ?></h4>
                            <p><?= nl2br(t('contact.address_value')) ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <span class="contact-icon">🕐</span>
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
