=== WooCommerce Ahli Bank (MPGS) Payment Gateway ===
Contributors: yourname
Tags: woocommerce, payment, gateway, mpgs, mastercard, ahli, saudi arabia, saudi, snb
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

ربط بوابة دفع البنك الأهلي السعودي (MPGS) مع WooCommerce عبر طريقة Hosted Checkout.

== Description ==

هذا الملحق يتيح لمتجرك على WooCommerce قبول المدفوعات عبر بوابة البنك الأهلي السعودي المبنية على نظام Mastercard Payment Gateway Services (MPGS).

**الميزات:**
- تكامل كامل مع WooCommerce
- طريقة Hosted Checkout (العميل يُحوَّل لصفحة الدفع الآمنة للبنك)
- دعم استرداد المدفوعات (Refunds) عبر لوحة تحكم WooCommerce
- وضع الاختبار لتجربة البوابة قبل الإطلاق
- سجل تنقيح كامل لمتابعة عمليات الدفع

**متطلبات:**
- حساب تاجر نشط مع البنك الأهلي السعودي
- Merchant ID وAPI Password من لوحة تحكم MPGS

== Installation ==

1. ارفع مجلد الملحق `wc-ahli-mpgs` إلى `/wp-content/plugins/`
2. فعّل الملحق من لوحة تحكم ووردبريس
3. انتقل إلى WooCommerce → الإعدادات → الدفع → بوابة الأهلي (MPGS)
4. أدخل Merchant ID وAPI Password

== Changelog ==

= 1.0.1 =
* إضافة الأيقونة الرسمية لبوابة البنك الأهلي (SNB / MPGS) داخل مجلد assets/images.
* تحسين دالة get_icon() لإظهار الشعار بأبعاد ملائمة ومظهر متناسق مع مختلف القوالب ونماذج الدفع السريع.
* تحميل ملف CSS في صفحات الدفع (Checkout).

= 1.0.0 =
* الإصدار الأول — Hosted Checkout، دعم الاسترداد، وضع الاختبار.

