        </main>
    </div>
    <?php foreach ($extraJs ?? [] as $jsFile): ?>
        <script src="<?= BASE_URL ?>/assets/js/<?= e($jsFile) ?>" defer></script>
    <?php endforeach; ?>
</body>
</html>
