/**
 * ClaimIt Web Application JavaScript
 */

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function () {

    // Initialize application
    ClaimItApp.init();

});

// Main application object
const ClaimItApp = {

    // Initialize the application
    init: function () {
        this.setupNavigation();
        this.setupForms();
        this.setupAlerts();
        this.setupAnimations();
        console.log('ClaimIt App initialized');
    },

    // Navigation functionality
    setupNavigation: function () {
        // Mobile navigation toggle (if needed in future)
        const navToggle = document.querySelector('.nav-toggle');
        const navMenu = document.querySelector('.nav-menu');

        if (navToggle && navMenu) {
            navToggle.addEventListener('click', function () {
                navMenu.classList.toggle('active');
            });
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    },

    // Form functionality
    setupForms: function () {
        // Claim form validation
        const claimForm = document.querySelector('.claim-form');
        if (claimForm) {
            claimForm.addEventListener('submit', this.validateClaimForm);
        }

        // Contact form validation
        const contactForm = document.querySelector('.contact-form form');
        if (contactForm) {
            contactForm.addEventListener('submit', this.validateContactForm);
        }

        // File upload validation
        const fileInput = document.querySelector('#item_photo');
        if (fileInput) {
            fileInput.addEventListener('change', this.validateFileUpload);
        }

        // Real-time validation
        this.setupRealTimeValidation();
    },

    // Real-time form validation
    setupRealTimeValidation: function () {
        const inputs = document.querySelectorAll('input, textarea, select');

        inputs.forEach(input => {
            input.addEventListener('blur', function () {
                ClaimItApp.validateField(this);
            });

            input.addEventListener('input', function () {
                // Clear previous error styling on input
                this.classList.remove('error');
                const errorMsg = this.parentNode.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.remove();
                }
            });
        });
    },

    // Validate individual field
    validateField: function (field) {
        let isValid = true;
        let errorMessage = '';

        // Remove previous error
        field.classList.remove('error');
        const existingError = field.parentNode.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }

        // Check if field is required and empty
        if (field.hasAttribute('required') && !field.value.trim()) {
            isValid = false;
            errorMessage = 'This field is required';
        }

        // Email validation
        if (field.type === 'email' && field.value.trim()) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(field.value)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address';
            }
        }

        // Number validation
        if (field.type === 'number' && field.value.trim()) {
            if (isNaN(field.value) || parseFloat(field.value) < 0) {
                isValid = false;
                errorMessage = 'Please enter a valid positive number';
            }
        }

        // Show error if validation failed
        if (!isValid) {
            this.showFieldError(field, errorMessage);
        }

        return isValid;
    },

    // File upload validation
    validateFileUpload: function (e) {
        const file = e.target.files[0];
        const maxSize = 52428800; // 50MB in bytes

        // Clear previous error
        ClaimItApp.clearFieldError(e.target);

        if (file) {
            // Check file size
            if (file.size > maxSize) {
                ClaimItApp.showFieldError(e.target, 'File is too large. Maximum upload size is 50MB.');
                e.target.value = ''; // Clear the selected file
                return false;
            }

            // Check file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type.toLowerCase())) {
                ClaimItApp.showFieldError(e.target, 'File must be a valid image (JPG, PNG, GIF)');
                e.target.value = ''; // Clear the selected file
                return false;
            }

            // Show success feedback
            ClaimItApp.showFieldSuccess(e.target, `File selected: ${file.name} (${Math.round(file.size / 1024)}KB)`);
        }

        return true;
    },

    // Show field error
    showFieldError: function (field, message) {
        this.clearFieldError(field);
        field.classList.add('error');

        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        errorDiv.style.color = 'var(--danger-color)';
        errorDiv.style.fontSize = '0.875rem';
        errorDiv.style.marginTop = '0.25rem';

        field.parentNode.appendChild(errorDiv);
    },

    // Show field success
    showFieldSuccess: function (field, message) {
        this.clearFieldError(field);
        field.classList.remove('error');

        const successDiv = document.createElement('div');
        successDiv.className = 'success-message';
        successDiv.textContent = message;
        successDiv.style.color = 'var(--success-color)';
        successDiv.style.fontSize = '0.875rem';
        successDiv.style.marginTop = '0.25rem';

        field.parentNode.appendChild(successDiv);
    },

    // Clear field error/success
    clearFieldError: function (field) {
        field.classList.remove('error');
        const errorMsg = field.parentNode.querySelector('.error-message');
        const successMsg = field.parentNode.querySelector('.success-message');
        if (errorMsg) {
            errorMsg.remove();
        }
        if (successMsg) {
            successMsg.remove();
        }
    },

    // Validate claim form
    validateClaimForm: function (e) {
        const form = e.target;
        let isValid = true;

        // Validate all required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!ClaimItApp.validateField(field)) {
                isValid = false;
            }
        });

        // Additional claim-specific validation
        const amount = form.querySelector('#amount');
        if (amount && amount.value) {
            const amountValue = parseFloat(amount.value);
            if (amountValue < 0) {
                ClaimItApp.showFieldError(amount, 'Valid amount is required (must be 0 or greater)');
                isValid = false;
            }
        }

        if (!isValid) {
            e.preventDefault();
            ClaimItApp.showAlert('Please correct the errors below', 'error');
        }
    },

    // Validate contact form
    validateContactForm: function (e) {
        const form = e.target;
        let isValid = true;

        // Validate all required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!ClaimItApp.validateField(field)) {
                isValid = false;
            }
        });

        if (!isValid) {
            e.preventDefault();
            ClaimItApp.showAlert('Please correct the errors below', 'error');
        } else {
            // Show success message (since we don't have backend processing)
            e.preventDefault();
            ClaimItApp.showAlert('Thank you for your message! We will get back to you soon.', 'success');
            form.reset();
        }
    },

    // Alert functionality
    setupAlerts: function () {
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                this.hideAlert(alert);
            }, 5000);
        });
    },

    // Show alert
    showAlert: function (message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert - ${type}`;
        alertDiv.textContent = message;

        // Insert at top of main content
        const mainContent = document.querySelector('.main-content');
        const container = mainContent.querySelector('.container');
        container.insertBefore(alertDiv, container.firstChild);

        // Auto-hide after 5 seconds
        setTimeout(() => {
            this.hideAlert(alertDiv);
        }, 5000);
    },

    // Hide alert
    hideAlert: function (alert) {
        if (alert && alert.parentNode) {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            alert.style.transition = 'all 0.3s ease';

            setTimeout(() => {
                alert.remove();
            }, 300);
        }
    },

    // Setup animations
    setupAnimations: function () {
        // Fade in animation for feature cards
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe feature cards
        const featureCards = document.querySelectorAll('.feature-card');
        featureCards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease';
            observer.observe(card);
        });
    },

    // Utility functions
    utils: {
        // Debounce function
        debounce: function (func, wait) {
            let timeout;
            return function executedFunction(...args)
            {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // Format currency
        formatCurrency: function (amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        },

        // Format date
        formatDate: function (date) {
            return new Intl.DateTimeFormat('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }).format(new Date(date));
        }
    }
};

// Add error styles via JavaScript
const style = document.createElement('style');
style.textContent = `
    .form - group input.error,
    .form - group select.error,
    .form - group textarea.error {
        border - color: #dc3545;
        box - shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }
    `;
    document.head.appendChild(style);

// User dropdown functionality - make globally accessible for inline handlers
    window.toggleUserDropdown = function()
    {
        const dropdown = document.querySelector('.nav-user-dropdown');
        const dropdownMenu = document.getElementById('userDropdown');

        if (dropdown && dropdownMenu) {
            dropdown.classList.toggle('active');

            // Close dropdown when clicking outside
            document.addEventListener('click', function closeDropdown(e)
            {
                if (!dropdown.contains(e.target)) {
                    dropdown.classList.remove('active');
                    document.removeEventListener('click', closeDropdown);
                }
            });
        }
    }

// Mobile menu toggle functionality
    window.toggleMobileMenu = function()
    {
        const menu = document.getElementById('navMenu');
        const hamburger = document.querySelector('.hamburger-menu');
        
        if (menu && hamburger) {
            menu.classList.toggle('active');
            hamburger.classList.toggle('active');
        }
    }

    // Close mobile menu when clicking on a link or outside
    document.addEventListener('DOMContentLoaded', function() {
        const menu = document.getElementById('navMenu');
        const hamburger = document.querySelector('.hamburger-menu');
        const navbar = document.querySelector('.navbar');
        
        if (menu) {
            // Close when clicking links (except user dropdown trigger)
            const links = menu.querySelectorAll('a, button');
            links.forEach(link => {
                link.addEventListener('click', function() {
                    if (!this.classList.contains('nav-user-trigger')) {
                        menu.classList.remove('active');
                        if (hamburger) {
                            hamburger.classList.remove('active');
                        }
                    }
                });
            });
            
            // Close when clicking outside
            document.addEventListener('click', function(e) {
                if (menu.classList.contains('active') && 
                    navbar && 
                    !navbar.contains(e.target)) {
                    menu.classList.remove('active');
                    if (hamburger) {
                        hamburger.classList.remove('active');
                    }
                }
            });
        }
    });


// Edit Modal Functions
    // Make these functions globally accessible for inline handlers
    window.openEditModal = function(trackingNumber, title, description)
    {
        const modal = document.getElementById('editModal');
        if (modal) {
            // Populate the form fields
            document.getElementById('editTrackingNumber').value = trackingNumber;
            document.getElementById('editTitle').value = title;
            document.getElementById('editDescription').value = description;

            // Fetch and populate community checkboxes
            fetch(`?page=claim&action=get_item_communities&id=${encodeURIComponent(trackingNumber)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.populateCommunityCheckboxes(data.communities || [], data.itemCommunities || []);
                    }
                })
                .catch(error => console.error('Error loading communities:', error));

            // Show the modal
            modal.style.display = 'block';
        }
    }

    window.openEditModalFromButton = function(button)
    {
        const trackingNumber = button.getAttribute('data-tracking');
        const title = button.getAttribute('data-title');
        const description = button.getAttribute('data-description');

        window.openEditModal(trackingNumber, title, description);
    }

    window.populateCommunityCheckboxes = function(allCommunities, itemCommunityIds)
    {
        const container = document.getElementById('editCommunityCheckboxes');
        if (!container) {
            return;
        }

        let html = '';

        allCommunities.forEach(comm => {
            const isChecked = itemCommunityIds.includes(comm.id);
            html += `
                <div class="community-checkbox-item">
                    <input type="checkbox" 
                           name="communities[]" 
                           value="${comm.id}" 
                           id="edit_community_${comm.id}"
                           class="community-checkbox"
                           ${isChecked ? 'checked' : ''}>
                    <label for="edit_community_${comm.id}">${comm.full_name}</label>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    window.closeEditModal = function()
    {
        const modal = document.getElementById('editModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // Community selection - no special handlers needed, checkboxes work independently

    window.saveItemEdit = function()
    {
        const form = document.getElementById('editForm');
        const formData = new FormData(form);
        const trackingNumber = formData.get('trackingNumber');
        const title = formData.get('title');
        const description = formData.get('description');

        if (!title.trim() || !description.trim()) {
            showMessage('Title and description are required', 'error');
            return;
        }

        // Collect community checkboxes
        const communityCheckboxes = document.querySelectorAll('#editCommunityCheckboxes input[type="checkbox"]:checked');
        const communities = Array.from(communityCheckboxes).map(cb => cb.value);

        // Empty selection is allowed (creates invisible/staging item)

        // Build the request body
        let body = `action=edit_item&id=${encodeURIComponent(trackingNumber)}&title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}`;
        communities.forEach(comm => {
            body += `&communities[]=${encodeURIComponent(comm)}`;
        });

        fetch('?page=claim', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                closeEditModal();
                // Reload the page to show the updated content
                setTimeout(() => {
                    window.location.reload(true); // Force hard reload
                }, 1000);
            } else {
                showMessage(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error saving item edit:', error);
            showMessage('An error occurred while saving changes', 'error');
        });
    }

    window.showMessage = function(message, type = 'info')
    {
        // Create a simple message display
        const messageDiv = document.createElement('div');
        messageDiv.className = `message message - ${type}`;
        messageDiv.textContent = message;
        messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border - radius: 8px;
        color: white;
        font - weight: 500;
        z - index: 10000;
        max - width: 400px;
        word - wrap: break - word;
        `;

        if (type === 'success') {
            messageDiv.style.backgroundColor = '#28a745';
        } else if (type === 'error') {
            messageDiv.style.backgroundColor = '#dc3545';
        } else {
            messageDiv.style.backgroundColor = '#17a2b8';
        }

        document.body.appendChild(messageDiv);

        // Remove the message after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.parentNode.removeChild(messageDiv);
            }
        }, 5000);
    }

// Mark item as gone
    window.markItemGone = function(trackingNumber)
    {
        const button = document.querySelector(`button[onclick="markItemGone('${trackingNumber}')"]`);
        if (!button) {
            return;
        }

        // Show loading state
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '⏳';

        // Send AJAX request
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_gone&id=${encodeURIComponent(trackingNumber)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                // Reload the page to show updated status
                setTimeout(() => {
                    window.location.reload(true); // Force hard reload
                }, 500);
            } else {
                showMessage(data.message, 'error');
                // Restore button
                button.disabled = false;
                button.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error marking item as gone:', error);
            showMessage('Network error while marking item as gone: ' + error.message, 'error');
            // Restore button
            button.disabled = false;
            button.innerHTML = originalText;
        });
    }

// Re-list item (mark as not gone)
    window.relistItem = function(trackingNumber)
    {
        const button = document.querySelector(`button[onclick="relistItem('${trackingNumber}')"]`);
        if (!button) {
            return;
        }

        // Show loading state
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '⏳';

        // Send AJAX request
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=relist_item&id=${encodeURIComponent(trackingNumber)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                // Reload the page to show updated status
                setTimeout(() => {
                    window.location.reload(true); // Force hard reload
                }, 500);
            } else {
                showMessage(data.message, 'error');
                // Restore button
                button.disabled = false;
                button.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error re-listing item:', error);
            showMessage('Network error while re-listing item: ' + error.message, 'error');
            // Restore button
            button.disabled = false;
            button.innerHTML = originalText;
        });
    }

// Handle edit form submission
    document.addEventListener('DOMContentLoaded', function () {
        const editForm = document.getElementById('editForm');
        if (editForm) {
            editForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveItemEdit();
            });
        }
    });

