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

        // ─── Pay Link Generator ──────────────────────────────
        function updateGeneratedUrl() {
            var base    = wcQuickPayAdmin.base_pay_url;
            var amount  = $.trim($('#gen_amount').val());
            var invoice = $.trim($('#gen_invoice').val());
            var name    = $.trim($('#gen_name').val());
            var phone   = $.trim($('#gen_phone').val());
            var notes   = $.trim($('#gen_notes').val());

            var params = [];
            if (amount)  params.push('amount=' + encodeURIComponent(amount));
            if (invoice) params.push('invoice=' + encodeURIComponent(invoice));
            if (name)    params.push('name=' + encodeURIComponent(name));
            if (phone)   params.push('phone=' + encodeURIComponent(phone));
            if (notes)   params.push('notes=' + encodeURIComponent(notes));

            var finalUrl = base;
            if (params.length > 0) {
                finalUrl += (finalUrl.indexOf('?') !== -1 ? '&' : '?') + params.join('&');
            }

            $('#gen_result_url').val(finalUrl);

            // WhatsApp Share link
            var waBtn = $('#wc_qp_whatsapp_share_btn');
            if (phone || amount) {
                var cleanPhone = phone.replace(/[^0-9]/g, '');
                var message = 'مرحباً ' + (name ? name : '') + '، رابط سداد الفاتورة رقم ' + (invoice ? '#' + invoice : '') + ' بمبلغ ' + (amount ? amount : '') + ' ريال: ' + finalUrl;
                var waUrl = 'https://api.whatsapp.com/send?' + (cleanPhone ? 'phone=' + cleanPhone + '&' : '') + 'text=' + encodeURIComponent(message);
                waBtn.attr('href', waUrl).show();
            } else {
                waBtn.hide();
            }
        }

        $('#gen_amount, #gen_invoice, #gen_name, #gen_phone, #gen_notes').on('input change', updateGeneratedUrl);

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
