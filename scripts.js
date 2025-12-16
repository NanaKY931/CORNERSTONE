/**
 * CORNERSTONE INVENTORY TRACKER - JavaScript
 * Client-side interactivity and dynamic features
 */

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Debounce function to limit function calls
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

/**
 * Format number
 */
function formatNumber(number, decimals = 2) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(number);
}

// ============================================
// DASHBOARD - INVENTORY FILTERS
// ============================================

function initializeInventoryFilters() {
    const searchInput = document.getElementById('search-inventory');
    const siteFilter = document.getElementById('filter-site');
    const categoryFilter = document.getElementById('filter-category');
    const table = document.getElementById('inventory-table');
    
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Filter function
    const filterTable = debounce(() => {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const selectedSite = siteFilter ? siteFilter.value : '';
        const selectedCategory = categoryFilter ? categoryFilter.value : '';
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const siteId = row.dataset.siteId;
            const category = row.dataset.category;
            
            const matchesSearch = !searchTerm || text.includes(searchTerm);
            const matchesSite = !selectedSite || siteId === selectedSite;
            const matchesCategory = !selectedCategory || category === selectedCategory;
            
            if (matchesSearch && matchesSite && matchesCategory) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }, 300);
    
    // Attach event listeners
    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
    }
    
    if (siteFilter) {
        siteFilter.addEventListener('change', filterTable);
    }
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', filterTable);
    }
}

// ============================================
// ADMIN - TRANSACTION FORM
// ============================================

function initializeTransactionForm() {
    const form = document.getElementById('transaction-form');
    if (!form) return;
    
    const actionInput = document.getElementById('action');
    const submitBtn = document.getElementById('submit-btn');
    const destinationGroup = document.getElementById('destination-site-group');
    const destinationSelect = document.getElementById('destination_site_id');
    const transactionButtons = document.querySelectorAll('.btn-transaction');
    const materialSelect = document.getElementById('material_id');
    const quantityInput = document.getElementById('quantity');
    const siteSelect = document.getElementById('site_id');
    
    // Transaction type button handling
    transactionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const action = this.dataset.action;
            
            // Remove active class from all buttons
            transactionButtons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Set action value
            actionInput.value = action;
            
            // Show/hide destination site for transfers
            if (action === 'TRANSFER') {
                destinationGroup.style.display = 'block';
                destinationSelect.required = true;
            } else {
                destinationGroup.style.display = 'none';
                destinationSelect.required = false;
                destinationSelect.value = '';
            }
            
            // Enable submit button
            validateForm();
        });
    });
    
    // Form validation
    function validateForm() {
        const hasAction = actionInput.value !== '';
        const hasMaterial = materialSelect.value !== '';
        const hasQuantity = quantityInput.value !== '' && parseFloat(quantityInput.value) > 0;
        const hasDestination = actionInput.value !== 'TRANSFER' || destinationSelect.value !== '';
        
        const isValid = hasAction && hasMaterial && hasQuantity && hasDestination;
        submitBtn.disabled = !isValid;
    }
    
    // Attach validation listeners
    materialSelect.addEventListener('change', validateForm);
    quantityInput.addEventListener('input', validateForm);
    destinationSelect.addEventListener('change', validateForm);
    
    // Prevent selecting same site for transfer
    if (destinationSelect && siteSelect) {
        destinationSelect.addEventListener('change', function() {
            if (this.value === siteSelect.value) {
                alert('Destination site must be different from source site.');
                this.value = '';
                validateForm();
            }
        });
    }
    
    // Form submission confirmation for large quantities
    form.addEventListener('submit', function(e) {
        const quantity = parseFloat(quantityInput.value);
        const action = actionInput.value;
        
        if (action === 'OUT' && quantity > 100) {
            if (!confirm(`You are removing ${quantity} units. Are you sure?`)) {
                e.preventDefault();
                return false;
            }
        }
        
        if (action === 'TRANSFER' && quantity > 100) {
            const destSite = destinationSelect.options[destinationSelect.selectedIndex].text;
            if (!confirm(`You are transferring ${quantity} units to ${destSite}. Are you sure?`)) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Auto-submit site selection (already in HTML but adding JS fallback)
    if (siteSelect) {
        // Remove the onchange attribute and handle it here for better control
        siteSelect.removeAttribute('onchange');
        siteSelect.addEventListener('change', function() {
            // Create a form to submit
            const tempForm = document.createElement('form');
            tempForm.method = 'GET';
            tempForm.action = 'inventory_admin.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'site_id';
            input.value = this.value;
            
            tempForm.appendChild(input);
            document.body.appendChild(tempForm);
            tempForm.submit();
        });
    }
}

// ============================================
// REPORTS - CSV EXPORT
// ============================================

function exportTableToCSV(filename) {
    const table = document.getElementById('report-table');
    if (!table) {
        alert('No report table found to export.');
        return;
    }
    
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const csvRow = [];
        
        cols.forEach(col => {
            // Get text content and clean it
            let text = col.textContent.trim();
            // Escape quotes and wrap in quotes if contains comma
            text = text.replace(/"/g, '""');
            if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                text = `"${text}"`;
            }
            csvRow.push(text);
        });
        
        csv.push(csvRow.join(','));
    });
    
    // Create blob and download
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (navigator.msSaveBlob) {
        // IE 10+
        navigator.msSaveBlob(blob, filename);
    } else {
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// ============================================
// ALERTS - AUTO-REFRESH (Optional)
// ============================================

let alertRefreshInterval = null;

function startAlertAutoRefresh(intervalSeconds = 60) {
    // Only on dashboard page
    if (!document.querySelector('.alerts-container')) return;
    
    alertRefreshInterval = setInterval(() => {
        fetchAndUpdateAlerts();
    }, intervalSeconds * 1000);
}

function stopAlertAutoRefresh() {
    if (alertRefreshInterval) {
        clearInterval(alertRefreshInterval);
        alertRefreshInterval = null;
    }
}

async function fetchAndUpdateAlerts() {
    try {
        const response = await fetch('api/get_alerts.php');
        if (!response.ok) throw new Error('Failed to fetch alerts');
        
        const data = await response.json();
        updateAlertsDisplay(data.alerts);
    } catch (error) {
        console.error('Error fetching alerts:', error);
    }
}

function updateAlertsDisplay(alerts) {
    const container = document.querySelector('.alerts-container');
    if (!container) return;
    
    // Update alert count badge
    const badge = document.querySelector('.badge-warning');
    if (badge) {
        badge.textContent = `${alerts.length} alerts`;
    }
    
    // Update stat card
    const statValue = document.querySelector('.stat-icon-orange').parentElement.querySelector('.stat-value');
    if (statValue) {
        statValue.textContent = alerts.length;
    }
    
    // Optionally rebuild alerts HTML (more complex, skipping for now)
    // This would require rebuilding the entire alerts container
}

// ============================================
// SMOOTH SCROLL
// ============================================

function initializeSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#') return;
            
            e.preventDefault();
            const target = document.querySelector(href);
            
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// ============================================
// FORM ENHANCEMENTS
// ============================================

function initializeFormEnhancements() {
    // Auto-focus first input in forms
    const firstInput = document.querySelector('form input:not([type="hidden"]):not([disabled])');
    if (firstInput && !firstInput.hasAttribute('autofocus')) {
        // Only if not already set
        // firstInput.focus(); // Commented out as it can be annoying
    }
    
    // Add loading state to forms on submit
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Processing...';
                
                // Re-enable after 5 seconds as fallback
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }, 5000);
            }
        });
    });
}

// ============================================
// TABLE SORTING (Optional Enhancement)
// ============================================

function initializeTableSorting() {
    const tables = document.querySelectorAll('.data-table');
    
    tables.forEach(table => {
        const headers = table.querySelectorAll('th');
        
        headers.forEach((header, index) => {
            // Skip if header has no text or is an action column
            if (!header.textContent.trim() || header.classList.contains('no-sort')) {
                return;
            }
            
            header.style.cursor = 'pointer';
            header.title = 'Click to sort';
            
            header.addEventListener('click', function() {
                sortTable(table, index);
            });
        });
    });
}

function sortTable(table, columnIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const isNumeric = rows.every(row => {
        const cell = row.cells[columnIndex];
        if (!cell) return false;
        const text = cell.textContent.trim().replace(/[$,]/g, '');
        return !isNaN(text) && text !== '';
    });
    
    // Determine sort direction
    const currentDirection = table.dataset.sortDirection || 'asc';
    const newDirection = currentDirection === 'asc' ? 'desc' : 'asc';
    table.dataset.sortDirection = newDirection;
    
    rows.sort((a, b) => {
        const aCell = a.cells[columnIndex];
        const bCell = b.cells[columnIndex];
        
        if (!aCell || !bCell) return 0;
        
        let aValue = aCell.textContent.trim();
        let bValue = bCell.textContent.trim();
        
        if (isNumeric) {
            aValue = parseFloat(aValue.replace(/[$,]/g, '')) || 0;
            bValue = parseFloat(bValue.replace(/[$,]/g, '')) || 0;
            return newDirection === 'asc' ? aValue - bValue : bValue - aValue;
        } else {
            return newDirection === 'asc' 
                ? aValue.localeCompare(bValue)
                : bValue.localeCompare(aValue);
        }
    });
    
    // Re-append rows in sorted order
    rows.forEach(row => tbody.appendChild(row));
}

// ============================================
// NOTIFICATIONS/TOASTS (Optional)
// ============================================

function showToast(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
    `;
    
    // Add color based on type
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };
    toast.style.borderLeft = `4px solid ${colors[type] || colors.info}`;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, duration);
}

// Add animation styles
if (!document.getElementById('toast-styles')) {
    const style = document.createElement('style');
    style.id = 'toast-styles';
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// ============================================
// INITIALIZATION
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Initialize common features
    initializeSmoothScroll();
    initializeFormEnhancements();
    
    // Page-specific initializations are called from the page itself
    // e.g., initializeInventoryFilters() is called from dashboard.php
    // e.g., initializeTransactionForm() is called from inventory_admin.php
    
    // Optional: Start alert auto-refresh on dashboard
    // if (window.location.pathname.includes('dashboard.php')) {
    //     startAlertAutoRefresh(60); // Refresh every 60 seconds
    // }
    
    // Optional: Initialize table sorting
    // initializeTableSorting();
});

// ============================================
// CLEANUP ON PAGE UNLOAD
// ============================================

window.addEventListener('beforeunload', function() {
    stopAlertAutoRefresh();
});

// ============================================
// EXPOSE FUNCTIONS TO GLOBAL SCOPE
// ============================================

window.initializeInventoryFilters = initializeInventoryFilters;
window.initializeTransactionForm = initializeTransactionForm;
window.exportTableToCSV = exportTableToCSV;
window.showToast = showToast;
