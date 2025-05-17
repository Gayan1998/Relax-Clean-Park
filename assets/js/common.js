/**
 * Common JavaScript functions for Auto Service System
 */

// Initialize common components
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 for all select elements with the 'select2' class
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search...',
            allowClear: true,
            width: '100%'
        });
    }
    
    // Initialize tooltips
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Initialize popovers
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Popover !== 'undefined') {
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    }
    
    // Add confirmation to delete buttons
    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
});

// Flash message handler
function showAlert(message, type = 'success', timeout = 5000) {
    // Create alert element
    const alertElement = document.createElement('div');
    alertElement.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertElement.style.top = '20px';
    alertElement.style.right = '20px';
    alertElement.style.zIndex = '9999';
    alertElement.style.maxWidth = '400px';
    alertElement.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
    
    // Add content
    alertElement.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Add to document body
    document.body.appendChild(alertElement);
    
    // Auto remove after timeout
    if (timeout > 0) {
        setTimeout(() => {
            if (alertElement.parentNode) {
                alertElement.classList.remove('show');
                setTimeout(() => alertElement.remove(), 300);
            }
        }, timeout);
    }
    
    return alertElement;
}

// Format currency with LKR prefix
function formatCurrency(amount) {
    return 'LKR ' + parseFloat(amount).toFixed(2);
}

// Format date to display format
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

// Check if element is in viewport
function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

// Debounce function for input events
function debounce(func, wait, immediate) {
    let timeout;
    return function() {
        const context = this, args = arguments;
        const later = function() {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
    };
}

// Ajax form submission handler
function submitFormAjax(formElement, successCallback, errorCallback) {
    const formData = new FormData(formElement);
    
    fetch(formElement.action, {
        method: formElement.method || 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (typeof successCallback === 'function') {
                successCallback(data);
            } else {
                showAlert(data.message || 'Operation completed successfully.', 'success');
            }
        } else {
            if (typeof errorCallback === 'function') {
                errorCallback(data);
            } else {
                showAlert(data.message || 'There was an error processing your request.', 'danger');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (typeof errorCallback === 'function') {
            errorCallback({ success: false, message: error.message });
        } else {
            showAlert('There was a problem connecting to the server.', 'danger');
        }
    });
    
    return false; // Prevent default form submission
}
