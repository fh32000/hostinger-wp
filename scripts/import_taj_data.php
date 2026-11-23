<?php
/**
 * Taj Al-Hdaya WordPress Data Importer
 * Imports scraped products, pages, footer widgets, and store settings into WooCommerce / WordPress.
 */

define('WP_USE_THEMES', false);

// Path to wp-load.php
$wp_load_path = __DIR__ . '/../sites/epay.taj-alhdaya.com/wp-load.php';
if (!file_exists($wp_load_path)) {
    // If run inside docker at /var/www/html
    $wp_load_path = '/var/www/html/wp-load.php';
}

if (!file_exists($wp_load_path)) {
    die("Error: wp-load.php not found at $wp_load_path\n");
}

require_once $wp_load_path;
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Authenticate as administrator for CLI execution (prevents Elementor permission errors)
wp_set_current_user(1);

echo "============================================================\n";
echo " TAJ AL-HDAYA WORDPRESS DATA IMPORTER\n";
echo "============================================================\n";

// 1. Load Scraped Dataset
$json_path = __DIR__ . '/../data/taj_alhdaya_full_dataset.json';
if (!file_exists($json_path)) {
    $json_path = '/var/www/html/data/taj_alhdaya_full_dataset.json';
}
if (!file_exists($json_path)) {
    die("Error: dataset file not found at $json_path\n");
}

$dataset = json_decode(file_get_contents($json_path), true);
if (!$dataset) {
    die("Error: invalid JSON in dataset file.\n");
}

$store_info = $dataset['store_info'];
$pages = $dataset['pages'];
$products = $dataset['products'];

echo "[*] Dataset loaded successfully: " . count($products) . " products, " . count($pages) . " pages.\n";

// Helper: Download image and attach to media library
function import_image_from_url($image_url, $post_id = 0, $title = '') {
    static $image_cache = [];
    if (empty($image_url)) {
        return 0;
    }

    if (isset($image_cache[$image_url])) {
        return $image_cache[$image_url];
    }

    // Check if attachment with same source url or filename already exists
    $filename = basename(parse_url($image_url, PHP_URL_PATH));
    $existing = get_posts([
        'post_type' => 'attachment',
        'meta_key' => '_source_image_url',
        'meta_value' => $image_url,
        'posts_per_page' => 1,
        'post_status' => 'any'
    ]);

    if (!empty($existing)) {
        $attachment_id = $existing[0]->ID;
        $image_cache[$image_url] = $attachment_id;
        return $attachment_id;
    }

    // Download temporary file
    $tmp_file = download_url($image_url, 30);
    if (is_wp_error($tmp_file)) {
        echo "    [!] Warning: Failed downloading image {$image_url}: " . $tmp_file->get_error_message() . "\n";
        return 0;
    }

    $file_array = [
        'name' => $filename,
        'tmp_name' => $tmp_file
    ];

    $attachment_id = media_handle_sideload($file_array, $post_id, $title);
    if (is_wp_error($attachment_id)) {
        @unlink($tmp_file);
        echo "    [!] Warning: Failed sideloading image {$image_url}: " . $attachment_id->get_error_message() . "\n";
        return 0;
    }

    update_post_meta($attachment_id, '_source_image_url', $image_url);
    if (!empty($title)) {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $title);
    }

    $image_cache[$image_url] = $attachment_id;
    return $attachment_id;
}

// 2. Update General Settings
echo "\n[*] 1/6 Updating General WordPress Settings...\n";
update_option('blogname', 'متجر تاج الهدايا');
update_option('blogdescription', 'متجر تاج الهدايا - هدايا ومناسبات وساعات وعطور ونظارات');
echo "  [+] Updated site title: " . get_option('blogname') . "\n";
echo "  [+] Updated tagline: " . get_option('blogdescription') . "\n";

// 3. Import & Set Logo & Favicon
echo "\n[*] 2/6 Importing Store Logo & Favicon...\n";
$logo_id = import_image_from_url($store_info['logo_url'], 0, 'شعار متجر تاج الهدايا');
if ($logo_id) {
    set_theme_mod('custom_logo', $logo_id);
    // Update XStore theme mod logo
    $logo_url = wp_get_attachment_url($logo_id);
    $theme_mods = get_option('theme_mods_xstore-child', []);
    if (is_array($theme_mods)) {
        $theme_mods['logo'] = [
            'url' => $logo_url,
            'id' => (string)$logo_id,
            'height' => '55',
            'width' => '311'
        ];
        $theme_mods['logo_fixed'] = [
            'url' => $logo_url,
            'id' => (string)$logo_id,
            'height' => '55',
            'width' => '311'
        ];
        update_option('theme_mods_xstore-child', $theme_mods);
    }
    echo "  [+] Logo imported and assigned (ID: {$logo_id})\n";
}

$favicon_id = import_image_from_url($store_info['favicon_url'], 0, 'أيقونة متجر تاج الهدايا');
if ($favicon_id) {
    update_option('site_icon', $favicon_id);
    echo "  [+] Favicon imported and set (ID: {$favicon_id})\n";
}

// 4. Update Store Pages
echo "\n[*] 3/6 Updating Store Pages Content...\n";
foreach ($pages as $p) {
    $target_id = $p['target_wp_id'];
    $key = $p['key'];
    $page_post = get_post($target_id);

    if (!$page_post && !empty($p['target_wp_slug'])) {
        $found = get_page_by_path($p['target_wp_slug']);
        if ($found) {
            $target_id = $found->ID;
            $page_post = $found;
        }
    }

    if ($key === 'refund_policy') {
        $content = "<!-- wp:heading {\"level\":2} -->\n<h2 class=\"wp-block-heading\">سياسة الحجز والاسترجاع - متجر تاج الهدايا</h2>\n<!-- /wp:heading -->\n";
        $content .= "<!-- wp:paragraph -->\n<p><strong>مؤسسة تاج الهدايا (السجل التجاري: {$store_info['cr_number']})</strong></p>\n<!-- /wp:paragraph -->\n";
        $content .= "<!-- wp:paragraph -->\n<p>" . nl2br($p['content_plain']) . "</p>\n<!-- /wp:paragraph -->";
        $title = "سياسة الحجز والاسترجاع";
    } elseif ($key === 'privacy_policy') {
        $content = "<!-- wp:heading {\"level\":2} -->\n<h2 class=\"wp-block-heading\">سياسة الخصوصية - متجر تاج الهدايا</h2>\n<!-- /wp:heading -->\n";
        $content .= "<!-- wp:paragraph -->\n<p>" . nl2br($p['content_plain']) . "</p>\n<!-- /wp:paragraph -->";
        $title = "السياسة والخصوصية";
    } elseif ($key === 'about_us') {
        $content = "<!-- wp:heading {\"level\":2} -->\n<h2 class=\"wp-block-heading\">اقرأ عنا - متجر تاج الهدايا</h2>\n<!-- /wp:heading -->\n";
        $content .= "<!-- wp:paragraph -->\n<p style=\"font-size: 1.15em; line-height: 2;\">" . nl2br($p['content_plain']) . "</p>\n<!-- /wp:paragraph -->\n";
        $content .= "<!-- wp:paragraph -->\n<p><strong>العنوان:</strong> {$store_info['address']}<br><strong>السجل التجاري:</strong> {$store_info['cr_number']}</p>\n<!-- /wp:paragraph -->";
        $title = "اقرأ عنا";
    } elseif ($key === 'contact_us') {
        $title = "اتصل بنا";
        $content = "<!-- wp:heading {\"level\":2} -->\n<h2 class=\"wp-block-heading\">تواصل مع متجر تاج الهدايا</h2>\n<!-- /wp:heading -->\n";
        $content .= "<!-- wp:paragraph -->\n<p>يسعدنا تواصلكم واستقبال استفساراتكم عبر القنوات التالية:</p>\n<!-- /wp:paragraph -->\n";
        $content .= "<!-- wp:list -->\n<ul class=\"wp-block-list\">\n";
        $content .= "<li><strong>الموقع:</strong> {$store_info['address']}</li>\n";
        $content .= "<li><strong>الجوال:</strong> <a href=\"tel:{$store_info['phone']}\">{$store_info['phone']}</a> / <a href=\"tel:{$store_info['phone_secondary']}\">{$store_info['phone_secondary']}</a></li>\n";
        $content .= "<li><strong>واتساب:</strong> <a href=\"{$store_info['whatsapp_url']}\" target=\"_blank\">{$store_info['whatsapp']}</a></li>\n";
        $content .= "<li><strong>البريد الإلكتروني:</strong> <a href=\"mailto:{$store_info['email']}\">{$store_info['email']}</a></li>\n";
        $content .= "<li><strong>السجل التجاري:</strong> {$store_info['cr_number']}</li>\n";
        $content .= "</ul>\n<!-- /wp:list -->\n";
        $content .= "<!-- wp:shortcode -->\n[contact-form-7 id=\"4480\" title=\"Contact form 1\"]\n<!-- /wp:shortcode -->";
    } else {
        $title = $p['title'];
        $content = $p['content_html'];
    }

    if ($page_post) {
        wp_update_post([
            'ID' => $target_id,
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish'
        ]);
        echo "  [+] Updated Page: '{$title}' (ID: {$target_id})\n";
    } else {
        $new_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => $p['target_wp_slug']
        ]);
        echo "  [+] Created Page: '{$title}' (ID: {$new_id})\n";
    }
}

// 5. Update Homepage banner text if exists
$home_page = get_post(3738);
if ($home_page) {
    $c = $home_page->post_content;
    $c = str_replace('العالم الزهري', 'متجر تاج الهدايا', $c);
    $c = str_replace('العطور اللي تناسب كل الأذواق والمناسبات', 'الهدايا والساعات والنظارات والعطور الفاخرة التي تناسب كل الأذواق والمناسبات', $c);
    wp_update_post([
        'ID' => 3738,
        'post_content' => $c
    ]);
    echo "  [+] Updated Homepage (ID: 3738) branding references.\n";
}

// 6. Update Footer Widgets & Top Promo
echo "\n[*] 4/6 Updating Footer Widgets & Top Promo Banner...\n";
$widgets_text = get_option('widget_text', []);

// Widget 8: Store details
$logo_img_src = !empty($logo_url) ? $logo_url : 'http://localhost:8085/wp-content/uploads/2026/07/ChatGPT-Image-9-يوليو-2026،-07_10_15-م-1.png';
$widget_8_text = "<p><img src=\"{$logo_img_src}\" alt=\"متجر تاج الهدايا\" style=\"max-height: 60px; width: auto;\" /></p>\r\n" .
"<p><strong>مؤسسة تاج الهدايا</strong><br>\r\n" .
"متخصصون في مجال الهدايا والمناسبات وباقات الورد والساعات والنظارات والعطور.<br>\r\n" .
"<strong>العنوان:</strong> {$store_info['address']}<br>\r\n" .
"<strong>السجل التجاري:</strong> {$store_info['cr_number']}</p>";

$widgets_text['8']['text'] = $widget_8_text;
$widgets_text['8']['title'] = '';

// Widget 13: Copyright
$widgets_text['13']['text'] = "<p style=\"text-align: center; margin: 0; direction: rtl;\">{$store_info['copyright']}</p>";

// Widget 9: Quick Links
$site_url = get_site_url();
$widget_9_text = "<div class=\"row\">\r\n" .
"<div class=\"col-md-6 col-sm-6 col-xs-6\">\r\n" .
"<ul class=\"menu\">\r\n" .
"  <li><a href=\"{$site_url}/shop/\">صفحة المتجر</a></li>\r\n" .
"  <li><a href=\"{$site_url}/about-us/\">اقرأ عنا</a></li>\r\n" .
"  <li><a href=\"{$site_url}/privacy-policy/\">السياسة والخصوصية</a></li>\r\n" .
"  <li><a href=\"{$site_url}/refund/\">سياسة الحجز والاسترجاع</a></li>\r\n" .
"  <li><a href=\"{$site_url}/terms-and-conditions/\">الشروط والأحكام</a></li>\r\n" .
"  <li><a href=\"{$site_url}/contact-us/\">اتصل بنا</a></li>\r\n" .
"</ul>\r\n" .
"</div>\r\n" .
"</div>";
$widgets_text['9']['text'] = $widget_9_text;
$widgets_text['9']['title'] = 'روابط مفيدة';

update_option('widget_text', $widgets_text);
echo "  [+] Updated Footer Widgets 8, 9, and 13.\n";

// Update top promo banner in theme_mods
$theme_mods = get_option('theme_mods_xstore-child', []);
if (is_array($theme_mods) && isset($theme_mods['promo_text_package'])) {
    $theme_mods['promo_text_package'] = [
        [
            'text' => 'أهلاً بكم في متجر تاج الهدايا - هدايا ومناسبات وساعات وعطور ونظارات',
            'icon' => 'et_icon-gift',
            'icon_position' => 'before',
            'link_title' => 'تسوق الآن',
            'link' => "{$site_url}/shop/"
        ],
        [
            'text' => 'توصيل سريع وخدمة عملاء متميزة في حفر الباطن وجميع مدن المملكة',
            'icon' => 'et_icon-delivery',
            'icon_position' => 'before',
            'link_title' => 'تواصل معنا',
            'link' => "{$site_url}/contact-us/"
        ]
    ];
    update_option('theme_mods_xstore-child', $theme_mods);
    echo "  [+] Updated top bar promo announcements.\n";
}

// 7. Clean Old Products
echo "\n[*] 5/6 Cleaning Old Products...\n";
$old_products = get_posts([
    'post_type' => 'product',
    'posts_per_page' => -1,
    'post_status' => 'any'
]);
echo "  [*] Found " . count($old_products) . " existing products. Trashing...\n";
foreach ($old_products as $old_p) {
    wp_delete_post($old_p->ID, true); // Permanent delete to keep DB clean
}
echo "  [+] Old products removed.\n";

// 8. Create Categories & Import New Products
echo "\n[*] 6/6 Importing " . count($products) . " Products from Taj Al-Hdaya...\n";

// Pre-create categories
$created_terms = [];
$cats_to_create = [
    'ساعات', 'عطورات', 'نظارات', 'خواتم', 'طاولات تفاصيل',
    'كوشات', 'استقبال مواليد', 'استقبالات', 'هدايا وورد'
];

foreach ($cats_to_create as $cat_title) {
    $term = get_term_by('name', $cat_title, 'product_cat');
    if (!$term) {
        $new_term = wp_insert_term($cat_title, 'product_cat');
        if (!is_wp_error($new_term)) {
            $created_terms[$cat_title] = $new_term['term_id'];
        }
    } else {
        $created_terms[$cat_title] = $term->term_id;
    }
}
echo "  [+] Product categories verified/created: " . implode(', ', array_keys($created_terms)) . "\n";

$imported_count = 0;
foreach ($products as $p) {
    $title = $p['title'];
    $price = (string)$p['price'];
    $sku = $p['sku'];
    $cat_name = $p['category'];
    $desc = !empty($p['description']) ? $p['description'] : "<p>{$title}</p>";
    $short_desc = !empty($p['short_description']) ? $p['short_description'] : $title;

    // Insert Product Post
    $product_id = wp_insert_post([
        'post_title' => $title,
        'post_content' => $desc,
        'post_excerpt' => $short_desc,
        'post_status' => 'publish',
        'post_type' => 'product'
    ]);

    if (is_wp_error($product_id)) {
        echo "  [!] Error creating product '{$title}': " . $product_id->get_error_message() . "\n";
        continue;
    }

    // Set Product Meta
    update_post_meta($product_id, '_visibility', 'visible');
    update_post_meta($product_id, '_stock_status', 'instock');
    update_post_meta($product_id, 'total_sales', '0');
    update_post_meta($product_id, '_downloadable', 'no');
    update_post_meta($product_id, '_virtual', 'no');
    update_post_meta($product_id, '_regular_price', $price);
    update_post_meta($product_id, '_sale_price', '');
    update_post_meta($product_id, '_price', $price);
    update_post_meta($product_id, '_sku', $sku);
    update_post_meta($product_id, '_manage_stock', 'no');
    update_post_meta($product_id, '_source_url', $p['source_url']);

    // Set Category
    if (isset($created_terms[$cat_name])) {
        wp_set_object_terms($product_id, [$created_terms[$cat_name]], 'product_cat');
    } else {
        wp_set_object_terms($product_id, [$cat_name], 'product_cat');
    }

    // Import and set Featured Image
    if (!empty($p['main_image'])) {
        $thumb_id = import_image_from_url($p['main_image'], $product_id, $title);
        if ($thumb_id) {
            set_post_thumbnail($product_id, $thumb_id);
        }
    }

    // Gallery images
    if (!empty($p['gallery_images'])) {
        $gallery_ids = [];
        foreach ($p['gallery_images'] as $g_url) {
            $g_id = import_image_from_url($g_url, $product_id, $title);
            if ($g_id) {
                $gallery_ids[] = $g_id;
            }
        }
        if (!empty($gallery_ids)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
        }
    }

    $imported_count++;
    echo "  [+] [{$imported_count}/" . count($products) . "] '{$title}' ({$price} SAR) -> {$cat_name}\n";
}

// 9. Clear Caches & Flush
echo "\n[*] Flushing rewrite rules and caches...\n";
flush_rewrite_rules();
wp_cache_flush();
if (function_exists('run_litespeed_cache')) {
    do_action('litespeed_purge_all');
}

echo "\n============================================================\n";
echo " IMPORT COMPLETED SUCCESSFULLY!\n";
echo " - {$imported_count} Products imported.\n";
echo " - Store title, tagline, logo, and favicon updated.\n";
echo " - Pages updated (Refund, Privacy, Contact, About).\n";
echo " - Footer widgets and contact details updated.\n";
echo "============================================================\n";
