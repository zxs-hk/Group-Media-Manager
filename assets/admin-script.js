// Modern JavaScript for Group Media Manager

jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize the admin interface
    GMM_Admin.init();
});

var GMM_Admin = {
    
    init: function() {
        this.bindEvents();
        this.initSortable();
        this.initSearch();
        this.initAccessibility();
        this.initResponsive();
    },
    
    bindEvents: function() {
        var self = this;
        
        // Privacy toggle buttons
        $(document).on('click', '.gmm-toggle-privacy', function(e) {
            e.preventDefault();
            self.togglePrivacy($(this));
        });
        
        // Select all checkbox
        $(document).on('change', '#gmm-select-all', function() {
            $('.gmm-item-checkbox').prop('checked', $(this).is(':checked'));
            self.updateBulkActions();
        });
        
        // Individual checkboxes
        $(document).on('change', '.gmm-item-checkbox', function() {
            self.updateSelectAll();
            self.updateBulkActions();
        });
        
        // Bulk actions
        $(document).on('click', '#gmm-apply-bulk', function(e) {
            e.preventDefault();
            self.applyBulkAction();
        });
        
        // Form validation
        $('form').on('submit', function(e) {
            if (!self.validateForm($(this))) {
                e.preventDefault();
            }
        });
        
        // Auto-save indication for settings
        $('.gmm-settings-form input, .gmm-settings-form select').on('change', function() {
            self.autoSaveIndication();
        });
        
        // Smooth scrolling for internal links
        $('a[href^="#"]').on('click', function(e) {
            var target = $(this.getAttribute('href'));
            if (target.length) {
                e.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: target.offset().top - 100
                }, 600);
            }
        });
        
        // Card hover effects
        $('.gmm-stat-card, .gmm-group-card').on('mouseenter', function() {
            $(this).addClass('hovered');
        }).on('mouseleave', function() {
            $(this).removeClass('hovered');
        });
        
        // Confirmation dialogs
        $('[data-confirm]').on('click', function(e) {
            var message = $(this).data('confirm');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    },
    
    togglePrivacy: function($button) {
        var attachmentId = $button.data('id');
        var currentStatus = $button.data('status');
        var newStatus = currentStatus === 'public' ? 'private' : 'public';
        
        // Add loading state
        $button.addClass('gmm-loading').prop('disabled', true);
        var originalText = $button.text();
        $button.text('Processing...');
        
        $.ajax({
            url: gmm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gmm_toggle_privacy',
                attachment_id: attachmentId,
                new_status: newStatus,
                nonce: gmm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update button
                    $button.data('status', newStatus);
                    $button.text(newStatus === 'public' ? 'Make Private' : 'Make Public');
                    
                    // Show success notification
                    GMM_Admin.showNotification('Privacy updated successfully!', 'success');
                    
                    // Update the row styling
                    var $row = $button.closest('tr');
                    if (newStatus === 'public') {
                        $row.addClass('gmm-success');
                    } else {
                        $row.removeClass('gmm-success');
                    }
                    
                    // Auto-refresh after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    GMM_Admin.showNotification('Failed to update privacy setting.', 'error');
                    $button.text(originalText);
                }
            },
            error: function() {
                GMM_Admin.showNotification('An error occurred. Please try again.', 'error');
                $button.text(originalText);
            },
            complete: function() {
                $button.removeClass('gmm-loading').prop('disabled', false);
            }
        });
    },
    
    updateSelectAll: function() {
        var total = $('.gmm-item-checkbox').length;
        var checked = $('.gmm-item-checkbox:checked').length;
        
        var $selectAll = $('#gmm-select-all');
        $selectAll.prop('checked', total > 0 && checked === total);
        $selectAll.prop('indeterminate', checked > 0 && checked < total);
    },
    
    updateBulkActions: function() {
        var checked = $('.gmm-item-checkbox:checked').length;
        var $applyButton = $('#gmm-apply-bulk');
        
        $applyButton.prop('disabled', checked === 0);
        
        if (checked > 0) {
            $applyButton.text('Apply (' + checked + ')');
        } else {
            $applyButton.text('Apply');
        }
        
        // Update bulk action dropdown accessibility
        $('#gmm-bulk-action').attr('aria-describedby', checked > 0 ? 'bulk-selected' : '');
    },
    
    applyBulkAction: function() {
        var action = $('#gmm-bulk-action').val();
        var items = $('.gmm-item-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (!action || items.length === 0) {
            this.showNotification('Please select an action and items.', 'error');
            return;
        }
        
        var actionText = $('#gmm-bulk-action option:selected').text();
        if (!confirm('Are you sure you want to "' + actionText + '" on ' + items.length + ' item(s)?')) {
            return;
        }
        
        // Add loading state
        var $applyButton = $('#gmm-apply-bulk');
        $applyButton.addClass('gmm-loading').prop('disabled', true);
        var originalText = $applyButton.text();
        $applyButton.text('Processing...');
        
        $.ajax({
            url: gmm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gmm_bulk_action',
                bulk_action: action,
                items: items,
                nonce: gmm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    GMM_Admin.showNotification('Bulk action completed successfully!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    GMM_Admin.showNotification(response.data || 'Bulk action failed.', 'error');
                    $applyButton.text(originalText).removeClass('gmm-loading').prop('disabled', false);
                }
            },
            error: function() {
                GMM_Admin.showNotification('An error occurred during bulk action.', 'error');
                $applyButton.text(originalText).removeClass('gmm-loading').prop('disabled', false);
            }
        });
    },
    
    initSortable: function() {
        // Make table headers sortable with visual feedback
        $('.gmm-table th a').on('click', function() {
            $(this).closest('th').addClass('gmm-loading');
        });
        
        // Add sort indicators
        $('.gmm-table th a').each(function() {
            var $link = $(this);
            var href = $link.attr('href');
            
            if (href && href.includes('order=desc')) {
                $link.append(' <span class="dashicons dashicons-arrow-down-alt2"></span>');
            } else if (href && href.includes('order=asc')) {
                $link.append(' <span class="dashicons dashicons-arrow-up-alt2"></span>');
            }
        });
    },
    
    initSearch: function() {
        var searchTimeout;
        
        // Debounced search
        $('.gmm-search-box input[type="search"]').on('input', function() {
            var $input = $(this);
            var query = $input.val();
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                if (query.length >= 3 || query.length === 0) {
                    // Auto-submit search after typing
                    $input.closest('form').submit();
                }
            }, 500);
        });
        
        // Search highlights
        this.highlightSearchTerms();
        
        // Clear search button
        $('.gmm-search-box').each(function() {
            var $searchBox = $(this);
            var $input = $searchBox.find('input[type="search"]');
            
            if ($input.val()) {
                if (!$searchBox.find('.gmm-clear-search').length) {
                    $input.after('<button type="button" class="gmm-clear-search" title="Clear search">×</button>');
                }
            }
        });
        
        $(document).on('click', '.gmm-clear-search', function() {
            var $input = $(this).siblings('input[type="search"]');
            $input.val('').trigger('input');
            $(this).closest('form').submit();
        });
    },
    
    initAccessibility: function() {
        // Add ARIA labels and roles
        $('.gmm-table').attr('role', 'table');
        $('.gmm-table thead').attr('role', 'rowgroup');
        $('.gmm-table tbody').attr('role', 'rowgroup');
        $('.gmm-table tr').attr('role', 'row');
        $('.gmm-table th').attr('role', 'columnheader');
        $('.gmm-table td').attr('role', 'cell');
        
        // Enhanced focus management
        $('.gmm-btn, .gmm-input, .gmm-select').on('focus', function() {
            $(this).addClass('focused');
        }).on('blur', function() {
            $(this).removeClass('focused');
        });
        
        // Keyboard navigation for cards
        $('.gmm-group-card, .gmm-stat-card').attr('tabindex', '0').on('keypress', function(e) {
            if (e.which === 13 || e.which === 32) { // Enter or Space
                var $link = $(this).find('a').first();
                if ($link.length) {
                    $link[0].click();
                }
            }
        });
    },
    
    initResponsive: function() {
        // Handle window resize for responsive tables
        $(window).on('resize', this.handleResize.bind(this));
        this.handleResize(); // Initial call
        
        // Mobile menu toggle for cards
        if (window.innerWidth <= 768) {
            $('.gmm-group-actions').addClass('mobile-actions');
        }
    },
    
    handleResize: function() {
        var $tables = $('.gmm-table');
        var $window = $(window);
        
        if ($window.width() < 768) {
            $tables.addClass('gmm-responsive');
            this.makeTablesResponsive();
        } else {
            $tables.removeClass('gmm-responsive');
        }
        
        // Adjust card layouts
        if ($window.width() <= 480) {
            $('.gmm-stat-card').addClass('mobile-card');
        } else {
            $('.gmm-stat-card').removeClass('mobile-card');
        }
    },
    
    makeTablesResponsive: function() {
        $('.gmm-table.gmm-responsive').each(function() {
            var $table = $(this);
            
            if (!$table.hasClass('processed')) {
                var $headers = $table.find('th');
                
                $table.find('tbody tr').each(function() {
                    var $row = $(this);
                    
                    $row.find('td').each(function(index) {
                        var $cell = $(this);
                        var headerText = $headers.eq(index).text();
                        
                        if (headerText && !$cell.attr('data-label')) {
                            $cell.attr('data-label', headerText);
                        }
                    });
                });
                
                $table.addClass('processed');
            }
        });
    },
    
    highlightSearchTerms: function() {
        var urlParams = new URLSearchParams(window.location.search);
        var searchTerm = urlParams.get('s');
        
        if (searchTerm && searchTerm.length > 0) {
            $('.gmm-table tbody').find('td').each(function() {
                var $cell = $(this);
                var text = $cell.text();
                var regex = new RegExp('(' + searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                var highlightedText = text.replace(regex, '<mark class="gmm-highlight">$1</mark>');
                
                if (highlightedText !== text) {
                    $cell.html(highlightedText);
                }
            });
        }
    },
    
    validateForm: function($form) {
        var isValid = true;
        var errors = [];
        
        // Remove previous error states
        $form.find('.gmm-error').removeClass('gmm-error');
        $form.find('.error-message').remove();
        
        // Check required fields
        $form.find('[required]').each(function() {
            var $field = $(this);
            var value = $field.val().trim();
            var fieldName = $field.attr('name') || $field.attr('id') || 'Field';
            
            if (!value) {
                $field.addClass('gmm-error');
                var errorMsg = $field.attr('placeholder') || fieldName;
                errors.push(errorMsg + ' is required');
                
                // Add inline error message
                $field.after('<span class="error-message" style="color: #dc2626; font-size: 12px; margin-top: 4px; display: block;">' + errorMsg + ' is required</span>');
                
                isValid = false;
            }
        });
        
        // Email validation
        $form.find('input[type="email"]').each(function() {
            var $field = $(this);
            var value = $field.val().trim();
            
            if (value && !this.isValidEmail(value)) {
                $field.addClass('gmm-error');
                errors.push('Please enter a valid email address');
                $field.after('<span class="error-message" style="color: #dc2626; font-size: 12px; margin-top: 4px; display: block;">Please enter a valid email address</span>');
                isValid = false;
            }
        }.bind(this));
        
        // Show errors if any
        if (!isValid) {
            this.showNotification('Please fix the errors in the form', 'error');
            
            // Focus first error field
            var $firstError = $form.find('.gmm-error').first();
            if ($firstError.length) {
                $firstError.focus();
            }
        }
        
        return isValid;
    },
    
    isValidEmail: function(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },
    
    autoSaveIndication: function() {
        // Show that changes need to be saved
        if (!$('.gmm-save-reminder').length) {
            $('.gmm-settings-actions').prepend(
                '<div class="gmm-save-reminder" style="margin-bottom: 1rem; padding: 0.75rem; background: #fef3c7; color: #92400e; border-radius: 6px; font-size: 14px; border-left: 4px solid #f59e0b;">' +
                '<span class="dashicons dashicons-warning" style="margin-right: 8px;"></span>' +
                'You have unsaved changes. Don\'t forget to save!' +
                '</div>'
            );
        }
    },
    
    showNotification: function(message, type) {
        type = type || 'info';
        
        // Remove existing notifications
        $('.gmm-notification').remove();
        
        var $notification = $('<div class="gmm-notification gmm-notification-' + type + '">' + message + '</div>');
        
        // Add styles
        $notification.css({
            position: 'fixed',
            top: '32px',
            right: '20px',
            padding: '1rem 1.5rem',
            borderRadius: '8px',
            color: 'white',
            fontSize: '14px',
            fontWeight: '500',
            zIndex: 9999,
            minWidth: '300px',
            maxWidth: '500px',
            boxShadow: '0 4px 20px rgba(0, 0, 0, 0.15)',
            transform: 'translateX(100%)',
            transition: 'transform 0.3s ease',
            wordWrap: 'break-word'
        });
        
        // Set background color based on type
        switch (type) {
            case 'success':
                $notification.css('background', '#10b981');
                break;
            case 'error':
                $notification.css('background', '#ef4444');
                break;
            case 'warning':
                $notification.css('background', '#f59e0b');
                break;
            default:
                $notification.css('background', '#6b7280');
        }
        
        // Add close button
        $notification.append('<button class="gmm-notification-close" style="background: none; border: none; color: white; float: right; margin-left: 10px; cursor: pointer; font-size: 18px; line-height: 1; padding: 0;">&times;</button>');
        
        // Add to DOM
        $('body').append($notification);
        
        // Slide in
        setTimeout(function() {
            $notification.css('transform', 'translateX(0)');
        }, 100);
        
        // Auto-hide after 5 seconds
        var hideTimeout = setTimeout(function() {
            $notification.css('transform', 'translateX(100%)');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, 5000);
        
        // Manual close
        $notification.find('.gmm-notification-close').on('click', function() {
            clearTimeout(hideTimeout);
            $notification.css('transform', 'translateX(100%)');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        });
        
        // Pause auto-hide on hover
        $notification.on('mouseenter', function() {
            clearTimeout(hideTimeout);
        }).on('mouseleave', function() {
            hideTimeout = setTimeout(function() {
                $notification.css('transform', 'translateX(100%)');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 2000);
        });
    },
    
    // Utility function for smooth animations
    animateCSS: function($element, animationName, callback) {
        var animationEnd = 'webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend';
        
        $element.addClass('animated ' + animationName).one(animationEnd, function() {
            $element.removeClass('animated ' + animationName);
            if (typeof callback === 'function') callback();
        });
    }
};

// Global AJAX handlers
jQuery(document).ajaxStart(function() {
    // Global loading state
    jQuery('body').addClass('gmm-ajax-loading');
}).ajaxStop(function() {
    jQuery('body').removeClass('gmm-ajax-loading');
});

// Handle AJAX errors globally
jQuery(document).ajaxError(function(event, xhr, settings) {
    if (xhr.status !== 200 && settings.url && settings.url.includes('admin-ajax.php')) {
        // Don't show error for aborted requests
        if (xhr.statusText !== 'abort') {
            GMM_Admin.showNotification('Connection error. Please check your internet connection.', 'error');
        }
    }
});

// Keyboard shortcuts
jQuery(document).on('keydown', function(e) {
    // Ctrl+S or Cmd+S to save
    if ((e.ctrlKey || e.metaKey) && e.which === 83) {
        var $saveButton = jQuery('.gmm-settings-form button[type="submit"]');
        if ($saveButton.length) {
            e.preventDefault();
            $saveButton.trigger('click');
        }
    }
    
    // Escape to close notifications
    if (e.which === 27) {
        jQuery('.gmm-notification .gmm-notification-close').trigger('click');
    }
    
    // Alt+S for search focus
    if (e.altKey && e.which === 83) {
        e.preventDefault();
        jQuery('.gmm-search-box input[type="search"]').first().focus();
    }
});

// Initialize everything when DOM is ready
jQuery(document).ready(function($) {
    // Trigger initial resize
    $(window).trigger('resize');
    
    // Add smooth transitions to interactive elements
    $('.gmm-btn, .gmm-stat-card, .gmm-table tbody tr, .gmm-group-card').css({
        transition: 'all 0.2s ease'
    });
    
    // Initialize tooltips if needed
    $('[data-tooltip]').each(function() {
        var $element = $(this);
        var tooltip = $element.data('tooltip');
        
        $element.attr('title', tooltip);
    });
    
    // Add loading states to forms
    $('form').on('submit', function() {
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        
        if ($submitBtn.length && !$submitBtn.hasClass('gmm-loading')) {
            $submitBtn.addClass('gmm-loading').prop('disabled', true);
            
            // Re-enable after 10 seconds as fallback
            setTimeout(function() {
                $submitBtn.removeClass('gmm-loading').prop('disabled', false);
            }, 10000);
        }
    });
    
    // Add confirmation for destructive actions
    $('a[href*="action=delete"], button[name*="delete"], .gmm-btn-danger').on('click', function(e) {
        var $element = $(this);
        var confirmText = $element.data('confirm') || 'Are you sure you want to delete this item?';
        
        if (!confirm(confirmText)) {
            e.preventDefault();
            return false;
        }
    });
});

// Add CSS for responsive tables and additional styles
jQuery(document).ready(function($) {
    // Add responsive table CSS if not already present
    if (!$('#gmm-responsive-table-css').length) {
        $('<style id="gmm-responsive-table-css">' +
            '@media (max-width: 768px) {' +
                '.gmm-table.gmm-responsive { border: 0; }' +
                '.gmm-table.gmm-responsive thead { display: none; }' +
                '.gmm-table.gmm-responsive tbody tr { display: block; margin-bottom: 1rem; border: 1px solid #ccc; }' +
                '.gmm-table.gmm-responsive tbody td { display: block; padding: 0.5rem; border: none; border-bottom: 1px solid #eee; }' +
                '.gmm-table.gmm-responsive tbody td:before { content: attr(data-label) ": "; font-weight: bold; display: inline-block; width: 120px; }' +
                '.gmm-highlight { background: #fef08a; padding: 2px 4px; border-radius: 3px; }' +
                '.gmm-clear-search { background: #ef4444; color: white; border: none; padding: 4px 8px; margin-left: 4px; border-radius: 4px; cursor: pointer; }' +
            '}' +
        '</style>').appendTo('head');
    }
});