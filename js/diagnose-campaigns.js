/**
 * Campaign Diagnostics Tool - Event Handlers
 * Handles SSE-based recovery, repair, and filename fixing operations
 */

document.addEventListener('DOMContentLoaded', function() {
    // Load configuration from data attributes
    const config = document.getElementById('app-config');
    const CSRF_TOKEN = config ? config.dataset.csrfToken : '';
    const RECOVERABLE_CAMPAIGNS = config && config.dataset.recoverableCampaigns ? JSON.parse(config.dataset.recoverableCampaigns) : [];
    const MISSING_CAMPAIGN_IDS = config && config.dataset.missingCampaignIds ? JSON.parse(config.dataset.missingCampaignIds) : [];
    
    /**
     * Initialize recovery button for MailerLite campaigns
     */
    const recoverBtn = document.getElementById('recover-btn');
    if (recoverBtn) {
        const progressConsole = document.getElementById('progress-console');
        const recoverableCampaigns = RECOVERABLE_CAMPAIGNS;
        
        recoverBtn.addEventListener('click', async function() {
            if (!confirm(`This will attempt to recover ${recoverableCampaigns.length} campaigns from MailerLite. This may take several minutes. Continue?`)) {
                return;
            }
            
            recoverBtn.disabled = true;
            recoverBtn.textContent = 'Recovering...';
            progressConsole.classList.add('active');
            progressConsole.innerHTML = '';
            
            const formData = new FormData();
            formData.append('recover_campaigns', '1');
            formData.append('campaign_ids', JSON.stringify(recoverableCampaigns));
            formData.append('csrf_token', CSRF_TOKEN);
            
            try {
                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                });
                
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    
                    const chunk = decoder.decode(value);
                    const lines = chunk.split('\n\n');
                    
                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const data = JSON.parse(line.substring(6));
                            
                            if (data.type === 'complete') {
                                recoverBtn.textContent = 'Recovery Complete';
                                setTimeout(() => {
                                    window.location.reload();
                                }, 3000);
                            } else if (data.message) {
                                const entry = document.createElement('div');
                                entry.className = 'log-entry log-' + (data.type || 'info');
                                entry.textContent = data.message;
                                progressConsole.appendChild(entry);
                                progressConsole.scrollTop = progressConsole.scrollHeight;
                            }
                        }
                    }
                }
            } catch (err) {
                const entry = document.createElement('div');
                entry.className = 'log-entry log-error';
                entry.textContent = 'Error: ' + err.message;
                progressConsole.appendChild(entry);
                recoverBtn.disabled = false;
                recoverBtn.textContent = 'Recover Missing MailerLite Campaigns';
            }
        });
    }
    
    /**
     * Initialize repair names button
     */
    const repairNamesBtn = document.getElementById('repair-names-btn');
    if (repairNamesBtn) {
        repairNamesBtn.addEventListener('click', async function() {
            if (!confirm('This will copy the title field to the name field for all campaigns missing names. Continue?')) {
                return;
            }
            
            repairNamesBtn.disabled = true;
            repairNamesBtn.textContent = 'Repairing...';
            
            const repairConsole = document.getElementById('repair-progress-console');
            const repairOutput = document.getElementById('repair-console-output');
            repairConsole.classList.add('active');
            repairOutput.innerHTML = '';
            
            try {
                const formData = new FormData();
                formData.append('repair_names', '1');
                formData.append('csrf_token', CSRF_TOKEN);
                
                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                });
                
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    
                    const chunk = decoder.decode(value);
                    const lines = chunk.split('\n\n');
                    
                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            let data;
                            try {
                                data = JSON.parse(line.substring(6));
                            } catch (parseErr) {
                                const entry = document.createElement('div');
                                entry.className = 'log-entry log-error';
                                entry.textContent = 'Malformed server response: ' + parseErr.message;
                                repairOutput.appendChild(entry);
                                repairOutput.scrollTop = repairOutput.scrollHeight;
                                continue;
                            }
                            
                            if (data.type === 'complete') {
                                const entry = document.createElement('div');
                                entry.className = 'log-entry log-success';
                                entry.textContent = `✓ Complete! Repaired ${data.repaired} campaigns.`;
                                repairOutput.appendChild(entry);
                                repairOutput.scrollTop = repairOutput.scrollHeight;
                                
                                setTimeout(() => {
                                    alert(`Successfully repaired ${data.repaired} campaign(s)! The page will reload.`);
                                    window.location.reload();
                                }, 1000);
                            } else if (data.message) {
                                const entry = document.createElement('div');
                                entry.className = 'log-entry log-' + (data.type || 'info');
                                entry.textContent = data.message;
                                repairOutput.appendChild(entry);
                                repairOutput.scrollTop = repairOutput.scrollHeight;
                            }
                        }
                    }
                }
            } catch (err) {
                const entry = document.createElement('div');
                entry.className = 'log-entry log-error';
                entry.textContent = 'Error: ' + err.message;
                repairOutput.appendChild(entry);
                repairNamesBtn.disabled = false;
                repairNamesBtn.textContent = 'Repair Missing Names';
            }
        });
    }
    
    /**
     * Initialize fix filenames button
     */
    const fixFilenamesBtn = document.getElementById('fix-filenames-btn');
    if (fixFilenamesBtn) {
        const missingCampaignIds = MISSING_CAMPAIGN_IDS;
        
        fixFilenamesBtn.addEventListener('click', async function() {
            if (!confirm(`This will re-match ${missingCampaignIds.length} campaigns to their HTML files. Continue?`)) {
                return;
            }
            
            fixFilenamesBtn.disabled = true;
            fixFilenamesBtn.textContent = 'Fixing...';
            
            const fixConsole = document.getElementById('fix-progress-console');
            const fixOutput = document.getElementById('fix-console-output');
            fixConsole.classList.add('active');
            fixOutput.innerHTML = '';
            
            try {
                const formData = new FormData();
                formData.append('fix_filenames', '1');
                formData.append('campaign_ids', JSON.stringify(missingCampaignIds));
                formData.append('csrf_token', CSRF_TOKEN);
                
                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                });
                
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    
                    const chunk = decoder.decode(value);
                    const lines = chunk.split('\n\n');
                    
                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            let data;
                            try {
                                data = JSON.parse(line.substring(6));
                            } catch (parseErr) {
                                const entry = document.createElement('div');
                                entry.className = 'log-entry log-error';
                                entry.textContent = 'Malformed server response: ' + parseErr.message;
                                fixOutput.appendChild(entry);
                                fixOutput.scrollTop = fixOutput.scrollHeight;
                                continue;
                            }
                            
                            if (data.type === 'complete') {
                                const entry = document.createElement('div');
                                entry.className = 'log-entry log-success';
                                entry.textContent = `✓ Complete! Fixed: ${data.fixed}, Failed: ${data.failed}`;
                                fixOutput.appendChild(entry);
                                fixOutput.scrollTop = fixOutput.scrollHeight;
                                
                                setTimeout(() => {
                                    alert(`Filename repair complete! Fixed ${data.fixed} campaigns. The page will reload.`);
                                    window.location.reload();
                                }, 1000);
                            } else if (data.message) {
                                const entry = document.createElement('div');
                                entry.className = 'log-entry log-' + (data.type || 'info');
                                entry.textContent = data.message;
                                fixOutput.appendChild(entry);
                                fixOutput.scrollTop = fixOutput.scrollHeight;
                            }
                        }
                    }
                }
            } catch (err) {
                const entry = document.createElement('div');
                entry.className = 'log-entry log-error';
                entry.textContent = 'Error: ' + err.message;
                fixOutput.appendChild(entry);
                fixFilenamesBtn.disabled = false;
                fixFilenamesBtn.textContent = 'Fix Filename Paths';
            }
        });
    }
});
