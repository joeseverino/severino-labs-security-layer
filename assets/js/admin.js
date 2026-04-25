// Severino Labs Security Layer Admin JavaScript

function slSecurityToggleTable(toggleElement) {
    const tableContent = toggleElement.nextElementSibling;

    if (!tableContent) {
        return;
    }

    const isExpanded = tableContent.classList.toggle('expanded');
    toggleElement.classList.toggle('expanded');
    toggleElement.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
}

function slSecurityToggleEventDetails(row) {
    const detailRow = row.nextElementSibling;

    if (!detailRow || !detailRow.classList.contains('sl-security-event-details-row')) {
        return;
    }

    const isActive = row.classList.toggle('active');
    row.setAttribute('aria-expanded', isActive ? 'true' : 'false');
}

// Initialize expandable tables on page load
jQuery(document).ready(function($) {
    $('.sl-security-table-toggle').on('click', function() {
        slSecurityToggleTable(this);
    });

    $('.sl-security-event-summary').on('click keypress', function(event) {
        if (event.type === 'keypress' && event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        slSecurityToggleEventDetails(this);
    });
});