/**
 * WTRS Global JavaScript Utilities
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Sidebar Toggle Mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }

    // 2. Global Form Loading State
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        // Skip forms that have 'no-confirm' or special handlers
        if (form.classList.contains('no-confirm')) return;

        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.classList.contains('no-confirm')) {
                // We'll handle confirmations separately
            }
        });
    });

    // 3. Tooltips / Date detail hover
    // Handled via CSS title mostly, but can add JS enhancements here
});

/**
 * Global Confirmation Modal
 * @param {string} title 
 * @param {string} message 
 * @param {string} type 'warning' | 'danger' | 'info'
 * @returns Promise<boolean>
 */
window.confirmAction = function(title, message, type = 'warning') {
    return new Promise((resolve) => {
        const modal = document.getElementById('confirmModal');
        const titleEl = document.getElementById('confirmTitle');
        const msgEl = document.getElementById('confirmMessage');
        const iconEl = document.getElementById('confirmIcon');
        const proceedBtn = document.getElementById('confirmProceed');
        const cancelBtn = document.getElementById('confirmCancel');

        if (!modal) {
            resolve(confirm(message));
            return;
        }

        titleEl.innerText = title;
        msgEl.innerText = message;
        
        // Style based on type
        if (type === 'danger') {
            iconEl.innerHTML = '<i class="ph-fill ph-warning-circle"></i>';
            iconEl.style.color = '#DC2626';
            proceedBtn.style.background = '#DC2626';
        } else {
            iconEl.innerHTML = '<i class="ph-fill ph-question"></i>';
            iconEl.style.color = 'var(--crimson)';
            proceedBtn.style.background = 'var(--crimson)';
        }

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        const cleanup = (result) => {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            proceedBtn.removeEventListener('click', onProceed);
            cancelBtn.removeEventListener('click', onCancel);
            resolve(result);
        };

        const onProceed = () => cleanup(true);
        const onCancel = () => cleanup(false);

        proceedBtn.addEventListener('click', onProceed);
        cancelBtn.addEventListener('click', onCancel);
    });
};

// Intercept all links with .btn-confirm or forms with .form-confirm
document.addEventListener('click', async (e) => {
    const confirmBtn = e.target.closest('.btn-confirm');
    if (confirmBtn) {
        e.preventDefault();
        const title = confirmBtn.dataset.confirmTitle || 'Are you sure?';
        const msg = confirmBtn.dataset.confirmMessage || 'This action cannot be undone.';
        const type = confirmBtn.dataset.confirmType || 'warning';
        
        if (await window.confirmAction(title, msg, type)) {
            window.location.href = confirmBtn.href;
        }
    }
});

document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (form.classList.contains('form-confirm') || form.dataset.confirm) {
        if (form.dataset.confirmed === 'true') return;
        
        e.preventDefault();
        const title = form.dataset.confirmTitle || 'Confirm Submission';
        const msg = form.dataset.confirmMessage || 'Please verify all details before proceeding.';
        
        if (await window.confirmAction(title, msg)) {
            form.dataset.confirmed = 'true';
            form.submit();
        }
    }
});
