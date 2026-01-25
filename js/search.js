/**
 * Search Page JavaScript
 * Newsletter Archive - Full-text search functionality
 */

// Tag mapping: single letters to full names (only for abbreviated tags)
const TAG_MAPPINGS = {
    'E': 'EXTRAS'
};

// Map tag to CSS class name (lowercase)
function getTagClass(tag) {
    const mappedTag = TAG_MAPPINGS[tag] || tag;
    return 'tag-' + mappedTag.toLowerCase();
}

// Get display name for tag
function getTagDisplayName(tag) {
    return TAG_MAPPINGS[tag] || tag;
}

// Generate dynamic title from date when title is missing
function generateTitle(campaign) {
    // For EXTRAS campaigns, use "EXTRAS #number" format
    if (campaign.tags && campaign.tags.includes('E')) {
        return `EXTRAS #${campaign.number}`;
    }
    
    // For other campaigns, generate title from date
    const dateString = campaign.date;
    
    // Parse date in format DD/MM/YYYY
    const parts = dateString.split('/');
    if (parts.length !== 3) return 'Sin título';
    
    const day = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10);
    
    // Spanish day names
    const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    
    // Create date object (months are 0-indexed in JavaScript)
    const date = new Date(parseInt(parts[2], 10), month - 1, day);
    const dayName = dayNames[date.getDay()];
    
    // Format as "Martes 31/08"
    return `${dayName} ${parts[0]}/${parts[1]}`;
}

// Search state
let currentPage = 1;
let currentQuery = '';
let currentFilters = {};
let totalResults = 0;
let perPage = 20;
let lastSearchedQuery = ''; // Track the last actually-searched query
let lastSearchedFilters = ''; // Track the last actually-searched filters

// DOM elements
const searchForm = document.getElementById('searchForm');
const searchInput = document.getElementById('searchInput');
const searchClear = document.getElementById('searchClear');
const searchButton = document.getElementById('searchButton');
const searchStatus = document.getElementById('searchStatus');
const searchResults = document.getElementById('searchResults');
const pagination = document.getElementById('pagination');
const dateFrom = document.getElementById('dateFrom');
const dateTo = document.getElementById('dateTo');
const sortOrder = document.getElementById('sortOrder');
const perPageSelect = document.getElementById('perPageSelect');
const clearFiltersBtn = document.getElementById('clearFilters');
const filtersToggle = document.getElementById('filtersToggle');
const filtersContent = document.getElementById('filtersContent');

// Initialize filters as collapsed on mobile
function initializeFilters() {
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
        filtersContent.classList.add('collapsed');
        filtersToggle.classList.add('collapsed');
    }
}

// Toggle filters visibility
filtersToggle.addEventListener('click', () => {
    const isCollapsed = filtersContent.classList.toggle('collapsed');
    filtersToggle.classList.toggle('collapsed', isCollapsed);
    
    // Save preference
    localStorage.setItem('filtersCollapsed', isCollapsed);
});

// Restore filter state from localStorage if on mobile
const savedFilterState = localStorage.getItem('filtersCollapsed');
if (savedFilterState !== null && window.innerWidth <= 768) {
    const isCollapsed = savedFilterState === 'true';
    filtersContent.classList.toggle('collapsed', isCollapsed);
    filtersToggle.classList.toggle('collapsed', isCollapsed);
} else {
    initializeFilters();
}

// Handle window resize
let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        const isMobile = window.innerWidth <= 768;
        if (!isMobile) {
            // Always show filters on desktop
            filtersContent.classList.remove('collapsed');
            filtersToggle.classList.remove('collapsed');
        }
    }, 250);
});

// Get URL parameters on load
const urlParams = new URLSearchParams(window.location.search);
const initialQuery = urlParams.get('q') || '';
const initialPerPage = urlParams.get('per_page');

// Restore per-page setting from URL if present
if (initialPerPage) {
    perPage = parseInt(initialPerPage, 10);
    perPageSelect.value = initialPerPage;
}

// Auto-search if query is present (from URL param or pre-filled by PHP for clean URLs)
const prefilledQuery = searchInput.value.trim();
const queryToSearch = initialQuery || prefilledQuery;

if (queryToSearch) {
    // Ensure input and clear button are set up
    if (!initialQuery && prefilledQuery) {
        // Clean URL case: input already has value, just show clear button
        searchClear.classList.remove('hidden');
    } else {
        // Legacy URL case: set input value
        searchInput.value = initialQuery;
        searchClear.classList.remove('hidden');
    }
    
    // Perform search and then scroll to clicked result if needed
    (async () => {
        try {
            await performSearch();
            
            // If we have a clicked result index, scroll to it
            const context = sessionStorage.getItem('searchContext');
            if (context) {
                const data = JSON.parse(context);
                if (data.clickedResultIndex !== undefined) {
                    // Wait for DOM to be painted before trying to scroll
                    // Use requestAnimationFrame to ensure rendering is complete
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            const resultElement = document.getElementById(`result-${data.clickedResultIndex}`);
                            
                            if (resultElement) {
                                resultElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                // Highlight briefly
                                resultElement.classList.add('highlight-flash');
                                setTimeout(() => {
                                    resultElement.classList.remove('highlight-flash');
                                }, 2000);
                            }
                        });
                    });
                }
            }
        } catch (e) {
            // Silently handle scroll errors
        }
    })();
}

// Show/hide clear button based on input
searchInput.addEventListener('input', () => {
    if (searchInput.value.trim()) {
        searchClear.classList.remove('hidden');
    } else {
        searchClear.classList.add('hidden');
    }
});

// Clear button click
searchClear.addEventListener('click', () => {
    searchInput.value = '';
    searchClear.classList.add('hidden');
    searchInput.focus();
    
    // Clear results and reset
    currentQuery = '';
    currentPage = 1;
    lastSearchedQuery = ''; // Reset last searched query
    lastSearchedFilters = ''; // Reset last searched filters
    searchStatus.innerHTML = window.searchCfg.i18n.search.enter_query || 'Enter a search term to begin';
    searchResults.innerHTML = '';
    pagination.classList.add('hidden');
    pagination.innerHTML = ''; // Clear pagination content
    const siteName = window.searchCfg?.siteName || 'Newsletter Archive';
    document.title = `Search Archive | ${siteName}`;
    
    // Clear URL - go back to base search path
    const basePath = window.location.pathname.includes('buscar') ? '/buscar' : '/search';
    window.history.pushState({}, '', basePath);
    
    // Clear stored search context
    sessionStorage.removeItem('searchContext');
});

// Search form submission
searchForm.addEventListener('submit', (e) => {
    e.preventDefault();
    // Reset pagination and context on new search
    currentPage = 1;
    lastSearchedQuery = ''; // Force a context mismatch to trigger page reset
    lastSearchedFilters = ''; // Force a context mismatch to trigger page reset
    performSearch();
});

// Filter changes
[dateFrom, dateTo, sortOrder, perPageSelect].forEach(el => {
    el.addEventListener('change', () => {
        if (currentQuery) {
            currentPage = 1;
            lastSearchedFilters = ''; // Force a context mismatch when filters change
            // Update perPage if the selector changed
            if (el === perPageSelect) {
                perPage = parseInt(perPageSelect.value, 10);
            }
            performSearch();
        }
    });
});

// Clear filters
clearFiltersBtn.addEventListener('click', () => {
    dateFrom.value = '';
    dateTo.value = '';
    sortOrder.value = 'relevance';
    
    if (currentQuery) {
        currentPage = 1;
        lastSearchedFilters = ''; // Force a context mismatch when clearing filters
        performSearch();
    }
});

// Perform search
async function performSearch() {
    currentQuery = searchInput.value.trim();
    
    if (!currentQuery) {
        searchStatus.innerHTML = window.searchCfg.i18n.search.enter_query || 'Enter a search term to begin';
        searchResults.innerHTML = '';
        pagination.classList.add('hidden');
        pagination.innerHTML = ''; // Clear pagination content
        // Restore default title
        const siteName = window.searchCfg?.siteName || 'Newsletter Archive';
        document.title = `Search Archive | ${siteName}`;
        return;
    }
    
    // Read current filter values (including perPage which may have been changed)
    perPage = parseInt(perPageSelect.value, 10);
    
    // Build current filter values
    const currentFiltersObj = {};
    if (dateFrom.value) {
        currentFiltersObj.from = dateFrom.value;
    }
    if (dateTo.value) {
        currentFiltersObj.to = dateTo.value;
    }
    if (sortOrder.value && sortOrder.value !== 'relevance') {
        currentFiltersObj.sort = sortOrder.value;
    }
    
    // Create a hash of current query and filters
    const currentQueryStr = currentQuery;
    const currentFiltersStr = JSON.stringify(currentFiltersObj);
    
    // Check if the search query or filters have changed since last search
    // If they have, reset to page 1
    if (currentQueryStr !== lastSearchedQuery || currentFiltersStr !== lastSearchedFilters) {
        currentPage = 1;
    }
    
    // Update what we just searched for (for next comparison)
    lastSearchedQuery = currentQueryStr;
    lastSearchedFilters = currentFiltersStr;
    
    // Build query params for API call
    const apiParams = new URLSearchParams({
        q: currentQuery,
        page: currentPage,
        per_page: perPage
    });
    
    currentFilters = currentFiltersObj;
    
    if (dateFrom.value) {
        apiParams.append('from', dateFrom.value);
    }
    
    if (dateTo.value) {
        apiParams.append('to', dateTo.value);
    }
    
    if (sortOrder.value && sortOrder.value !== 'relevance') {
        apiParams.append('sort', sortOrder.value);
    }
    
    // Build URL params (exclude 'q' since it's in the path for clean URLs)
    const urlParams = new URLSearchParams();
    if (currentPage > 1) urlParams.set('page', currentPage);
    if (perPage !== 20) urlParams.set('per_page', perPage);
    if (dateFrom.value) urlParams.set('from', dateFrom.value);
    if (dateTo.value) urlParams.set('to', dateTo.value);
    if (sortOrder.value && sortOrder.value !== 'relevance') urlParams.set('sort', sortOrder.value);
    
    // Update URL with clean format
    let newUrl;
    if (currentQuery) {
        // Use clean URL format: /search/{query} or /buscar/{query}
        const basePath = window.location.pathname.includes('buscar') ? '/buscar' : '/search';
        newUrl = `${basePath}/${encodeURIComponent(currentQuery)}`;
        
        // Add pagination/filter params if present (not 'q' - it's in the path)
        const urlParamsStr = urlParams.toString();
        if (urlParamsStr) {
            newUrl += `?${urlParamsStr}`;
        }
    } else {
        // No query - just the base search path
        const basePath = window.location.pathname.includes('buscar') ? '/buscar' : '/search';
        newUrl = basePath;
        const urlParamsStr = urlParams.toString();
        if (urlParamsStr) {
            newUrl += `?${urlParamsStr}`;
        }
    }
    
    window.history.pushState({}, '', newUrl);
    
    // Store search context for back navigation from campaigns
    // Preserve clickedResultIndex if it exists
    const existingContext = JSON.parse(sessionStorage.getItem('searchContext') || '{}');
    const searchContext = {
        query: currentQuery,
        page: currentPage,
        filters: currentFilters,
        url: newUrl,
        timestamp: Date.now(),
        // Preserve clickedResultIndex from previous context if it exists
        ...(existingContext.clickedResultIndex !== undefined && { clickedResultIndex: existingContext.clickedResultIndex })
    };
    sessionStorage.setItem('searchContext', JSON.stringify(searchContext));
    
    // Show loading
    searchStatus.innerHTML = 'Buscando...';
    searchResults.innerHTML = '';
    pagination.classList.add('hidden');
    searchButton.disabled = true;
    
    try {
        const response = await fetch(`/api/search.php?${apiParams.toString()}`);
        const data = await response.json();
        
        if (data.success) {
            // Update page title with search query for analytics
            const siteName = window.searchCfg?.siteName || 'Newsletter Archive';
            document.title = `Search: ${currentQuery} | ${siteName}`;
            displayResults(data);
        } else {
            showError(data.message || data.error);
        }
    } catch (error) {
        console.error('Search error:', error);
        showError('An error occurred while searching. Please try again.');
        // Error already displayed to user
    } finally {
        searchButton.disabled = false;
    }
}

// Display search results
function displayResults(data) {
    totalResults = data.total;
    
    if (totalResults === 0) {
        searchStatus.innerHTML = `No se encontraron resultados para "<strong>${escapeHtml(currentQuery)}</strong>"`;
        searchResults.innerHTML = '';
        pagination.classList.add('hidden');
        pagination.innerHTML = ''; // Clear old pagination to prevent stale display
        return;
    }
    
    const startResult = ((currentPage - 1) * perPage) + 1;
    const endResult = Math.min(currentPage * perPage, totalResults);
    
    searchStatus.innerHTML = `
        Mostrando ${startResult}-${endResult} de ${totalResults} resultado${totalResults !== 1 ? 's' : ''} 
        para "<strong>${escapeHtml(currentQuery)}</strong>"
    `;
    
    // Render results - calculate global index for each result
    const startIndex = (currentPage - 1) * perPage;
    const resultsHtml = data.results.map((result, localIndex) => {
        const globalIndex = startIndex + localIndex;
        return renderResult(result, globalIndex);
    }).join('');
    searchResults.innerHTML = `<ul class="search-results">${resultsHtml}</ul>`;
    
    // Add click handlers to track which result was clicked
    document.querySelectorAll('[data-result-index]').forEach(link => {
        link.addEventListener('click', (e) => {
            const index = link.dataset.resultIndex;
            // Update search context with clicked result index
            const context = JSON.parse(sessionStorage.getItem('searchContext') || '{}');
            context.clickedResultIndex = parseInt(index, 10);
            sessionStorage.setItem('searchContext', JSON.stringify(context));
        });
    });
    
    // Render pagination
    renderPagination(data);
}

// Render single result
function renderResult(result, index) {
    // Format date
    const date = new Date(result.sent_at);
    const formattedDate = date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    
    return `
        <li class="search-result" id="result-${index}">
            <div class="result-header">
                <h2 class="result-title">
                    <a href="${escapeHtml(result.url)}" data-result-index="${index}">${escapeHtml(result.subject)}</a>
                </h2>
                <div class="result-meta">
                    <span>📅 ${formattedDate}</span>
                    ${result.source ? `<span>📧 ${escapeHtml(result.source)}</span>` : ''}
                </div>
            </div>
            ${result.excerpt ? `<div class="result-excerpt">${result.excerpt}</div>` : ''}
        </li>
    `;
}

// Render pagination
function renderPagination(data) {
    const totalPages = Math.ceil(totalResults / perPage);
    
    if (totalPages <= 1) {
        pagination.classList.add('hidden');
        pagination.innerHTML = ''; // Clear old pagination HTML
        return;
    }
    
    pagination.classList.remove('hidden');
    
    let paginationHtml = '';
    
    // Previous button
    paginationHtml += `
        <button data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>
            ← Anterior
        </button>
    `;
    
    // Page numbers (show max 5 pages)
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        paginationHtml += `<button data-page="1">1</button>`;
        if (startPage > 2) {
            paginationHtml += `<button disabled>...</button>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        paginationHtml += `
            <button 
                data-page="${i}" 
                class="${i === currentPage ? 'current-page' : ''}"
                ${i === currentPage ? 'disabled' : ''}
            >
                ${i}
            </button>
        `;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            paginationHtml += `<button disabled>...</button>`;
        }
        paginationHtml += `<button data-page="${totalPages}">${totalPages}</button>`;
    }
    
    // Next button
    paginationHtml += `
        <button data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}>
            Siguiente →
        </button>
    `;
    
    pagination.innerHTML = paginationHtml;
    
    // Attach event listeners to pagination buttons
    pagination.querySelectorAll('button[data-page]').forEach(button => {
        button.addEventListener('click', () => {
            const page = parseInt(button.dataset.page, 10);
            if (!isNaN(page)) {
                goToPage(page);
            }
        });
    });
}

// Go to page
function goToPage(page) {
    currentPage = page;
    performSearch();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Show error
function showError(message) {
    searchStatus.innerHTML = '';
    searchResults.innerHTML = `
        <div class="error-message">
            ${escapeHtml(message)}
        </div>
    `;
    pagination.classList.add('hidden');
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
