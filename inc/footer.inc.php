<?php
require_once __DIR__ . '/bootstrap.php';
$version = get_composer_version();
?>
<footer>
    <div class="credits">
        Made with ❤️ and ☕️ by <a href="https://valentinmuro.com" target="_blank" rel="noopener noreferrer">Valentin Muro</a>. <br>
        Open source code <a href="https://github.com/valenzine/newsletter-archive" target="_blank" rel="noopener noreferrer">available on GitHub</a>.
        <?php if ($version): ?>
            <br><small>Version: <?= htmlspecialchars($version) ?></small>
        <?php endif; ?>
    </div>
</footer>
