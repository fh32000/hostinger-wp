/**
 * Quick Pay Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Initialize WordPress Color Pickers.
        if ($.fn.wpColorPicker) {
            $('.wc-qp-color-field').wpColorPicker();
        }

        // Media Uploader for Logo.
        $('.wc-qp-upload-btn').on('click', function(e) {
            e.preventDefault();
            var targetInput = $($(this).data('target'));
            var customUploader = wp.media({
                title: 'اختر أو ارفع شعار المتجر',
                button: { text: 'استخدام هذا الشعار' },
                multiple: false
            }).on('select', function() {
                var attachment = customUploader.state().get('selection').first().toJSON();
                targetInput.val(attachment.url);
                $('#wc_qp_logo_preview').html('<img src="' + attachment.url + '" style="max-height:60px;background:#f1f5f9;padding:6px;border-radius:6px;border:1px solid #cbd5e1;" />');
            }).open();
        });

        // ─── Fields Visibility Toggles ───────────────────────
        function updateFieldsState() {
            var invoiceChecked = $('#wc_qp_show_section_invoice').is(':checked');
            var $invoiceBox    = $('#wc_qp_invoice_subfields');
            if (invoiceChecked) {
                $invoiceBox.css({ 'opacity': '1', 'pointer-events': 'auto' });
            } else {
                $invoiceBox.css({ 'opacity': '0.45', 'pointer-events': 'none' });
            }

            var customerChecked = $('#wc_qp_show_section_customer').is(':checked');
            var $customerBox    = $('#wc_qp_customer_subfields');
            if (customerChecked) {
                $customerBox.css({ 'opacity': '1', 'pointer-events': 'auto' });
            } else {
                $customerBox.css({ 'opacity': '0.45', 'pointer-events': 'none' });
            }
        }

        $('#wc_qp_show_section_invoice, #wc_qp_show_section_customer').on('change', updateFieldsState);
        updateFieldsState();

        // ─── Gateways Selection & Counter ────────────────────
        function updateGatewaysCounter() {
            var total = $('.wc-qp-gw-checkbox').length;
            var checked = $('.wc-qp-gw-checkbox:checked').length;
            var counterText = 'تم تحديد ' + checked + ' من ' + total + ' بوابة';
            $('#wc_qp_gateway_counter').text(counterText);
        }

        $('.wc-qp-gw-checkbox').on('change', updateGatewaysCounter);

        $('#wc_qp_btn_select_all_gws').on('click', function(e) {
            e.preventDefault();
            $('.wc-qp-gw-checkbox').prop('checked', true).trigger('change');
        });

        $('#wc_qp_btn_deselect_all_gws').on('click', function(e) {
            e.preventDefault();
            $('.wc-qp-gw-checkbox').prop('checked', false).trigger('change');
        });

        updateGatewaysCounter();

        // ─── Pay Link Generator ──────────────────────────────
        function updateGeneratedUrl() {
            var base         = wcQuickPayAdmin.base_pay_url;
            var amount       = $.trim($('#gen_amount').val());
            var invoice      = $.trim($('#gen_invoice').val());
            var name         = $.trim($('#gen_name').val());
            var phone        = $.trim($('#gen_phone').val());
            var notes        = $.trim($('#gen_notes').val());
            var gateway      = $.trim($('#gen_gateway').val());
            var lockAmount   = $('#gen_lock_amount').is(':checked');
            var hideCustomer = $('#gen_hide_customer').is(':checked');
            var hideInvoice  = $('#gen_hide_invoice').is(':checked');
            var hideSupport  = $('#gen_hide_support').is(':checked');

            var params = [];
            if (amount)  params.push('amount=' + encodeURIComponent(amount));
            if (invoice) params.push('invoice=' + encodeURIComponent(invoice));
            if (name)    params.push('name=' + encodeURIComponent(name));
            if (phone)   params.push('phone=' + encodeURIComponent(phone));
            if (notes)   params.push('notes=' + encodeURIComponent(notes));
            if (gateway) params.push('gateway=' + encodeURIComponent(gateway));

            if (amount && lockAmount) {
                params.push('lock=1');
            }
            if (hideCustomer) {
                params.push('hide_customer=1');
            }
            if (hideInvoice) {
                params.push('hide_invoice=1');
            }
            if (hideSupport) {
                params.push('hide_support=1');
            }

            var finalUrl = base;
            if (params.length > 0) {
                finalUrl += (finalUrl.indexOf('?') !== -1 ? '&' : '?') + params.join('&');
            }

            $('#gen_result_url').val(finalUrl);

            // WhatsApp Share link
            var waBtn = $('#wc_qp_whatsapp_share_btn');
            if (phone || amount) {
                var cleanPhone = phone.replace(/[^0-9]/g, '');
                var message = 'مرحباً ' + (name ? name : '') + '، رابط سداد الفاتورة ' + (invoice ? '#' + invoice : '') + ' بمبلغ ' + (amount ? amount : '') + ': ' + finalUrl;
                var waUrl = 'https://api.whatsapp.com/send?' + (cleanPhone ? 'phone=' + cleanPhone + '&' : '') + 'text=' + encodeURIComponent(message);
                waBtn.attr('href', waUrl).show();
            } else {
                waBtn.hide();
            }
        }

        $('#gen_amount, #gen_invoice, #gen_name, #gen_phone, #gen_notes, #gen_gateway').on('input change', updateGeneratedUrl);
        $('#gen_lock_amount, #gen_hide_customer, #gen_hide_invoice, #gen_hide_support').on('change', updateGeneratedUrl);

        // Copy link to clipboard.
        $('#wc_qp_copy_link_btn').on('click', function(e) {
            e.preventDefault();
            var copyText = document.getElementById('gen_result_url');
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value);

            var $btn = $(this);
            var originalText = $btn.text();
            $btn.text(wcQuickPayAdmin.copied_text);
            setTimeout(function() {
                $btn.text(originalText);
            }, 2500);
        });

    });

})(jQuery);
