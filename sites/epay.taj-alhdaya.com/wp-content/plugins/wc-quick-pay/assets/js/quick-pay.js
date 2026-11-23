/**
 * Quick Pay Frontend JavaScript
 * Handles dynamic amount calculation, preset pills, localStorage retention, and AJAX payment flow.
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var $form        = $('#wc-quick-pay-form');
        var $amountInput = $('#wc_qp_amount');
        var $presets     = $('.wc-qp-preset-btn');
        var $dynBtnAmt   = $('#wc-qp-btn-dynamic-amount');
        var $submitBtn   = $('#wc-qp-submit-btn');
        var $spinner     = $submitBtn.find('.wc-qp-spinner');
        var $btnText     = $submitBtn.find('.wc-qp-btn-text');
        var $notice      = $('#wc-qp-notice');

        // Clean & decode currency symbol (prevent any raw &#x... entities from ever displaying)
        var cleanCurrency = wcQuickPayParams.currency || '';
        if (cleanCurrency.indexOf('&') !== -1) {
            var tempDiv = document.createElement('div');
            tempDiv.innerHTML = cleanCurrency;
            cleanCurrency = tempDiv.textContent || tempDiv.innerText || cleanCurrency;
        }

        // Language Switcher: store cookie on toggle click
        $('#wc-qp-lang-btn').on('click', function(e) {
            var href = $(this).attr('href') || '';
            var targetLang = href.indexOf('lang=en') !== -1 ? 'en' : 'ar';
            document.cookie = 'wc_qp_lang=' + targetLang + ';path=/;max-age=' + (30*86400);
        });

        // Auto-detect browser/device language on first visit if not explicitly chosen
        try {
            if (!document.cookie.match(/wc_qp_lang=/) && !window.location.search.match(/[?&]lang=/)) {
                var userLang = (navigator.language || navigator.userLanguage || '').toLowerCase();
                var currentLang = wcQuickPayParams.lang || 'ar';
                if (userLang.startsWith('en') && currentLang !== 'en') {
                    document.cookie = 'wc_qp_lang=en;path=/;max-age=' + (30*86400);
                    var newUrl = window.location.href + (window.location.search ? '&' : '?') + 'lang=en';
                    window.location.replace(newUrl);
                    return;
                }
            }
        } catch(e) {}

        // Restore saved customer data from localStorage if fields are present and empty.
        try {
            var savedName  = localStorage.getItem('wc_qp_name');
            var savedEmail = localStorage.getItem('wc_qp_email');
            var savedPhone = localStorage.getItem('wc_qp_phone');

            if (savedName && $('#wc_qp_name').length && !$('#wc_qp_name').val()) {
                $('#wc_qp_name').val(savedName);
            }
            if (savedEmail && $('#wc_qp_email').length && !$('#wc_qp_email').val()) {
                $('#wc_qp_email').val(savedEmail);
            }
            if (savedPhone && $('#wc_qp_phone').length && !$('#wc_qp_phone').val()) {
                $('#wc_qp_phone').val(savedPhone);
            }
        } catch (e) {
            // LocalStorage might be disabled in private mode.
        }

        // Save fields to localStorage on change if present.
        $('#wc_qp_name').on('input change', function() {
            try { localStorage.setItem('wc_qp_name', $(this).val()); } catch (e) {}
        });
        $('#wc_qp_email').on('input change', function() {
            try { localStorage.setItem('wc_qp_email', $(this).val()); } catch (e) {}
        });
        $('#wc_qp_phone').on('input change', function() {
            try { localStorage.setItem('wc_qp_phone', $(this).val()); } catch (e) {}
        });

        // Update button text when amount changes.
        function updateAmountDisplay() {
            var val = parseFloat($amountInput.val());
            if (!isNaN(val) && val > 0) {
                var formatted = val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                $dynBtnAmt.text('(' + formatted + ' ' + cleanCurrency + ')');
            } else {
                $dynBtnAmt.text('');
            }

            // Sync preset active state
            $presets.each(function() {
                var pVal = parseFloat($(this).data('amount'));
                if (pVal === val) {
                    $(this).addClass('is-active');
                } else {
                    $(this).removeClass('is-active');
                }
            });
        }

        $amountInput.on('input change', updateAmountDisplay);
        updateAmountDisplay();

        // Preset button click.
        $presets.on('click', function(e) {
            e.preventDefault();
            if ($amountInput.prop('readonly')) {
                return;
            }
            var amt = $(this).data('amount');
            $amountInput.val(amt).trigger('input');
        });

        // Gateway Card selection.
        $('.wc-qp-gateway-item').on('click', function() {
            $('.wc-qp-gateway-item').removeClass('is-selected');
            $(this).addClass('is-selected');
            $(this).find('input[type="radio"]').prop('checked', true);
        });

        // Notice helper.
        function showNotice(msg, type) {
            $notice.removeClass('wc-qp-notice-error wc-qp-notice-success')
                   .addClass(type === 'success' ? 'wc-qp-notice-success' : 'wc-qp-notice-error')
                   .html(msg)
                   .fadeIn(200);

            $('html, body').animate({
                scrollTop: $notice.offset().top - 40
            }, 300);
        }

        function hideNotice() {
            $notice.hide().empty();
        }

        // Form submission.
        $form.on('submit', function(e) {
            e.preventDefault();
            hideNotice();

            // 1. Amount validation.
            var amount = parseFloat($amountInput.val());
            if (isNaN(amount) || amount <= 0) {
                showNotice(wcQuickPayParams.i18n.invalid_amount, 'error');
                $amountInput.focus();
                return;
            }

            // 2. Invoice validation (only if present and required).
            var invoiceInput = $('#wc_qp_invoice');
            if (invoiceInput.length && invoiceInput.prop('required') && !$.trim(invoiceInput.val())) {
                var invMsg = (wcQuickPayParams.i18n && wcQuickPayParams.i18n.invalid_invoice) ? wcQuickPayParams.i18n.invalid_invoice : 'يرجى إدخال رقم الفاتورة أو المعاملة المرجعية.';
                showNotice(invMsg, 'error');
                invoiceInput.focus();
                return;
            }

            // 3. Customer validation (only if present and required).
            var nameInput = $('#wc_qp_name');
            if (nameInput.length && nameInput.prop('required') && !$.trim(nameInput.val())) {
                showNotice(wcQuickPayParams.i18n.invalid_name, 'error');
                nameInput.focus();
                return;
            }

            var emailInput = $('#wc_qp_email');
            var emailVal   = emailInput.length ? $.trim(emailInput.val()) : '';
            if (emailInput.length) {
                if (emailInput.prop('required') && !emailVal) {
                    showNotice(wcQuickPayParams.i18n.invalid_email, 'error');
                    emailInput.focus();
                    return;
                }
                if (emailVal.length > 0) {
                    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(emailVal)) {
                        showNotice(wcQuickPayParams.i18n.invalid_email, 'error');
                        emailInput.focus();
                        return;
                    }
                }
            }

            var phoneInput = $('#wc_qp_phone');
            if (phoneInput.length && phoneInput.prop('required') && !$.trim(phoneInput.val())) {
                showNotice(wcQuickPayParams.i18n.invalid_phone, 'error');
                phoneInput.focus();
                return;
            }

            // 4. Gateway selection.
            var gateway = $('input[name="gateway_id"]:checked').val() || $('input[type="hidden"][name="gateway_id"]').val();
            if (!gateway) {
                showNotice(wcQuickPayParams.i18n.select_gateway, 'error');
                return;
            }

            // Set Loading state.
            $submitBtn.prop('disabled', true);
            $spinner.show();
            var originalBtnHtml = $btnText.html();
            $btnText.text(wcQuickPayParams.i18n.processing);

            var formData = {
                action:     'wc_quick_pay_submit',
                nonce:      wcQuickPayParams.nonce,
                amount:     amount,
                name:       nameInput.length ? nameInput.val() : '',
                email:      emailVal,
                phone:      phoneInput.length ? phoneInput.val() : '',
                invoice:    invoiceInput.length ? invoiceInput.val() : '',
                notes:      $('#wc_qp_notes').length ? $('#wc_qp_notes').val() : '',
                gateway_id: gateway
            };

            $.ajax({
                url:      wcQuickPayParams.ajax_url,
                type:     'POST',
                data:     formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data && response.data.redirect) {
                        showNotice(response.data.message || wcQuickPayParams.i18n.processing, 'success');
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 400);
                    } else {
                        var errMsg = (response.data && response.data.message) ? response.data.message : wcQuickPayParams.i18n.general_error;
                        showNotice(errMsg, 'error');
                        $submitBtn.prop('disabled', false);
                        $spinner.hide();
                        $btnText.html(originalBtnHtml);
                    }
                },
                error: function(xhr, status, error) {
                    var errorMsg = wcQuickPayParams.i18n.general_error;
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    }
                    showNotice(errorMsg, 'error');
                    $submitBtn.prop('disabled', false);
                    $spinner.hide();
                    $btnText.html(originalBtnHtml);
                }
            });
        });

    });

})(jQuery);
