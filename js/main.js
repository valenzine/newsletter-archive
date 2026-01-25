/**
 * Newsletter Archive - Main JavaScript
 * Public email archive with inbox-style layout
 */

const ArchiveApp = {
    // Configuration
    config: {
        apiBase: '/api',
        isMobile: window.archiveCfg?.isMobile || false,
        siteName: window.archiveCfg?.siteName || 'Newsletter Archive',
        sidebarCollapsed: false
    },
    
    // Translations (loaded from PHP)
    i18n: window.archiveCfg?.i18n || {},
    
    // Get current viewport size category
    getViewportSize() {
        const width = window.innerWidth;
        if (width <= 768) return 'mobile';
        if (width <= 1024) return 'tablet';
        return 'desktop';
    },
    
    // Check if current viewport is mobile (dynamic)
    isMobileView() {
        return this.getViewportSize() === 'mobile';
    },
    
    // Check if sidebar should be collapsible (tablet/desktop)
    isCollapsibleView() {
        const size = this.getViewportSize();
        return size === 'tablet' || size === 'desktop';
    },
    
    // Get translated string
    t(key, params = {}) {
        const keys = key.split('.');
        let value = this.i18n;
        for (const k of keys) {
            if (value && typeof value === 'object' && k in value) {
                value = value[k];
            } else {
                return key; // Return key if not found
            }
        }
        if (typeof value !== 'string') return key;
        
        // Replace {param} placeholders
        for (const [k, v] of Object.entries(params)) {
            value = value.replace(`{${k}}`, v);
        }
        return value;
    },
    
    // State
    state: {
        campaigns: [],
        sortOrder: 'desc',
        currentCampaignId: null,
        currentCampaignIndex: -1,
        loading: false
    },

    // Initialize
    async init() {
        // Disable browser scroll restoration
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }
        
        // Setup sidebar toggle (for tablet/desktop)
        this.setupSidebarToggle();
        
        // Setup mobile navigation
        this.setupMobileNav();
        
        // Setup sort toggle
        this.setupSortToggle();
        
        // Load campaigns
        await this.loadCampaigns();
        
        // loadCampaigns() already calls renderEmailList(), so no need to call it again
        
        // Handle direct campaign link
        const directCampaignId = window.archiveCfg?.directCampaignId;
        if (directCampaignId) {
            setTimeout(() => {
                this.loadCampaign(directCampaignId);
            }, 100);
        }
        
        
        // Handle browser back/forward
        window.addEventListener('popstate', (e) => {
            if (e.state && e.state.campaignId) {
                this.loadCampaign(e.state.campaignId, false);
            } else {
                this.closeCampaign();
            }
        });
    },

    // Setup mobile navigation
    setupMobileNav() {
        const backBtn = document.getElementById('back-to-list');
        const prevBtn = document.getElementById('prev-campaign');
        const nextBtn = document.getElementById('next-campaign');
        
        if (backBtn) {
            backBtn.addEventListener('click', () => this.closeCampaign());
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', () => this.navigateCampaign(-1));
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this.navigateCampaign(1));
        }
    },

    // Setup sort toggle
    setupSortToggle() {
        const sortBtn = document.getElementById('sort-toggle');
        if (sortBtn) {
            sortBtn.addEventListener('click', () => {
                this.state.sortOrder = this.state.sortOrder === 'desc' ? 'asc' : 'desc';
                sortBtn.textContent = this.state.sortOrder === 'desc' 
                    ? this.t('sort.newest_first')
                    : this.t('sort.oldest_first');
                this.loadCampaigns();
            });
        }
    },

    // Load campaigns from API
    async loadCampaigns() {
        this.state.loading = true;
        const list = document.getElementById('email-list');
        
        if (list) {
            list.innerHTML = `<li class="loading-state">${this.t('list.loading')}</li>`;
        }
        
        try {
            const url = `${this.config.apiBase}/campaigns.php?limit=1000&sort=${this.state.sortOrder}`;
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                this.state.campaigns = data.data;
                this.renderEmailList();
            } else {
                console.error('[ArchiveApp] API returned success=false');
            }
        } catch (error) {
            console.error('[ArchiveApp] Failed to load campaigns:', error);
            if (list) {
                list.innerHTML = `<li class="error-state">${this.t('error.api_error')}</li>`;
            }
        } finally {
            this.state.loading = false;
        }
    },

    // Render email list
    renderEmailList() {
        const list = document.getElementById('email-list');
        if (!list) {
            console.error('[ArchiveApp] email-list element not found!');
            return;
        }
        
        if (this.state.campaigns.length === 0) {
            list.innerHTML = `<li class="empty-state">${this.t('list.no_campaigns')}</li>`;
            return;
        }
        
        list.innerHTML = this.state.campaigns.map((campaign, index) => `
            <li class="email show" data-index="${index}">
                <a href="javascript:void(0)" 
                   data-campaign-id="${campaign.id}" 
                   class="${campaign.id === this.state.currentCampaignId ? 'active' : ''}">
                    <span class="subject">${this.escapeHtml(campaign.subject)}</span>
                    <span class="date">${campaign.date.formatted}</span>
                </a>
            </li>
        `).join('');
        
        // Add click handlers to campaign links
        const links = list.querySelectorAll('a[data-campaign-id]');
        links.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                const campaignId = link.dataset.campaignId;
                this.loadCampaign(campaignId);
                return false;
            }, true); // Use capture phase
        });
    },

    // Load and display a campaign
    async loadCampaign(campaignId, updateHistory = true) {
        
        // Prevent loading if already loading or already showing this campaign
        if (this.state.loading) {
            return;
        }
        
        if (this.state.currentCampaignId === campaignId && !updateHistory) {
            return;
        }
        
        this.state.loading = true;
        
        const contentPanel = document.getElementById('campaign-content');
        if (!contentPanel) {
            this.state.loading = false;
            return;
        }
        
        
        // Update state
        this.state.currentCampaignId = campaignId;
        this.state.currentCampaignIndex = this.state.campaigns.findIndex(c => c.id === campaignId);
        
        // Update active state in list - only change what's needed
        const previousActive = document.querySelector('#email-list a.active');
        const newActive = document.querySelector(`#email-list a[data-campaign-id="${campaignId}"]`);
        
        if (previousActive && previousActive !== newActive) {
            previousActive.classList.remove('active');
        }
        if (newActive) {
            newActive.classList.add('active');
        }
        
        // Show loading with spinner overlay
        this.showLoadingIndicator(contentPanel, this.t('campaign.loading'));
        
        // Update URL
        if (updateHistory) {
            history.pushState({ campaignId }, '', `/?id=${campaignId}`);
        }
        
        // Mobile: show campaign view
        if (this.isMobileView()) {
            this.showMobileCampaignView();
        } else {
        }
        
        try {
            const response = await fetch(`/view_campaign.php?id=${campaignId}&ajax=1`);
            const content = await response.text();
            
            this.removeLoadingIndicator(contentPanel);
            contentPanel.innerHTML = content;
            
            // Scroll to top of content
            contentPanel.scrollTop = 0;
        } catch (error) {
            console.error('Failed to load campaign:', error);
            this.removeLoadingIndicator(contentPanel);
            contentPanel.innerHTML = `<div class="error">${this.t('campaign.load_error')}</div>`;
        } finally {
            this.state.loading = false;
        }
        
        this.updateNavButtons();
    },

    // Close campaign (mobile)
    closeCampaign() {
        this.state.currentCampaignId = null;
        this.state.currentCampaignIndex = -1;
        
        // Remove active state
        document.querySelectorAll('.email-link').forEach(link => {
            link.classList.remove('active');
        });
        
        // Show placeholder
        const contentPanel = document.getElementById('campaign-content');
        if (contentPanel) {
            contentPanel.innerHTML = `
                <div id="content-placeholder" class="content-placeholder desktop-only">
                    <p>${this.t('list.select_campaign')}</p>
                </div>
            `;
        }
        
        // Mobile: show list view
        if (this.isMobileView()) {
            this.showMobileListView();
        }
        
        // Update URL
        history.pushState({}, '', '/');
    },
    
    // Show mobile campaign view (hide list, show nav bar)
    showMobileCampaignView() {
        const navBar = document.getElementById('mobile-campaign-nav');
        const listPanel = document.getElementById('email-list-panel');
        const contentPanel = document.getElementById('content-panel');
        
        if (navBar) {
            navBar.classList.remove('hidden');
            navBar.style.display = 'flex'; // Force display
        }
        if (listPanel) {
            listPanel.classList.add('viewing-campaign');
        }
        if (contentPanel) {
            contentPanel.classList.add('viewing-campaign');
        }
    },
    
    // Show mobile list view (show list, hide nav)
    showMobileListView() {
        const navBar = document.getElementById('mobile-campaign-nav');
        const listPanel = document.getElementById('email-list-panel');
        const contentPanel = document.getElementById('content-panel');
        
        if (navBar) navBar.classList.add('hidden');
        if (listPanel) listPanel.classList.remove('viewing-campaign');
        if (contentPanel) contentPanel.classList.remove('viewing-campaign');
    },

    // Navigate to prev/next campaign
    navigateCampaign(direction) {
        const newIndex = this.state.currentCampaignIndex + direction;
        if (newIndex >= 0 && newIndex < this.state.campaigns.length) {
            this.loadCampaign(this.state.campaigns[newIndex].id);
        }
    },

    // Update mobile nav buttons
    updateNavButtons() {
        const prevBtn = document.getElementById('prev-campaign');
        const nextBtn = document.getElementById('next-campaign');
        
        if (prevBtn) {
            prevBtn.disabled = this.state.currentCampaignIndex <= 0;
        }
        if (nextBtn) {
            nextBtn.disabled = this.state.currentCampaignIndex >= this.state.campaigns.length - 1;
        }
    },

    // Setup sidebar toggle for tablet/desktop
    setupSidebarToggle() {
        // Check if toggle button already exists
        let toggleBtn = document.getElementById('sidebar-toggle');
        
        if (!toggleBtn) {
            // Create toggle button
            toggleBtn = document.createElement('button');
            toggleBtn.id = 'sidebar-toggle';
            toggleBtn.className = 'sidebar-toggle';
            toggleBtn.innerHTML = '\u2715'; // Start with X (close icon)
            toggleBtn.setAttribute('aria-label', 'Hide sidebar');
            toggleBtn.style.display = 'none'; // Hidden by default
            
            // Insert as child of inbox-layout (absolute positioning context)
            const inboxLayout = document.querySelector('.inbox-layout');
            if (inboxLayout) {
                inboxLayout.appendChild(toggleBtn);
            }
            
            // Add click handler
            toggleBtn.addEventListener('click', () => {
                this.toggleSidebar();
            });
        }
        
        // Update visibility based on viewport
        this.updateSidebarToggleVisibility();
        
        // Restore sidebar state from localStorage (after button is created)
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === 'true' && this.isCollapsibleView()) {
            this.config.sidebarCollapsed = true;
            this.applySidebarState();
        }
        
        // Add resize handler
        let previousViewportSize = this.getViewportSize();
        window.addEventListener('resize', () => {
            const currentViewportSize = this.getViewportSize();
            
            this.updateSidebarToggleVisibility();
            
            // Reset sidebar state on mobile
            if (this.isMobileView()) {
                this.config.sidebarCollapsed = false;
                this.applySidebarState();
            }
            
            // If switching viewport sizes, reset to default (expanded) state
            if (previousViewportSize !== currentViewportSize && currentViewportSize !== 'mobile') {
                this.config.sidebarCollapsed = false;
                this.applySidebarState();
                localStorage.setItem('sidebarCollapsed', 'false');
            }
            
            previousViewportSize = currentViewportSize;
        });
    },
    
    // Update sidebar toggle button visibility
    updateSidebarToggleVisibility() {
        const toggleBtn = document.getElementById('sidebar-toggle');
        if (!toggleBtn) return;
        
        // Show toggle on tablet/desktop, hide on mobile
        if (this.isCollapsibleView()) {
            toggleBtn.style.display = 'flex';
        } else {
            toggleBtn.style.display = 'none';
        }
    },
    
    // Toggle sidebar collapsed state
    toggleSidebar() {
        this.config.sidebarCollapsed = !this.config.sidebarCollapsed;
        this.applySidebarState();
        
        // Save preference
        localStorage.setItem('sidebarCollapsed', this.config.sidebarCollapsed);
    },
    
    // Apply sidebar collapsed state
    applySidebarState() {
        const listPanel = document.getElementById('email-list-panel');
        const inboxLayout = document.querySelector('.inbox-layout');
        const toggleBtn = document.getElementById('sidebar-toggle');
        
        // Only require listPanel to exist (toggleBtn might not exist yet)
        if (!listPanel) return;
        
        if (this.config.sidebarCollapsed) {
            listPanel.classList.add('collapsed');
            if (inboxLayout) inboxLayout.classList.add('sidebar-collapsed');
            if (toggleBtn) {
                toggleBtn.innerHTML = '☰';
                toggleBtn.setAttribute('aria-label', 'Show sidebar');
            }
        } else {
            listPanel.classList.remove('collapsed');
            if (inboxLayout) inboxLayout.classList.remove('sidebar-collapsed');
            if (toggleBtn) {
                toggleBtn.innerHTML = '✕';
                toggleBtn.setAttribute('aria-label', 'Hide sidebar');
            }
        }
    },

    // Show loading indicator with spinner overlay
    showLoadingIndicator(container, message = 'Loading...') {
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `
            <div class="loading-message">
                <div class="loading loading-large"></div>
                <div>${message}</div>
            </div>
        `;
        container.style.position = 'relative';
        container.appendChild(overlay);
    },

    // Remove loading indicator
    removeLoadingIndicator(container) {
        const overlay = container.querySelector('.loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    },

    // Utility: escape HTML
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

// ============================================================================
// Welcome Page Handler
// ============================================================================

/**
 * Set a cookie
 */
function setCookie(name, value, days) {
    const expires = new Date();
    expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
    document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
}

/**
 * Handle welcome page dismissal
 */
function dismissWelcomePage() {
    const overlay = document.getElementById('welcomeOverlay');
    if (overlay) {
        // Set cookie for 7 days
        setCookie('archive_welcome_seen', '1', 7);
        
        // Fade out and remove
        overlay.style.opacity = '0';
        overlay.style.transition = 'opacity 0.3s ease';
        
        setTimeout(() => {
            overlay.remove();
        }, 300);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Setup welcome page if present
    const enterArchiveBtn = document.getElementById('enterArchiveBtn');
    if (enterArchiveBtn) {
        enterArchiveBtn.addEventListener('click', dismissWelcomePage);
    }
    
    // Initialize main app
    ArchiveApp.init();
});
