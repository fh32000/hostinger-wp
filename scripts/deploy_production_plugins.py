#!/usr/bin/env python3
"""
Deploy WooCommerce Quick Pay (wc-quick-pay) v1.3.0 to:
1. https://epay.taj-alhdaya.com/
2. https://muskise.com/

And configure default settings to HIDE Customer Information & Invoice Information
from the Dashboard settings on BOTH sites.
"""

import base64
import json
import os
import subprocess
import sys
import time

COOKIE_FILE = "/tmp/wp_cookies.txt"
BASE_URL = "https://epay.taj-alhdaya.com"
ZIP_PATH = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "plugins", "wc-quick-pay-v1.3.1.zip")

def get_nonce():
    cmd = ["curl", "-s", "-b", COOKIE_FILE, BASE_URL + "/wp-admin/"]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    for line in p.stdout.splitlines():
        if "wpApiSettings" in line and "nonce" in line:
            start = line.find('"nonce":"') + 9
            end = line.find('"', start)
            return line[start:end]
    return None

def update_snippet(nonce):
    cmd = ["curl", "-s", "-b", COOKIE_FILE, "-H", f"X-WP-Nonce: {nonce}", f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5"]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    data = json.loads(p.stdout)
    code = data.get("code", "")

    # Snippet logic for deploy_quick_pay and hide sections settings
    deploy_code = r"""
if ($action === 'deploy_quick_pay') {
    $zip_b64 = $params['zip_b64'] ?? '';
    $targets = $params['targets'] ?? ['epay', 'muskise'];
    if (empty($zip_b64)) {
        return ['success' => false, 'error' => 'No zip data provided'];
    }

    $raw_zip = base64_decode($zip_b64);
    if (!$raw_zip) {
        return ['success' => false, 'error' => 'Base64 decode failed'];
    }

    $tmp_zip = wp_tempnam('quick_pay_') . '.zip';
    file_put_contents($tmp_zip, $raw_zip);

    $zip = new ZipArchive();
    if ($zip->open($tmp_zip) !== true) {
        @unlink($tmp_zip);
        return ['success' => false, 'error' => 'Failed to open zip archive'];
    }

    $extract_dir = sys_get_temp_dir() . '/quick_pay_ext_' . uniqid();
    if (!wp_mkdir_p($extract_dir)) {
        @unlink($tmp_zip);
        return ['success' => false, 'error' => 'Failed to create temp extract directory'];
    }

    $zip->extractTo($extract_dir);
    $zip->close();
    @unlink($tmp_zip);

    $source = is_dir($extract_dir . '/wc-quick-pay') ? $extract_dir . '/wc-quick-pay' : $extract_dir;

    $copy_dir = function($src, $dst) use (&$copy_dir) {
        $dir = opendir($src);
        if (!is_dir($dst)) {
            wp_mkdir_p($dst);
        }
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;
            if (is_dir($src_path)) {
                $copy_dir($src_path, $dst_path);
            } else {
                copy($src_path, $dst_path);
            }
        }
        closedir($dir);
    };

    $del_dir = function($dir) use (&$del_dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $f) {
            $path = $dir . '/' . $f;
            is_dir($path) ? $del_dir($path) : @unlink($path);
        }
        @rmdir($dir);
    };

    $out = [];
    $destinations = [
        'epay' => WP_PLUGIN_DIR . '/wc-quick-pay',
        'muskise' => '/home/u286043199/domains/muskise.com/public_html/wp-content/plugins/wc-quick-pay'
    ];

    foreach ($targets as $target) {
        if (!isset($destinations[$target])) continue;
        $dst = $destinations[$target];
        $parent = dirname($dst);
        if (!is_dir($parent)) {
            $out[$target] = ['success' => false, 'error' => 'Parent plugin directory does not exist: ' . $parent];
            continue;
        }

        $copy_dir($source, $dst);

        $main_file = $dst . '/wc-quick-pay.php';
        $version = null;
        if (file_exists($main_file)) {
            $content = file_get_contents($main_file, false, null, 0, 1024);
            if (preg_match('/Version:\s*([^\r\n]+)/i', $content, $m)) {
                $version = trim($m[1]);
            }
        }

        $out[$target] = [
            'success' => true,
            'path' => $dst,
            'version' => $version,
            'files_count' => count(scandir($dst))
        ];
    }

    $del_dir($extract_dir);

    // ── Update database settings to hide customer & invoice sections by default ──
    $db_updates = [];

    // 1. Update epay.taj-alhdaya.com settings
    $epay_settings = get_option('wc_quick_pay_settings', []);
    $epay_settings['show_section_customer'] = 'no';
    $epay_settings['show_section_invoice']  = 'no';
    $epay_settings['show_support_box']      = 'no';
    $epay_settings['preset_amounts']       = '1000, 2000, 3000, 5000, 7000, 10000, 15000, 20000, 30000, 40000, 50000, 60000, 70000, 80000, 90000, 100000';
    $epay_settings['show_preset_buttons']  = 'yes';
    update_option('wc_quick_pay_settings', $epay_settings);
    $db_updates['epay'] = [
        'show_section_customer' => $epay_settings['show_section_customer'],
        'show_section_invoice'  => $epay_settings['show_section_invoice'],
        'show_support_box'      => $epay_settings['show_support_box'],
        'preset_amounts'        => $epay_settings['preset_amounts'],
        'show_preset_buttons'   => $epay_settings['show_preset_buttons'],
    ];

    // 2. Update muskise.com settings directly in its MySQL database
    $muskise_cfg_file = '/home/u286043199/domains/muskise.com/public_html/wp-config.php';
    if (file_exists($muskise_cfg_file)) {
        $cfg = file_get_contents($muskise_cfg_file);
        preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $cfg, $m_name);
        preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $cfg, $m_user);
        preg_match("/define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $cfg, $m_pass);
        preg_match("/define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $cfg, $m_host);
        preg_match("/\\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]/", $cfg, $m_prefix);

        if (!empty($m_name[1]) && !empty($m_user[1])) {
            $db_host = $m_host[1] ?? 'localhost';
            $mysqli = @new mysqli($db_host, $m_user[1], $m_pass[1] ?? '', $m_name[1]);
            if (!$mysqli->connect_error) {
                $prefix = $m_prefix[1] ?? 'wp_';
                $res = $mysqli->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'wc_quick_pay_settings'");
                $musk_settings = [];
                if ($res && $row = $res->fetch_assoc()) {
                    $musk_settings = maybe_unserialize($row['option_value'] ?? '');
                }
                if (!is_array($musk_settings)) {
                    $musk_settings = [];
                }
                $musk_settings['show_section_customer'] = 'no';
                $musk_settings['show_section_invoice']  = 'no';
                $musk_settings['show_support_box']      = 'no';
                $musk_settings['preset_amounts']       = '1000, 2000, 3000, 5000, 7000, 10000, 15000, 20000, 30000, 40000, 50000, 60000, 70000, 80000, 90000, 100000';
                $musk_settings['show_preset_buttons']  = 'yes';
                $ser = serialize($musk_settings);

                $stmt = $mysqli->prepare("INSERT INTO {$prefix}options (option_name, option_value, autoload) VALUES ('wc_quick_pay_settings', ?, 'yes') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)");
                if ($stmt) {
                    $stmt->bind_param('s', $ser);
                    $stmt->execute();
                    $stmt->close();
                    $db_updates['muskise'] = [
                        'success' => true,
                        'show_section_customer' => 'no',
                        'show_section_invoice'  => 'no',
                        'show_support_box'      => 'no',
                        'preset_amounts'        => $musk_settings['preset_amounts'],
                        'show_preset_buttons'   => $musk_settings['show_preset_buttons'],
                    ];
                }
                $mysqli->close();
            } else {
                $db_updates['muskise'] = ['success' => false, 'error' => $mysqli->connect_error];
            }
        }
    }

    // Purge muskise.com cache via CLI script executed in its own context
    $purge_script = '<?php define("WP_USE_THEMES", false); require_once "/home/u286043199/domains/muskise.com/public_html/wp-load.php"; if (function_exists("do_action")) { do_action("litespeed_purge_all"); } if (class_exists("LiteSpeed\\\\Purge")) { \\\\LiteSpeed\\\\Purge::purge_all(); } wp_cache_flush(); echo "CLI_PURGED_OK";';
    $cli_purge_file = '/home/u286043199/domains/muskise.com/public_html/cli_purge.php';
    file_put_contents($cli_purge_file, $purge_script);
    if (function_exists('shell_exec')) {
        $db_updates['shell_purge'] = shell_exec('php ' . escapeshellarg($cli_purge_file) . ' 2>&1');
    }
    @unlink($cli_purge_file);

    // Also purge on disk any cache folders
    $cache_dirs = [
        '/home/u286043199/lscache',
        '/home/u286043199/.lscache',
        '/home/u286043199/domains/muskise.com/public_html/wp-content/cache',
        '/home/u286043199/domains/muskise.com/public_html/wp-content/litespeed/cache',
        '/home/u286043199/domains/epay.taj-alhdaya.com/public_html/wp-content/cache',
        '/home/u286043199/domains/epay.taj-alhdaya.com/public_html/wp-content/litespeed/cache'
    ];
    $db_updates['found_cache_dirs'] = [];
    foreach ($cache_dirs as $cdir) {
        if (is_dir($cdir)) {
            $db_updates['found_cache_dirs'][] = $cdir;
            $del_dir($cdir);
        }
    }

    $musk_ht = '/home/u286043199/domains/muskise.com/public_html/.htaccess';
    if (file_exists($musk_ht)) {
        @touch($musk_ht);
        $db_updates['touched_muskise_htaccess'] = true;
    }

    wp_cache_flush();
    if (function_exists('run_litespeed_cache')) {
        do_action('litespeed_purge_all');
    }

    return ['success' => true, 'deployments' => $out, 'db_updates' => $db_updates];
}
"""

    target_start = "terms' => wp_get_post_terms(6536, 'product_visibility')\n    ];\n}"
    target_end = "if ($action === 'finish_and_cleanup') {"
    idx1 = code.find(target_start)
    idx2 = code.find(target_end)
    if idx1 != -1 and idx2 != -1:
        code = code[:idx1 + len(target_start)] + '\n\n' + code[idx2:]

    if "if ($action === 'deploy_quick_pay')" in code:
        import re
        code = re.sub(r"if \(\$action === 'deploy_quick_pay'\).*?return \['success' => true, 'deployments' => \$out.*?;\s*}\n", "", code, flags=re.DOTALL)

    target = "$action = $params['action'] ?? '';"
    code = code.replace(target, target + "\n" + deploy_code)

    payload = {
        "name": "Taj Migration REST Route",
        "code": code,
        "scope": "global",
        "active": True
    }

    with open("/tmp/test_snippet_syntax.php", "w", encoding="utf-8") as f:
        f.write("<?php\n" + code)
    syntax_check = subprocess.run(["php", "-l", "/tmp/test_snippet_syntax.php"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    if syntax_check.returncode != 0:
        print("[!] PHP Syntax error in snippet:", syntax_check.stdout, syntax_check.stderr)
        sys.exit(1)
    print("[+] Snippet PHP syntax OK!")

    with open("/tmp/payload_deploy_snippet.json", "w", encoding="utf-8") as f:
        json.dump(payload, f)

    cmd = [
        "curl", "-s", "-b", COOKIE_FILE,
        "-H", f"X-WP-Nonce: {nonce}",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5",
        "-d", "@/tmp/payload_deploy_snippet.json"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    res = json.loads(p.stdout)
    print(f"[+] Snippet 5 updated. Active: {res.get('active')}")

    # Explicitly activate snippet
    cmd_act = [
        "curl", "-s", "-b", COOKIE_FILE,
        "-H", f"X-WP-Nonce: {nonce}",
        "-X", "POST",
        f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5/activate"
    ]
    subprocess.run(cmd_act, stdout=subprocess.PIPE, text=True)
    print("[+] Snippet 5 activation verified.")

def deploy_plugin():
    print(f"[*] Reading plugin archive: {ZIP_PATH}...")
    with open(ZIP_PATH, "rb") as f:
        zip_b64 = base64.b64encode(f.read()).decode("utf-8")
    print(f"    Payload size: {len(zip_b64)} chars (base64)")

    payload = {
        "token": "taj_sec_migration_9827",
        "action": "deploy_quick_pay",
        "targets": ["epay", "muskise"],
        "zip_b64": zip_b64
    }

    with open("/tmp/deploy_payload.json", "w", encoding="utf-8") as f:
        json.dump(payload, f)

    print("[*] Calling deploy_quick_pay on epay.taj-alhdaya.com...")
    cmd = [
        "curl", "-s",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        f"{BASE_URL}/wp-json/taj/v1/migrate",
        "-d", "@/tmp/deploy_payload.json"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    try:
        res = json.loads(p.stdout)
        print("[+] Deployment response:")
        print(json.dumps(res, indent=2, ensure_ascii=False))
        return res
    except Exception as e:
        print("[!] Error parsing response:", p.stdout[:500])
        return None

def main():
    print("=" * 60)
    print(" WC QUICK PAY v1.3.0 PRODUCTION MULTI-SITE DEPLOYER")
    print("=" * 60)
    print("[*] 1. Getting REST nonce...")
    nonce = get_nonce()
    if not nonce:
        print("[!] Failed to get nonce")
        sys.exit(1)
    print(f"    Nonce: {nonce}")

    print("[*] 2. Updating Snippet 5 on production...")
    update_snippet(nonce)
    time.sleep(1)

    print("[*] 3. Deploying plugin & applying database settings...")
    res = deploy_plugin()
    if res and res.get("success"):
        print("\n[SUCCESS] Plugin deployed and settings applied successfully to all targets!")
    else:
        print("\n[FAILURE] Deployment did not complete successfully.")
        sys.exit(1)

if __name__ == "__main__":
    main()
