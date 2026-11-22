---
name: frontend-safari-testing
description: Comprehensive QA and testing skill for Safia frontend projects (safia-frontend, safia-landing-pages, safia-admin). Enforces automated verification of any CSS, HTML, Vue components, and layout changes against known Safari (WebKit) pitfalls (iOS & macOS), runs automated audits, and provides developers with commands to preview and inspect changes in native WebKit.
---

# مهارة فحص واختبار الواجهات الأمامية وتوافق سفاري (Frontend QA & Safari Testing Skill)

هذه المهارة تحدد **بروتوكول الفحص والاختبار الإلزامي** للوكلاء الأذكياء (AI Agents) والمطورين عند فحص، أو تعديل، أو إنشاء أي كود للواجهة الأمامية (Vue Components, SCSS/CSS, Tailwind classes, Layouts, Grid, Flexbox) في مشاريع صفية (`safia-frontend`, `safia-landing-pages`, `safia-admin`).

الهدف الأساسي هو:
1. **الوقاية الجذرية من عيوب محرك WebKit (Safari على iOS و macOS)** خاصة في التخطيطات العربية (`dir="rtl"`).
2. **تجنب الحلول الترقيعية والشوائب البرمجية (CSS Spam):** منع إضافة كلاسات وخواص عشوائية (`overflow-x: hidden` على كل الحاويات، طبقات تغليف غير ضرورية) تُثقل الكود دون معالجة السبب الجذري.
3. **سرعة دورة التطوير والفحص المباشر:** منع إضاعة الوقت في عمليات بناء الإنتاج البطيئة (`build`) أثناء الفحص، والاعتماد التام على خادم التطوير الحي `npm run dev` مع أدوات القياس الرياضية الدقيقة للـ DOM.

---

## 1. الدروس المستفادة والأخطاء الشائعة (What Went Wrong & Core Principles)

### ❌ الأسلوب الخاطئ (Anti-Patterns):
1. **الانتظار لبناء الإنتاج (`npm run build`):**
   بناء Nuxt الكامل يقوم بـ Prerendering وتوليد حزم الإنتاج ويستغرق من 5 إلى 10 دقائق ويتوقف أحياناً على اتصالات خارجية، بينما سلوك محرك WebKit في معالجة الـ CSS والـ DOM **متطابق بنسبة 100% بين `dev` و `build`**.
2. **الحلول الترقيعية وتسميم الكود (CSS Band-Aid Spam):**
   وضع `overflow-x: hidden` و `contain: layout` وحاويات إضافية على كل عنصر أباً وجداً؛ هذا يخفي الأعراض سطحياً أحياناً على الديسكتوب، لكنه لا يحل المشكلة الجوهرية على شاشات الجوال ويثقل الكود ويعقد الصيانة.
3. **الفحص الأعمى ولقطات الشاشة غير المكتملة (Skeleton / Loading Trap):**
   التقاط صورة أثناء مرحلة تحميل البيانات أو الهيكل العظمي (Skeleton/Splash) والادعاء بأن المشكلة حُلّت، بينما العناصر الحقيقية لم تُرسم بعد.
4. **الرفع المباشر دون فحص أبعاد الـ DOM رياضياً:**
   عمل Commit أو Push قبل قياس أبعاد العناصر (`scrollWidth` و `getBoundingClientRect`) والتأكد من عدم وجود أي عنصر يخرج خارج حدود الشاشة.

### ✅ الأسلوب الهندسي الصحيح (The Engineering Standard):
1. **التشخيص الرياضي الدقيق للـ DOM:**
   لا تخمن أبداً سبب التمدد. استخدم سكريبت الفحص الآلي لتحديد العنصر الدقيق بالبكسل الذي يتجاوز عرضه عرض الشاشة أو يمتد لليسار.
2. **الحل في المصدر (Fix at the Root):**
   إصلاح قاعدة التخطيط الأساسية (مثل تحويل `grid 1fr` إلى `minmax(0, 1fr)` أو تحويله إلى `flex-direction: column` في الجوال) بدلاً من تغليف الصفحة بحواجز إخفاء.
3. **الاعتماد التام على `npm run dev`:**
   تعديل الكود ومعاينته فورياً بفضل الـ Hot Module Replacement (HMR) في أجزاء من الثانية.

---

## 2. بروتوكول سير العمل الإلزامي للوكيل (Step-by-Step QA Protocol)

عند إجراء أي تعديل أو إصلاح واجهة في سفاري، **يجب اتباع الخطوات الخمس التالية بالترتيب الصارم**:

### الخطوة 1: فحص الكود ضد قائمة العيوب الشائعة (Static Audit)
راجع التعديلات وتأكد من خلوها من الأخطاء النمطية الموضحة في جدول القسم 3، وشغّل الفحص الثابت:
```bash
node scripts/audit-safari-css.mjs <مسار_الملف_أو_المجلد>
```

### الخطوة 2: تشغيل خادم التطوير السريع (`npm run dev`)
> [!CAUTION]
> **يُمنع منعاً باتاً تشغيل أو انتظار `npm run build` أثناء عملية الفحص والتعديل!**

تأكد من تشغيل خادم التطوير في مجلد المشروع:
```bash
cd safia-frontend && npm run dev
```
*خادم التطوير يعمل على `http://localhost:3000` ويتيح التعديل الفوري بدون أي إعادة بناء.*

### الخطوة 3: الفحص الرياضي الآلي لمحرك WebKit (`audit-webkit-layout.py`)
شغّل أداة فحص التخطيط الآلية بمحرك WebKit الأصلي بمقاس الآيفون (393px):
```bash
python3 scripts/audit-webkit-layout.py http://localhost:3000/مسار_الصفحة --mobile
```
* **معيار النجاح:** يجب أن تُرجع الأداة:
  * `"hasHorizontalOverflow": false`
  * `"overflowCount": 0`
  * تطابق عرض المستند `docScrollWidth` مع عرض الشاشة `width`.
* إذا أظهرت الأداة أي عنصر متجاوز (`overflowingElements`)، اقرأ الكلاس والأبعاد المعروضة في التقرير واذهب فوراً للعنصر المسبب لإصلاحه.

### الخطوة 4: التحقق من اكتمال تحميل الصفحة (No Skeleton Screenshots)
عند التقاط لقطات شاشة أو فحص الصفحة بصرياً، تأكد دائماً أن الصفحة:
1. انتهت من جلب البيانات من الـ API (`v-if="loaded"` أو اختفاء الـ skeleton تماماً).
2. تم رسم المحتوى الفعلي (النصوص، الكروت، البطاقات، الأزرار).

### الخطوة 5: بروتوكول أمان Git والنشر (Git Safety Protocol)
> [!IMPORTANT]
> **قواعد Git الصارمة:**
> 1. لا تقم بعمل Commit لأي حل تجريبي قبل التحقق بنجاح في الخطوة 3 والخطوة 4.
> 2. لا ترفع (`git push`) مباشرة إلى `main` أبداً.
> 3. مسار النشر المعتمد لبيئة الاختبار:
>    * تثبيت التعديل محلياً في فرع العمل أو `main` محلياً.
>    * دمج التعديل في `develop` ثم في `testing`.
>    * رفع فرع `testing` فقط (`git push origin testing`) ليتم بناؤه واختباره على سيرفر الاختبار: `https://testing-frontend.safiatahar.com`.
>    * إبقاء `main` بدون أي push حتى يقرر المستخدم أو المطور الاعتماد النهائي للإنتاج.

---

## 3. كتالوج عيوب WebKit وسفاري والحلول الجذرية المعتمدة

| الخاصية / المشكلة | السلوك في Safari / WebKit | السبب الجذري والحل الهندسي المعتمد |
| :--- | :--- | :--- |
| **تمدد شبكة CSS (`grid 1fr`) في RTL** | تخرج عناصر الكروت والبطاقات لليسار خارج حدود شاشة الجوال (Horizontal blowout). | **السبب:** سفاري يعتبر `1fr` اختصاراً لـ `minmax(auto, 1fr)`، وخاصية `auto` تتمدد بحجم المحتوى الداخلي (`min-content`). في الصفحات العربية (`dir="rtl"`) يتم تثبيت اليمين وتُطرد الزيادة لليسار.<br>**الحل:** استخدام `minmax(0, 1fr)` صراحة في `grid-template-columns`، أو التحويل في الجوال (`@media (max-width: 900px)`) إلى `display: flex; flex-direction: column; width: 100%; min-width: 0;`. |
| **انكماش عناصر Flexbox (`min-width: auto`)** | الكروت أو الصفوف ترفض الانكماش وتتجاوز الشاشة عند وجود نصوص طويلة أو أيقونات. | **السبب:** العناصر الأبناء داخل Flexbox تملك افتراضياً `min-width: auto` في WebKit.<br>**الحل:** إضافة `min-width: 0` دائماً على عناصر Flex الفرعية، مع التأكد من وجود `box-sizing: border-box`. |
| **حسابات `width: 100%` مع البادينغ في RTL** | الكرت يمتد بمسافة البادينغ خارج الشاشة من اليسار. | **السبب:** في حال غياب `box-sizing: border-box`، يتم جمع الـ padding مع الـ 100%، وتظهر الزيادة في الجهة اليسرى.<br>**الحل:** التأكد من إلزام `box-sizing: border-box; width: 100%; max-width: 100%;` على الكروت. |
| **ارتفاع الشاشة الكامل `100vh`** | شريط عناوين Safari على iOS يغطي أسفل المحتوى أو يسبب تمرير رأسي مزعج. | **الحل:** استخدام `100dvh` دائماً أو كلاسات المشروع `.h-screen` / `min-h-screen-dvh`. |
| **تأثير البلور والزجاج `backdrop-filter`** | يظهر كخلفية رمادية معتمة أو شفافة بالكامل دون أي تأثير ضبابي. | **الحل:** إضافة `-webkit-backdrop-filter: blur(...)` دائماً قبل أو مع `backdrop-filter`. |
| **مشغلات الفيديو `<video>`** | يفتح الفيديو في وضع ملء الشاشة إجبارياً على iPhone ويتجاهل التصميم المخصص. | **الحل:** إضافة الخاصيتين معاً: `playsinline` و `webkit-playsinline` و `preload="metadata"`. |
| **الحواف الدائرية `rounded` مع `overflow-hidden`** | العناصر الفرعية ذات الـ transforms أو التسريع العتادي تتسرب خارج المنحنيات. | **الحل:** تطبيق كلاس `.safari-clip-fix` (الذي يطبق `-webkit-mask-image: -webkit-radial-gradient(white, black)` و `transform: translateZ(0)`). |
| **توسيط العناصر المطلقة داخل Flex** | `inset-0 m-auto` يهتز أو يزاح في محرك WebKit أثناء التحويم أو الانتقالات. | **الحل:** استخدام التوسيط الهندسي الصريح: `top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2`. |
| **نسبة الأبعاد `aspect-ratio` داخل Flex** | تنهار إلى ارتفاع 0 قبل اكتمال تحميل الفيديو أو الصورة في سفاري. | **الحل:** إضافة `min-h-0` أو `min-w-0` للحاوية، أو وضع الوسائط بـ `absolute inset-0`. |
| **حقول الإدخال والأزرار** | سفاري يطبق ظلالاً وحواف رمادية افتراضية لنظام iOS. | **الحل:** استخدام `-webkit-appearance: none;` في قواعد الستايل العامة. |
| **تنعيم الخطوط العربية** | تظهر حواف الخطوط العربية متعرجة أو غير حادة في سفاري. | **الحل:** إضافة `-webkit-font-smoothing: antialiased;`. |

---

## 4. أدوات المعاينة والاختبار المتوفرة في المشروع (Tooling Suite)

### 1. الفاحص الرياضي الآلي للتخطيط (`scripts/audit-webkit-layout.py`):
أداة سريعة بدون واجهة رسومية (Headless) تفحص أي رابط محلي أو سحابي، وتقيس محاذاة كل عناصر الصفحة رياضياً في ثوانٍ:
```bash
# فحص وضع الآيفون (iPhone 15 Pro - 393px):
python3 scripts/audit-webkit-layout.py http://localhost:3000/courses/inperson/1 --mobile

# فحص وضع الديسكتوب:
python3 scripts/audit-webkit-layout.py http://localhost:3000/courses/inperson/1 --desktop

# حفظ لقطة شاشة للتحقق البصري:
python3 scripts/audit-webkit-layout.py http://localhost:3000/courses/inperson/1 --mobile --screenshot=/tmp/preview.png
```

### 2. متصفح WebKit الأصلي التفاعلي (`scripts/safari_preview.py`):
تطبيق متكامل مبني على محرك WebKitGTK الرسمي بنظام لينكس للمعاينة البصرية التفاعلية:
```bash
# داخل مجلد safia-frontend:
npm run safari:mobile   # وضع آيفون iOS Safari
npm run safari          # وضع ديسكتوب Safari Mac

# أو مباشرة من جذر المشروع:
./scripts/preview-safari.sh http://localhost:3000 --mobile
```
*يدعم أدوات المطورين الرسمية (WebKit Web Inspector) عبر الضغط بالزر الأيمن واختيار "فحص العنصر / Inspect Element".*

### 3. مدقق الكود المصدري الثابت (`scripts/audit-safari-css.mjs`):
لفحص ملفات الـ Vue و SCSS قبل الحفظ:
```bash
node scripts/audit-safari-css.mjs pages/courses/inperson/[id].vue
```

---

## 5. صيغة الرد الإلزامية للمستخدم (Standard Agent Response Pattern)

عند تقديم تقرير أو إنجاز مهمة تتعلق بالواجهات الأمامية، يجب أن يتضمن الرد دائماً:
1. **التشخيص الجذري:** شرح سبب المشكلة في WebKit (مثلاً: تمدد `1fr` أو غياب `min-width: 0`).
2. **الحل النظيف:** توضيح الأسطر المعدلة دون إضافة كلاسات زائدة.
3. **نتيجة الفحص الرياضي الآلي:** إرفاق أرقام عرض الشاشة والمستند (`scrollWidth <= innerWidth` و `overflowCount: 0`).
4. **أمر المعاينة المباشرة:**
```text
💡 يمكنك معاينة الصفحة مباشرة بمحرك سفاري عبر:
cd safia-frontend && npm run safari:mobile
(رابط الفحص: http://localhost:3000/...)
```
