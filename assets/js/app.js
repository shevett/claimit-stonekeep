/**
 * ClaimIt Web Application JavaScript
 */

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize application
    ClaimItApp.init();
    
});

// Main application object
const ClaimItApp = {
    
    // Initialize the application
    init: function() {
        this.setupNavigation();
        this.setupForms();
        this.setupAlerts();
        this.setupAnimations();
        console.log('ClaimIt App initialized');
    },
    
    // Navigation functionality
    setupNavigation: function() {
        // Mobile navigation toggle (if needed in future)
        const navToggle = document.querySelector('.nav-toggle');
        const navMenu = document.querySelector('.nav-menu');
        
        if (navToggle && navMenu) {
            navToggle.addEventListener('click', function() {
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
    setupForms: function() {
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
        
        // Real-time validation
        this.setupRealTimeValidation();
    },
    
    // Real-time form validation
    setupRealTimeValidation: function() {
        const inputs = document.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                ClaimItApp.validateField(this);
            });
            
            input.addEventListener('input', function() {
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
    validateField: function(field) {
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
    
    // Show field error
    showFieldError: function(field, message) {
        field.classList.add('error');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        errorDiv.style.color = '#dc3545';
        errorDiv.style.fontSize = '0.875rem';
        errorDiv.style.marginTop = '0.25rem';
        
        field.parentNode.appendChild(errorDiv);
    },
    
    // Validate claim form
    validateClaimForm: function(e) {
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
            if (amountValue <= 0) {
                ClaimItApp.showFieldError(amount, 'Claim amount must be greater than 0');
                isValid = false;
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            ClaimItApp.showAlert('Please correct the errors below', 'error');
        }
    },
    
    // Validate contact form
    validateContactForm: function(e) {
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
    setupAlerts: function() {
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                this.hideAlert(alert);
            }, 5000);
        });
    },
    
    // Show alert
    showAlert: function(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
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
    hideAlert: function(alert) {
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
    setupAnimations: function() {
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
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        // Format currency
        formatCurrency: function(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        },
        
        // Format date
        formatDate: function(date) {
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
    .form-group input.error,
    .form-group select.error,
    .form-group textarea.error {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }
`;
document.head.appendChild(style); 