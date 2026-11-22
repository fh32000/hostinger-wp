# Hostinger WordPress Multi-Site Ecosystem & In-House Plugins

مستودع موحد لإدارة وتطوير مواقع ووردبريس ومتاجر ووكومرس على استضافة Hostinger، مع حزم الإضافات البرمجية المطورة داخلياً، سكريبتات الأتمتة والمزامنة، وأدوات اختبار توافق محرك WebKit وسفاري على iOS.

---

## 📁 هيكلية المستودع (Repository Structure)

تم تنظيم المستودع وفق معمارية متعددة المواقع (Multi-Site Monorepo) بحيث يملك كل نطاق مجلداً خاصاً به، مع عزل الإضافات المطورة داخلياً والسكريبتات:

```text
├── sites/                                # مجلد مشاريع ووردبريس لكل موقع ونطاق
│   ├── tajalhdaya.roseise.com/           # مشروع متجر تاج الهدايا (نواة ووردبريس والقوالب والإضافات المخصصة)
│   └── muskise.com/                      # إعدادات، إصلاحات، وتخصيصات متجر مسكيس
│
├── plugins/                              # الإضافات البرمجية المطورة داخلياً (In-House Custom Plugins)
│   ├── wc-quick-pay/                     # إضافة صفحة وخيار الدفع المتكرر السريع لووكومرس
│   ├── wc-ahli-mpgs/                     # بوابة الدفع الإلكتروني للبنك الأهلي التجاري (MPGS)
│   ├── taj-sync/                         # أداة مزامنة ونقل بيانات متجر تاج الهدايا
│   ├── muskise-safari-fix/               # إضافة إصلاح انهيار وتكدس حقول الدفع على متصفح سفاري وآيفون
│   ├── wc-quick-pay-v1.0.0.zip           # حزمة التثبيت الجاهزة للإضافة
│   └── wc-ahli-mpgs-v1.0.0.zip           # حزمة التثبيت الجاهزة لبوابة الأهلي
│
├── scripts/                              # سكريبتات الأتمتة، الترحيل، وفحص سفاري
│   ├── deploy_production.py              # سكريبت النشر والترحيل الآلي للإنتاج
│   ├── import_taj_data.php               # استيراد البيانات والمنتجات
│   ├── scrape_taj.py                     # سكريبت استخراج وهيكلة البيانات
│   ├── audit-webkit-layout.py            # فحص آلي رياضي لعناصر الواجهة على WebKit (iPhone 15 Pro)
│   ├── safari_preview.py                 # تطبيق معاينة وفحص تفاعلي بمحرك سفاري WebKitGTK 6.0
│   ├── preview-safari.sh                 # مشغل سريع للمعاينة في بيئة لينكس
│   └── audit-safari-css.mjs              # مدقق الكود المصدري الثابت لعيوب سفاري
│
├── .agents/skills/                       # مهارات الوكلاء الأذكياء
│   └── frontend-safari-testing/          # مهارة فحص واختبار الواجهات الأمامية وتوافق سفاري (iOS & Mac)
│
├── docker-compose.yml                    # بيئة التشغيل والتطوير المحلية (WordPress + MariaDB + phpMyAdmin)
├── uploads.ini                           # إعدادات رفع الملفات لـ PHP (256MB)
├── init-db/                              # مجلد استيراد قاعدة البيانات الأولي عند بدء دوكر
└── README.md
```

---

## 🚀 تشغيل بيئة التطوير المحلية (Local Development)

### 1. المتطلبات
- Docker و Docker Compose
- متصفح ويب

### 2. تجهيز قاعدة البيانات
> [!NOTE]
> لا يتم رفع ملفات قواعد البيانات (`.sql`) أو ملفات الرفع (`uploads/`) إلى المستودع حفاظاً على الأمان والحجم.

1. حمّل ملف قاعدة البيانات الخاص بالموقع (مثلاً `database_taj_alhdaya_production.sql`).
2. ضعه داخل مجلد `init-db/` باسم `init.sql`:
   ```bash
   cp database_taj_alhdaya_production.sql init-db/init.sql
   ```
3. شغّل حاويات الدوكر:
   ```bash
   docker compose up -d
   ```
4. الروابط المحلية:
   - **الموقع المحلي:** [http://localhost:8085](http://localhost:8085)
   - **لوحة التحكم phpMyAdmin:** [http://localhost:8086](http://localhost:8086) (المستخدم: `root` / كلمة المرور: `root_password`)
   - **خادم قاعدة البيانات MariaDB:** المنفذ `33068`

---

## 🔌 تثبيت الإضافات المخصصة والطرف الثالث

1. **الإضافات المطورة داخلياً:**
   - موجودة دائماً في مجلد `plugins/`.
   - يمكن تثبيتها ورفعها كملفات ZIP جاهزة أو نسخ المجلد مباشرة إلى `wp-content/plugins/`.

2. **إضافات الطرف الثالث المعتمدة (Third-Party Plugins):**
   - يتم تثبيتها وتحديثها عبر لوحة تحكم ووردبريس أو عبر WP-CLI:
   ```bash
   wp plugin install woocommerce elementor contact-form-7 litespeed-cache --activate
   ```

---

## 📱 اختبار وفحص توافق سفاري وآيفون (Safari / WebKit Testing)

المشروع مزود بمهارة وأدوات اختبار مخصصة لمحاكاة وفحص محرك WebKit الخاص بـ iOS Safari:

1. **المعاينة البصرية بمحرك Safari الأصلي على لينكس:**
   ```bash
   ./scripts/preview-safari.sh http://localhost:8085 --mobile
   # أو لفحص أي موقع مباشر:
   ./scripts/preview-safari.sh https://muskise.com --mobile
   ```

2. **الفحص الرياضي التلقائي لمحاذاة العناصر:**
   ```bash
   python3 scripts/audit-webkit-layout.py https://muskise.com/checkout/ --mobile
   ```

---

## 🛠️ تفاصيل حل مشكلة عرض متجر مسكيس (Muskise Safari RTL Fix)

### المشكلة:
في متصفح Safari على هواتف iPhone في المواقع العربية (`dir="rtl"`):
محرك WebKit يُسيء حساب أبعاد شبكة CSS (`grid 1fr`) أو عناصر Flexbox داخل بلوك إنهاء الطلب لووكومرس (`wp-block-woocommerce-checkout`) وحاوية القالب (`.the_content_wrapper`)، مما يؤدي إلى انهيار وانكماش حقول الإدخال لتصبح بعرض ضيق جداً (حوالي 40 بكسل) في أقصى اليمين وظهور مساحة بيضاء فارغة في المنتصف واليسار.

### الحل الجذري:
1. تم إنشاء إضافة مخصصة مستقلة `plugins/muskise-safari-fix/` تقوم بحقن كود CSS عالي الأولوية لضبط الحاويات:
   - إلزام `.the_content_wrapper` بالتمدد بعرض 100% وإلغاء `float: right`.
   - تحويل التخطيط في الشاشات الصغيرة (< 960px) إلى عمود كامل `flex-direction: column; width: 100%`.
   - إعطاء جميع حقول العنوان `.wc-block-components-address-form .wc-block-components-text-input` عرض `width: 100%; flex: 1 1 100%`.
   - تطبيق `-webkit-appearance: none;` ومنع التكبير التلقائي في آيفون (`font-size: 16px`).
2. تم وضع كود الـ CSS في `sites/muskise.com/custom-css-fix.css` لإمكانية وضعه يدوياً عبر لوحة التحكم (مظهر -> تخصيص -> تنسيقات CSS إضافية) أو تفعيله عبر الإضافة.

---

## 💳 إضافة الدفع السريع والروابط المباشرة (Quick Pay & Pay Links)

تم تطوير وتفعيل إضافة `plugins/wc-quick-pay/` لتوفير محطة دفع وسداد فواتير سريعة ومستقلة:

### 1. المميزات:
- صفحة دفع منعزلة وخالية من عناصر تشتيت القوالب (`/pay/`).
- توليد فواتير وطلبات ووكومرس فورية برقم مرجعي ومبلغ حر أو مبالغ محددة مسبقاً (50, 100, 250, 500, 1000 ر.س).
- دعم روابط الدفع المسبقة بالمعطيات (Query Parameters) لإرسالها للعملاء مباشرة عبر الواتساب:
  ```text
  https://muskise.com/pay/?amount=150&invoice=INV-102&name=اسم_العميل&phone=05xxxxxxxx
  ```
- تكامل تام مع بوابة **Paymob** في السعودية (`paymob-main`) وتمرير تلقائي لمعرّفات الربط (`36409` للبطاقات ومدى، `37681` لـ Apple Pay، `37682` لـ MIGS).
- توثيق كامل للطلبات في لوحة تحكم ووردبريس مع شارة "دفع سريع" وبيانات الفاتورة.

### 2. النشر على السيرفر عبر Git:
- يتم عزل الإضافة وتحديثها على السيرفر مباشرة عبر فرع `plugin/wc-quick-pay`:
  ```bash
  git subtree split --prefix=plugins/wc-quick-pay -b plugin/wc-quick-pay-temp
  git push origin plugin/wc-quick-pay-temp:plugin/wc-quick-pay --force
  ```
