/**
 * Form Validation Utilities for ArbeitszeitCheck App
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

const ArbeitszeitCheckValidation = {
  /**
   * Validate form field
   */
  validateField(field, rules = {}) {
    const value = field.value.trim();
    const errors = [];
    const fieldLabel = field.labels && field.labels[0] ? field.labels[0].textContent : 'This field';
    const l10n = window.ArbeitszeitCheck?.l10n || {};

    // Required validation
    if (rules.required && !value) {
      const message = l10n.fieldRequired || `${fieldLabel} is required. Please fill in this field.`;
      errors.push(message);
    }

    // Email validation
    if (rules.email && value && !this.isEmail(value)) {
      const message = l10n.emailInvalid || `Please enter a valid email address. Example: name@example.com`;
      errors.push(message);
    }

    // Min length validation
    if (rules.minLength && value.length < rules.minLength) {
      const message = l10n.minLength || `Please enter at least ${rules.minLength} characters. You entered ${value.length} characters.`;
      errors.push(message);
    }

    // Max length validation
    if (rules.maxLength && value.length > rules.maxLength) {
      const message = l10n.maxLength || `Please enter no more than ${rules.maxLength} characters. You entered ${value.length} characters.`;
      errors.push(message);
    }

    // Pattern validation
    if (rules.pattern && value && !rules.pattern.test(value)) {
      const message = rules.patternMessage || l10n.invalidFormat || `The format is incorrect. ${rules.formatExample || 'Please check the format and try again.'}`;
      errors.push(message);
    }

    // Number validation
    if (rules.number) {
      const num = parseFloat(value);
      if (isNaN(num)) {
        const message = l10n.numberInvalid || `Please enter a valid number. Example: ${rules.example || '8'}`;
        errors.push(message);
      } else {
        if (rules.min !== undefined && num < rules.min) {
          const message = l10n.numberMin || `Please enter a number that is at least ${rules.min}. You entered ${num}.`;
          errors.push(message);
        }
        if (rules.max !== undefined && num > rules.max) {
          const message = l10n.numberMax || `Please enter a number that is no more than ${rules.max}. You entered ${num}.`;
          errors.push(message);
        }
      }
    }

    // Time validation
    if (rules.time && value) {
      const timePattern = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;
      if (!timePattern.test(value)) {
        const message = l10n.timeInvalid || `Please enter the time in this format: HH:MM (hours:minutes). Example: 09:00 for 9 in the morning, or 14:30 for 2:30 in the afternoon.`;
        errors.push(message);
      }
    }

    // Date validation
    if (rules.date && value) {
      const date = new Date(value);
      if (isNaN(date.getTime())) {
        const message = l10n.dateInvalid || `Please enter a valid date. Click the calendar icon to pick a date, or enter it in this format: YYYY-MM-DD. Example: 2024-01-15 for January 15, 2024.`;
        errors.push(message);
      }
    }

    // Custom validation
    if (rules.custom && typeof rules.custom === 'function') {
      const customError = rules.custom(value, field);
      if (customError) {
        errors.push(customError);
      }
    }

    return {
      valid: errors.length === 0,
      errors: errors
    };
  },

  /**
   * Validate entire form
   */
  validateForm(form, rules = {}) {
    if (typeof form === 'string') {
      form = document.querySelector(form);
    }

    if (!form) {
      return { valid: false, errors: {} };
    }

    const errors = {};
    let isValid = true;

    // Clear previous errors
    this.clearFormErrors(form);

    // Validate each field
    Object.keys(rules).forEach(fieldName => {
      const field = form.querySelector(`[name="${fieldName}"]`);
      if (field) {
        const result = this.validateField(field, rules[fieldName]);
        if (!result.valid) {
          isValid = false;
          errors[fieldName] = result.errors;
          this.showFieldError(field, result.errors[0]);
        }
      }
    });

    return {
      valid: isValid,
      errors: errors
    };
  },

  /**
   * Show field error with helpful message
   */
  showFieldError(field, message) {
    // Remove existing error
    this.clearFieldError(field);

    // Add error class to input
    field.classList.add('form-input--error');
    field.setAttribute('aria-invalid', 'true');
    
    // Get or create error container
    let errorContainer = field.parentNode.querySelector('.form-error-container');
    if (!errorContainer) {
      errorContainer = document.createElement('div');
      errorContainer.className = 'form-error-container';
      field.parentNode.appendChild(errorContainer);
    }

    // Create error message element with icon
    const errorElement = document.createElement('div');
    errorElement.className = 'form-error';
    errorElement.setAttribute('role', 'alert');
    errorElement.setAttribute('aria-live', 'polite');
    errorElement.setAttribute('id', field.id ? `${field.id}-error` : `error-${Date.now()}`);
    
    // Set aria-describedby on field
    const errorId = errorElement.id;
    const currentDescribedBy = field.getAttribute('aria-describedby') || '';
    if (!currentDescribedBy.includes(errorId)) {
      field.setAttribute('aria-describedby', 
        currentDescribedBy ? `${currentDescribedBy} ${errorId}` : errorId);
    }

    // Create error content with icon and message
    errorElement.innerHTML = `
      <span class="form-error__icon" aria-hidden="true">⚠️</span>
      <div class="form-error__content">
        <strong class="form-error__title">${this.escapeHtml(message.split('.')[0])}</strong>
        ${message.includes('.') ? `<p class="form-error__description">${this.escapeHtml(message.substring(message.indexOf('.') + 1).trim())}</p>` : ''}
      </div>
    `;

    errorContainer.appendChild(errorElement);
    
    // Scroll to error if needed
    if (errorElement.offsetParent === null || !this.isElementInViewport(errorElement)) {
      errorElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  },

  /**
   * Escape HTML to prevent XSS
   */
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },

  /**
   * Check if element is in viewport
   */
  isElementInViewport(el) {
    const rect = el.getBoundingClientRect();
    return (
      rect.top >= 0 &&
      rect.left >= 0 &&
      rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
      rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
  },

  /**
   * Clear field error
   */
  clearFieldError(field) {
    field.classList.remove('form-input--error', 'error');
    field.removeAttribute('aria-invalid');
    
    // Remove aria-describedby reference to error
    const currentDescribedBy = field.getAttribute('aria-describedby') || '';
    const errorId = field.id ? `${field.id}-error` : '';
    if (errorId && currentDescribedBy.includes(errorId)) {
      const newDescribedBy = currentDescribedBy.replace(errorId, '').trim();
      if (newDescribedBy) {
        field.setAttribute('aria-describedby', newDescribedBy);
      } else {
        field.removeAttribute('aria-describedby');
      }
    }

    // Remove error message elements
    const errorContainer = field.parentNode.querySelector('.form-error-container');
    if (errorContainer) {
      errorContainer.remove();
    }
    
    // Also remove old-style .field-error for backward compatibility
    const oldErrorElement = field.parentNode.querySelector('.field-error');
    if (oldErrorElement) {
      oldErrorElement.remove();
    }
  },

  /**
   * Clear all form errors
   */
  clearFormErrors(form) {
    if (typeof form === 'string') {
      form = document.querySelector(form);
    }

    if (!form) return;

    // Clear errors from fields with new class
    const errorFields = form.querySelectorAll('.form-input--error, .error');
    errorFields.forEach(field => {
      this.clearFieldError(field);
    });
    
    // Also clear any standalone error containers
    const errorContainers = form.querySelectorAll('.form-error-container');
    errorContainers.forEach(container => {
      container.remove();
    });
  },

  /**
   * Check if email is valid
   */
  isEmail(value) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(value);
  },

  /**
   * Check if value is numeric
   */
  isNumeric(value) {
    return !isNaN(parseFloat(value)) && isFinite(value);
  }
};

// Export for use in other modules
if (typeof window !== 'undefined') {
  window.ArbeitszeitCheckValidation = ArbeitszeitCheckValidation;
  // Also add to ArbeitszeitCheck namespace for consistency
  if (!window.ArbeitszeitCheck) {
    window.ArbeitszeitCheck = {};
  }
  window.ArbeitszeitCheck.Validation = ArbeitszeitCheckValidation;
}
