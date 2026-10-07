<?php $title = t('about.title', false); ?>
<?php require View::path('layouts/customer_header'); ?>

<section class="section page-section">
    <div class="container">
        <?php
            $crumbs  = [[t('header.nav.home', false), '/'], [t('header.nav.about', false), null]];
            $heading = t('about.title', false);
            $lead    = t('about.subtitle', false);
            require View::path('partials/page_head');
        ?>

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

<?php require View::path('layouts/customer_footer'); ?>
