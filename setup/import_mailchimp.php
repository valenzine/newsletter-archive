<?php

/**
 * Mailchimp Import Admin Interface
 * 
 * Allows admin to upload and import Mailchimp campaigns from a ZIP export.
 */

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/session.inc.php';
require_once __DIR__ . '/../inc/admin_auth.php';
require_once __DIR__ . '/../inc/mailchimp_import.php';

// Verify admin access
if (!is_admin_authenticated()) {
    header('HTTP/1.1 401 Unauthorized');
    echo '<h1>Unauthorized</h1><p>Admin access required.</p>';
    exit;
}

// Define destination directory for imported campaigns
$mailchimp_campaigns_directory = dirname(__DIR__) . '/source/mailchimp_campaigns';

// Check if this is a form submission
$import_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['mailchimp_zip'])) {
    $import_result = process_mailchimp_import(
        $_FILES['mailchimp_zip'],
        $mailchimp_campaigns_directory
    );
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mailchimp Import | <?= htmlspecialchars($site_title) ?></title>
    <link rel="stylesheet" href="/css/admin.css?ver=<?= htmlspecialchars(get_composer_version()) ?>" />
<?php require_once __DIR__ . '/../inc/head.inc.php'; ?>
</head>
<body class="admin-page">

<div class="admin-header">
    <div class="admin-header-content">
        <h1>📦 Mailchimp Import</h1>
        <div class="admin-user-info">
            <a href="setup.php" class="btn">← Back to Dashboard</a>
        </div>
    </div>
</div>

<div class="admin-container">
    <div class="admin-content">
        
        <?php if ($import_result): ?>
            <div class="result-box <?= $import_result['success'] ? 'success' : 'error' ?>">
                <h3><?= $import_result['success'] ? '✓ Import Complete' : '✗ Import Failed' ?></h3>
                <p><?= htmlspecialchars($import_result['message']) ?></p>
                
                <?php if (isset($import_result['stats'])): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?= $import_result['stats']['total'] ?></div>
                            <div class="stat-label">Total in CSV</div>
                        </div>
                        <div class="stat-card success">
                            <div class="stat-value"><?= $import_result['stats']['imported'] ?></div>
                            <div class="stat-label">Imported</div>
                        </div>
                        <div class="stat-card warning">
                            <div class="stat-value"><?= $import_result['stats']['skipped'] ?></div>
                            <div class="stat-label">Skipped (duplicates)</div>
                        </div>
                        <div class="stat-card error">
                            <div class="stat-value"><?= $import_result['stats']['errors'] ?></div>
                            <div class="stat-label">Errors</div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($import_result['success'] && $import_result['stats']['imported'] > 0): ?>
                    <p class="hint">
                        <a href="/">View imported campaigns →</a>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="instructions">
            <h2>Import Mailchimp Campaigns</h2>
            <p>This tool allows you to do a one-time import of your Mailchimp campaign archive. This is useful when migrating from Mailchimp to MailerLite.</p>
            
            <h3>Step 1: Export Your Data from Mailchimp</h3>
            <ol>
                <li>Log in to your Mailchimp account</li>
                <li>Navigate to <strong>Account → Settings → Manage my data</strong></li>
                <li>Click <strong>"Export data"</strong></li>
                <li>Under "Choose which data you'd like to export", select <strong>Emails</strong></li>
                <li>Under "Date Limit", select <strong>All data</strong></li>
                <li>Click the export button</li>
                <li>Wait for Mailchimp to email you when the export is ready (could take a few minutes or up to 24 hs)</li>
                <li>Download the ZIP file from the email link</li>
            </ol>
            
            <h3>Step 2: Upload the ZIP File</h3>
            <p>Upload the ZIP file you downloaded from Mailchimp. The file will contain:</p>
            <ul>
                <li><strong>campaigns.csv</strong> - Campaign metadata (Title, Subject, Send Date, etc.)</li>
                <li><strong>campaigns_content/</strong> - Folder with HTML files for each campaign</li>
            </ul>
            
            <p><strong>Note:</strong> The import process will automatically match campaigns to HTML files. If some campaigns can't be matched, they'll be imported with metadata only (you can view them but they won't have content).</p>
            
            <h3>Notes</h3>
            <ul>
                <li>Campaigns already in the database will be <strong>skipped</strong> (no duplicates)</li>
                <li>Imported campaigns will have <code>source = 'mailchimp'</code> in the database</li>
                <li>HTML files will be copied to <code>/source/mailchimp_campaigns/</code></li>
                <li>After import, you can sync new campaigns from MailerLite via the <a href="mailerlite.php">MailerLite Sync</a> page</li>
            </ul>
        </div>

        <form method="POST" enctype="multipart/form-data" class="import-form">
            <div class="form-group">
                <label for="mailchimp_zip">Upload ZIP File</label>
                <input type="file" id="mailchimp_zip" name="mailchimp_zip" accept=".zip" required>
                <p class="help-text">Maximum file size: <?= ini_get('upload_max_filesize') ?></p>
            </div>
            
            <button type="submit" class="btn btn-primary">
                📦 Import Campaigns
            </button>
        </form>
        
    </div>
</div>

<style>
.instructions {
    background: #f8f9fa;
    border-left: 4px solid #447BA6;
    padding: 20px;
    margin-bottom: 30px;
    line-height: 1.6;
}

.instructions h2 {
    margin-top: 0;
}

.instructions h3 {
    margin-top: 20px;
    color: #333;
}

.instructions h4 {
    margin-top: 15px;
    font-size: 14px;
    color: #666;
}

.instructions ul {
    margin: 10px 0;
    padding-left: 25px;
}

.instructions li {
    margin: 5px 0;
}

.instructions code {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

.instructions pre {
    background: #fff;
    border: 1px solid #dee2e6;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.5;
}

.format-table {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
    font-size: 14px;
}

.format-table th,
.format-table td {
    border: 1px solid #dee2e6;
    padding: 10px;
    text-align: left;
}

.format-table th {
    background: #e9ecef;
    font-weight: 600;
}

.format-table code {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 3px;
}

.import-form {
    background: #fff;
    border: 1px solid #dee2e6;
    padding: 30px;
    border-radius: 4px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

.form-group input[type="file"] {
    display: block;
    width: 100%;
    padding: 10px;
    border: 2px dashed #ccc;
    border-radius: 4px;
    background: #f8f9fa;
    cursor: pointer;
}

.help-text {
    margin-top: 5px;
    font-size: 12px;
    color: #666;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.stat-card {
    text-align: center;
    padding: 15px;
    background: white;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.stat-card.success {
    border-color: #28a745;
    background: #f0fff4;
}

.stat-card.warning {
    border-color: #ffc107;
    background: #fffbf0;
}

.stat-card.error {
    border-color: #dc3545;
    background: #fff5f5;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #333;
}

.stat-label {
    font-size: 0.875rem;
    color: #666;
    margin-top: 5px;
}
</style>

</body>
</html>
