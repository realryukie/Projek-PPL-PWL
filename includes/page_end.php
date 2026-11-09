<?php
// Page scripts and document closing tags for user-facing pages.
$extraJs = $extraJs ?? [];
$footerScrollPath = __DIR__ . '/../assets/js/footer-scroll.js';
?>
<script src="<?= BASE_URL ?>/assets/js/footer-scroll.js?v=<?= (int) filemtime($footerScrollPath) ?>" defer></script>
<?php foreach ($extraJs as $jsFile): ?>
    <?php $jsPath = __DIR__ . '/../assets/js/' . $jsFile; ?>
    <script src="<?= BASE_URL ?>/assets/js/<?= e($jsFile) ?>?v=<?= (int) filemtime($jsPath) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
