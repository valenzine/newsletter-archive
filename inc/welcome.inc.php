<?php
/**
 * Welcome Page Template
 * 
 * Displays a welcome overlay on first visit to introduce the archive
 * and provide subscription options.
 */

// This file should only be included when welcome page should be shown
$welcome_config = get_welcome_config();
?>

<div class="welcome-overlay" id="welcomeOverlay">
    <div class="welcome-container">
        <div class="welcome-content">
            <h1 class="welcome-title"><?= htmlspecialchars($welcome_config['title']) ?></h1>
            
            <div class="welcome-description">
                <?= nl2br(htmlspecialchars($welcome_config['description'])) ?>
            </div>
            
            <div class="welcome-actions">
                <?php if (!empty($welcome_config['subscribe_url'])): ?>
                    <a href="<?= htmlspecialchars($welcome_config['subscribe_url']) ?>" 
                       class="btn btn-primary btn-subscribe" 
                       target="_blank" 
                       rel="noopener noreferrer">
                        <?= htmlspecialchars($welcome_config['subscribe_text']) ?>
                    </a>
                <?php endif; ?>
                
                <button type="button" class="btn btn-secondary btn-enter-archive" id="enterArchiveBtn">
                    <?= htmlspecialchars($welcome_config['archive_button_text']) ?>
                </button>
            </div>
        </div>
    </div>
</div>
