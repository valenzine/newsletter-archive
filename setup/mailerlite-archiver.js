// newsletter-archiver.js

document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    const resultContainer = document.createElement('div');
    resultContainer.id = 'ajax-status';
    form.parentNode.insertBefore(resultContainer, form.nextSibling);

    // Helper to centralize status display updates
    function updateStatus(content, isError = false) {
        const statusClass = isError ? 'status error' : 'status';
        resultContainer.innerHTML = `<div class="${statusClass}">${content}</div>`;
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        updateStatus('<p>Processing, please wait...</p>');

        const formData = new FormData(form);
        formData.append('archive_mailerlite', '1');

        // Use AbortController for modern timeout handling
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 300000); // 5-minute timeout for large updates

        try {
            const response = await fetch(window.location.pathname, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: controller.signal // Link the controller to the fetch request
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                const text = await response.text();
                let msg = `Server error: ${response.status} ${response.statusText}`;
                let details = '';
                try {
                    const json = JSON.parse(text);
                    if (json.message) {
                        details += `<div><b>Message:</b> ${escapeHtml(json.message)}</div>`;
                    }
                    if (json.errors) {
                        details += '<div><b>Validation errors:</b><ul>';
                        for (const key in json.errors) {
                            details += `<li>${escapeHtml(key)}: ${escapeHtml(json.errors[key].join(', '))}</li>`;
                        }
                        details += '</ul></div>';
                    }
                } catch (e) {
                    details = `<pre>${escapeHtml(text)}</pre>`;
                }
                updateStatus(msg + details, true);
                return;
            }

            const status = await response.json();
            let html = '<p class="success">Archiving complete.</p>';
            html += '<ul>';
            html += `<li>Saved: ${status.saved || 0}</li>`;
            html += `<li>Skipped: ${status.skipped || 0}</li>`;
            html += `<li>Updated JSON: ${status.updated || 0}</li>`;
            html += '</ul>';
            if (status.errors && status.errors.length) {
                html += '<div class="error"><strong>Errors:</strong><ul>';
                status.errors.forEach(function (err) {
                    html += `<li>${escapeHtml(err)}</li>`;
                });
                html += '</ul></div>';
            }
            if (status.debug && status.debug.length) {
                html += `<details><summary>Debug log</summary><pre>${escapeHtml(status.debug.join('\n'))}</pre></details>`;
            }
            updateStatus(html);

        } catch (err) {
            clearTimeout(timeoutId);
            if (err.name === 'AbortError') {
                updateStatus('No response from server after 5 minutes. The process may still be running. Check the terminal/logs for progress.', true);
            } else {
                updateStatus(`AJAX error: ${escapeHtml(err.toString())}`, true);
            }
        }
    });

    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
