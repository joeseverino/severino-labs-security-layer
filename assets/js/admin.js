// Severino Labs Security Layer Admin JavaScript
//
// Note: expandable tables (Score breakdown, Monitored Target Groups,
// Excluded Paths) use the native <details>/<summary> element and need
// no JS — the browser handles open/close.

function slSecurityToggleEventDetails(row) {
    const detailRow = row.nextElementSibling;

    if (!detailRow || !detailRow.classList.contains('sl-security-event-details-row')) {
        return;
    }

    const isActive = row.classList.toggle('active');
    row.setAttribute('aria-expanded', isActive ? 'true' : 'false');
}

jQuery(document).ready(function ($) {
    $('.sl-security-event-summary').on('click keypress', function (event) {
        if (event.type === 'keypress' && event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        slSecurityToggleEventDetails(this);
    });
});