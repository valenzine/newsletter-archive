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

// Check if this is a form submission
$import_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['mailchimp_zip'])) {
    $import_result = process_mailchimp_import(
        $_FILES['mailchimp_zip'],
        $campaigns_content_directory
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
            
            <h3>Prepare Your ZIP File</h3>
            <p>Your ZIP file must contain:</p>
            <ul>
                <li><strong>campaigns.csv</strong> - A CSV file with campaign metadata</li>
                <li><strong>HTML files</strong> - Campaign HTML files referenced in the CSV</li>
            </ul>
            
            <h4>Required CSV Format</h4>
            <p>The <code>campaigns.csv</code> file must have the following columns:</p>
            <table class="format-table">
                <thead>
                    <tr>
                        <th>Column</th>
                        <th>Required</th>
                        <th>Description</th>
                        <th>Example</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>subject</code></td>
                        <td>✓</td>
                        <td>Email subject line</td>
                        <td>Welcome to Our Newsletter</td>
                    </tr>
                    <tr>
                        <td><code>sent_at</code></td>
                        <td>✓</td>
                        <td>Date sent (any parseable format)</td>
                        <td>2024-01-15 10:30:00</td>
                    </tr>
                    <tr>
                        <td><code>html_file</code></td>
                        <td>✓</td>
                        <td>Filename of HTML file in ZIP</td>
                        <td>campaign_001.html</td>
                    </tr>
                    <tr>
                        <td><code>name</code></td>
                        <td></td>
                        <td>Internal campaign name</td>
                        <td>Welcome Series #1</td>
                    </tr>
                    <tr>
                        <td><code>preview_text</code></td>
                        <td></td>
                        <td>Email preheader/preview text</td>
                        <td>Get started with your account...</td>
                    </tr>
                </tbody>
            </table>
            
            <h4>Example CSV</h4>
            <pre>name,subject,sent_at,html_file,preview_text
"Welcome Campaign","Welcome to Our Newsletter","2024-01-15 10:30:00","campaign_001.html","Get started today"
"Weekly Update #1","This Week's News","2024-01-22 09:00:00","campaign_002.html","See what's new"</pre>
            
            <h3>Notes</h3>
            <ul>
                <li>Campaigns already in the database will be <strong>skipped</strong> (no duplicates)</li>
                <li>Imported campaigns will have <code>source = 'mailchimp'</code> in the database</li>
                <li>HTML files will be copied to <code><?= htmlspecialchars($campaigns_content_directory) ?></code></li>
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
