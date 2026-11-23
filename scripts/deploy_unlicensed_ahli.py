#!/usr/bin/env python3
import base64
import json
import os
import subprocess
import sys
import time

COOKIE_FILE = "/tmp/wp_cookies.txt"
BASE_URL = "https://epay.taj-alhdaya.com"
ROOT_DIR = "/home/bootfi/Projects/Active/hostinger-wp"

# 1. Package plugins
print("[*] 1. Packaging plugins...")
ahli_zip = "/tmp/wc-ahli-mpgs-v1.0.2.zip"
qp_zip = "/tmp/wc-quick-pay-v1.3.2.zip"

subprocess.run(f"rm -rf /tmp/wc-ahli-mpgs && cp -r {ROOT_DIR}/plugins/wc-ahli-mpgs /tmp/wc-ahli-mpgs && cd /tmp && rm -f {ahli_zip} && zip -rq {ahli_zip} wc-ahli-mpgs", shell=True, check=True)
subprocess.run(f"rm -rf /tmp/wc-quick-pay && cp -r {ROOT_DIR}/plugins/wc-quick-pay /tmp/wc-quick-pay && cd /tmp && rm -f {qp_zip} && zip -rq {qp_zip} wc-quick-pay", shell=True, check=True)

with open(ahli_zip, "rb") as f:
    ahli_b64 = base64.b64encode(f.read()).decode("utf-8")

with open(qp_zip, "rb") as f:
    qp_b64 = base64.b64encode(f.read()).decode("utf-8")

print(f"[+] Ahli ZIP size: {len(ahli_b64)} chars (b64)")
print(f"[+] Quick Pay ZIP size: {len(qp_b64)} chars (b64)")

# 2. Get Nonce
print("[*] 2. Getting REST nonce...")
cmd = ["curl", "-s", "-b", COOKIE_FILE, BASE_URL + "/wp-admin/"]
p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
nonce = None
for line in p.stdout.splitlines():
    if "wpApiSettings" in line and "nonce" in line:
        start = line.find('"nonce":"') + 9
        end = line.find('"', start)
        nonce = line[start:end]
        break

if not nonce:
    print("[-] Failed to get nonce!")
    sys.exit(1)
print(f"[+] Nonce: {nonce}")

# 3. Build Snippet 5 Code
snippet_code = r'''add_action('rest_api_init', function() {
    register_rest_route('taj/v1', '/migrate', [
        'methods' => 'POST',
        'callback' => function($request) {
            $params = $request->get_json_params();
            if (!is_array($params)) {
                $params = [];
            }

            $token = $params['token'] ?? '';
            if ($token !== 'taj_sec_migration_9827') {
                return new WP_Error('unauthorized', 'Unauthorized', ['status' => 403]);
            }

            wp_set_current_user(1);
            $action = $params['action'] ?? '';

            if ($action === 'deploy_all') {
                $ahli_b64 = $params['ahli_b64'] ?? '';
                $qp_b64   = $params['qp_b64'] ?? '';

                $copy_dir = function($src, $dst) use (&$copy_dir) {
                    $dir = opendir($src);
                    if (!is_dir($dst)) wp_mkdir_p($dst);
                    while (($file = readdir($dir)) !== false) {
                        if ($file === '.' || $file === '..') continue;
                        $sp = $src . '/' . $file;
                        $dp = $dst . '/' . $file;
                        if (is_dir($sp)) {
                            $copy_dir($sp, $dp);
                        } else {
                            copy($sp, $dp);
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

                $deploy_zip = function($b64, $plugin_slug, $targets) use ($copy_dir, $del_dir) {
                    $raw = base64_decode($b64);
                    if (!$raw) return ['success' => false, 'error' => 'Decode failed'];
                    $tmp_zip = wp_tempnam($plugin_slug) . '.zip';
                    file_put_contents($tmp_zip, $raw);
                    $zip = new ZipArchive();
                    if ($zip->open($tmp_zip) !== true) {
                        @unlink($tmp_zip);
                        return ['success' => false, 'error' => 'Open zip failed'];
                    }
                    $ext = sys_get_temp_dir() . '/' . $plugin_slug . '_' . uniqid();
                    wp_mkdir_p($ext);
                    $zip->extractTo($ext);
                    $zip->close();
                    @unlink($tmp_zip);

                    $src = is_dir($ext . '/' . $plugin_slug) ? $ext . '/' . $plugin_slug : $ext;
                    $res = [];
                    foreach ($targets as $t_path) {
                        $dst = $t_path . '/' . $plugin_slug;
                        $copy_dir($src, $dst);
                        $res[$dst] = is_dir($dst);
                    }
                    $del_dir($ext);
                    return ['success' => true, 'targets' => $res];
                };

                $epay_plugins = WP_PLUGIN_DIR;
                $musk_plugins = '/home/u286043199/domains/muskise.com/public_html/wp-content/plugins';

                $results = [];
                if (!empty($ahli_b64)) {
                    $targets = [$epay_plugins];
                    if (is_dir($musk_plugins)) {
                        $targets[] = $musk_plugins;
                    }
                    $results['ahli'] = $deploy_zip($ahli_b64, 'wc-ahli-mpgs', $targets);
                }

                if (!empty($qp_b64)) {
                    $targets = [$epay_plugins];
                    if (is_dir($musk_plugins)) {
                        $targets[] = $musk_plugins;
                    }
                    $results['quick_pay'] = $deploy_zip($qp_b64, 'wc-quick-pay', $targets);
                }

                // Activate lifetime license data in database
                $license_data = [
                    'license_key'     => 'AHLI-PRO-LIFETIME-ACTIVE',
                    'client_email'    => 'admin@epay.taj-alhdaya.com',
                    'client_phone'    => '0500000000',
                    'status'          => 'valid',
                    'bound_domain'    => 'epay.taj-alhdaya.com',
                    'message'         => 'الترخيص مفعّل ونشط مدى الحياة.',
                    'last_check'      => time(),
                    'server_url'      => '',
                ];
                update_option('wc_ahli_mpgs_license_data', $license_data);

                // Ensure gateway is enabled in WooCommerce
                $gw_settings = get_option('woocommerce_wc_ahli_mpgs_gateway_settings', []);
                $gw_settings['enabled'] = 'yes';
                update_option('woocommerce_wc_ahli_mpgs_gateway_settings', $gw_settings);

                // Update quick pay settings
                $qp_settings = get_option('wc_quick_pay_settings', []);
                $qp_settings['selected_gateways'] = ['wc_ahli_mpgs_gateway'];
                $qp_settings['default_gateway']  = 'wc_ahli_mpgs_gateway';
                update_option('wc_quick_pay_settings', $qp_settings);

                // Purge caches
                wp_cache_flush();
                if (function_exists('run_litespeed_cache')) {
                    do_action('litespeed_purge_all');
                }

                return [
                    'success' => true,
                    'deployments' => $results,
                    'license_active' => class_exists('WC_Ahli_MPGS_License') ? WC_Ahli_MPGS_License::is_active() : null,
                    'available_gateways' => function_exists('WC') && WC()->payment_gateways() ? array_keys(WC()->payment_gateways()->get_available_payment_gateways()) : []
                ];
            }

            return ['error' => 'Unknown action'];
        },
        'permission_callback' => '__return_true'
    ]);
});'''

# Check syntax
with open("/tmp/snippet_check.php", "w") as f:
    f.write("<?php\n" + snippet_code)
res = subprocess.run(["php", "-l", "/tmp/snippet_check.php"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
if res.returncode != 0:
    print("Syntax error in snippet:", res.stderr)
    sys.exit(1)
print("[+] Snippet PHP syntax OK!")

# Update Snippet 5
print("[*] 3. Updating Snippet 5...")
payload = json.dumps({"code": snippet_code, "active": True})
with open("/tmp/payload_snippet5.json", "w") as f:
    f.write(payload)

cmd = ["curl", "-s", "-X", "POST", "-b", COOKIE_FILE, "-H", f"X-WP-Nonce: {nonce}",
       "-H", "Content-Type: application/json", "-d", "@/tmp/payload_snippet5.json",
       f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5"]
p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
res = json.loads(p.stdout)
print(f"[+] Snippet 5 updated. Active: {res.get('active')}")

cmd_act = ["curl", "-s", "-X", "POST", "-b", COOKIE_FILE, "-H", f"X-WP-Nonce: {nonce}",
           f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5/activate"]
p_act = subprocess.run(cmd_act, stdout=subprocess.PIPE, text=True)
print(f"[+] Snippet 5 activation response: {p_act.stdout[:100]}")

# 4. Trigger deploy_all
print("[*] 4. Triggering deploy_all...")
action_payload = {
    "token": "taj_sec_migration_9827",
    "action": "deploy_all",
    "ahli_b64": ahli_b64,
    "qp_b64": qp_b64
}
with open("/tmp/action_deploy_all.json", "w") as f:
    json.dump(action_payload, f)

cmd = ["curl", "-s", "-X", "POST", "-H", "Content-Type: application/json",
       "-d", "@/tmp/action_deploy_all.json", f"{BASE_URL}/wp-json/taj/v1/migrate"]
p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
print("[+] Deploy response:")
print(p.stdout)

