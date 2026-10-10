    </div>
    <script src="<?= htmlspecialchars(Asset::url('/js/site/ui.js')) ?>" defer></script>
    <script src="<?= htmlspecialchars(Asset::url('/js/site/app.js')) ?>" defer></script>
    <?php // The garment colours this page used (Tint::filterFor). ?>
    <?= Tint::svgDefs() ?>
</body>
</html>
