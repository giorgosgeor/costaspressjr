<?php $title = t('about.title', false); ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<section class="section page-section">
    <div class="container">
        <h1 class="page-title"><?= t('about.title') ?></h1>

        <div class="about-content">
            <div class="about-text">
                <h2><?= t('about.our_story') ?></h2>
                <p><?= t('about.story_p1') ?></p>

                <p><?= t('about.story_p2') ?></p>

                <h2><?= t('about.our_mission') ?></h2>
                <p><?= t('about.mission_lead') ?></p>
                <ul>
                    <li><?= t('about.mission_item1') ?></li>
                    <li><?= t('about.mission_item2') ?></li>
                    <li><?= t('about.mission_item3') ?></li>
                    <li><?= t('about.mission_item4') ?></li>
                </ul>

                <h2><?= t('about.quality_promise') ?></h2>
                <p><?= t('about.quality_lead') ?></p>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
