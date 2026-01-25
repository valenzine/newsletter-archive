<?php
require_once __DIR__ . '/bootstrap.php';
$version = get_composer_version();
?>
<footer>
    <hr>
    <div class="credits">
        Made with ❤️ by <a href="https://valentinmuro.com">Valentin Muro</a>.
        Open source code <a href="https://github.com/valenzine/newsletter-archive">available on GitHub</a>.
        <?php if ($version): ?>
            <br><small>Version: <?= htmlspecialchars($version) ?></small>
        <?php endif; ?>
    </div>
</footer>
