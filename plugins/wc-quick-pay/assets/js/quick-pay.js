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

        // Restore saved customer data from localStorage if fields are empty.
        try {
            var savedName  = localStorage.getItem('wc_qp_name');
            var savedEmail = localStorage.getItem('wc_qp_email');
            var savedPhone = localStorage.getItem('wc_qp_phone');

            if (savedName && !$('#wc_qp_name').val()) {
                $('#wc_qp_name').val(savedName);
            }
            if (savedEmail && !$('#wc_qp_email').val()) {
                $('#wc_qp_email').val(savedEmail);
            }
            if (savedPhone && !$('#wc_qp_phone').val()) {
                $('#wc_qp_phone').val(savedPhone);
            }
        } catch (e) {
            // LocalStorage might be disabled in private mode.
        }

        // Save fields to localStorage on change.
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
                $dynBtnAmt.text('(' + formatted + ' ' + wcQuickPayParams.currency + ')');
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

        // Preset button click.
        $presets.on('click', function(e) {
            e.preventDefault();
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

            var amount = parseFloat($amountInput.val());
            if (isNaN(amount) || amount <= 0) {
                showNotice(wcQuickPayParams.i18n.invalid_amount, 'error');
                $amountInput.focus();
                return;
            }

            var nameInput = $('#wc_qp_name');
            if (nameInput.prop('required') && !$.trim(nameInput.val())) {
                showNotice(wcQuickPayParams.i18n.invalid_name, 'error');
                nameInput.focus();
                return;
            }

            var emailInput = $('#wc_qp_email');
            var emailVal   = $.trim(emailInput.val());
            if (emailInput.prop('required') || emailVal.length > 0) {
                var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(emailVal)) {
                    showNotice(wcQuickPayParams.i18n.invalid_email, 'error');
                    emailInput.focus();
                    return;
                }
            }

            var phoneInput = $('#wc_qp_phone');
            if (phoneInput.prop('required') && !$.trim(phoneInput.val())) {
                showNotice(wcQuickPayParams.i18n.invalid_phone, 'error');
                phoneInput.focus();
                return;
            }

            var gateway = $('input[name="gateway_id"]:checked').val();
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
                name:       nameInput.val(),
                email:      emailVal,
                phone:      phoneInput.val(),
                invoice:    $('#wc_qp_invoice').val(),
                notes:      $('#wc_qp_notes').val(),
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
                        }, 500);
                    } else {
                        var errMsg = (response.data && response.data.message) ? response.data.message : wcQuickPayParams.i18n.general_error;
                        showNotice(errMsg, 'error');
                        $submitBtn.prop('disabled', false);
                        $spinner.hide();
                        $btnText.html(originalBtnHtml);
                    }
                },
                error: function(xhr, status, error) {
                    var errMsg = wcQuickPayParams.i18n.general_error;
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errMsg = xhr.responseJSON.data.message;
                    }
                    showNotice(errMsg, 'error');
                    $submitBtn.prop('disabled', false);
                    $spinner.hide();
                    $btnText.html(originalBtnHtml);
                }
            });
        });

    });

})(jQuery);
