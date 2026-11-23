#!/usr/bin/env python3
"""
Deploy updated WooCommerce Ahli Bank (MPGS) Payment Gateway (wc-ahli-mpgs) v1.0.1
with official gateway logo to https://epay.taj-alhdaya.com/
"""

import base64
import json
import os
import re
import subprocess
import sys
import time

COOKIE_FILE = "/tmp/wp_cookies.txt"
BASE_URL = "https://epay.taj-alhdaya.com"
ZIP_PATH = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "plugins", "wc-ahli-mpgs-v1.0.1.zip")

def get_nonce():
    cmd = ["curl", "-s", "-b", COOKIE_FILE, BASE_URL + "/wp-admin/"]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    m = re.search(r'\"nonce\":\"([a-f0-9]+)\"', p.stdout)
    return m.group(1) if m else None

def update_snippet(nonce):
    cmd = ["curl", "-s", "-b", COOKIE_FILE, "-H", f"X-WP-Nonce: {nonce}", f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5"]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    data = json.loads(p.stdout)
    code = data.get("code", "")

    deploy_code = r"""
if ($action === 'deploy_ahli_mpgs') {
    $zip_b64 = $params['zip_b64'] ?? '';
    if (empty($zip_b64)) {
        return ['success' => false, 'error' => 'No zip data provided'];
    }

    $raw_zip = base64_decode($zip_b64);
    if (!$raw_zip) {
        return ['success' => false, 'error' => 'Base64 decode failed'];
    }

    $tmp_zip = wp_tempnam('ahli_mpgs_') . '.zip';
    file_put_contents($tmp_zip, $raw_zip);

    $zip = new ZipArchive();
    if ($zip->open($tmp_zip) !== true) {
        @unlink($tmp_zip);
        return ['success' => false, 'error' => 'Failed to open zip archive'];
    }

    $extract_dir = sys_get_temp_dir() . '/ahli_mpgs_ext_' . uniqid();
    if (!wp_mkdir_p($extract_dir)) {
        @unlink($tmp_zip);
        return ['success' => false, 'error' => 'Failed to create temp extract directory'];
    }

    $zip->extractTo($extract_dir);
    $zip->close();
    @unlink($tmp_zip);

    $source = is_dir($extract_dir . '/wc-ahli-mpgs') ? $extract_dir . '/wc-ahli-mpgs' : $extract_dir;

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

    $dst = WP_PLUGIN_DIR . '/wc-ahli-mpgs';
    $copy_dir($source, $dst);

    $main_file = $dst . '/wc-ahli-mpgs.php';
    $version = null;
    if (file_exists($main_file)) {
        $content = file_get_contents($main_file, false, null, 0, 1024);
        if (preg_match('/Version:\s*([^\r\n]+)/i', $content, $m)) {
            $version = trim($m[1]);
        }
    }

    $logo_path = $dst . '/assets/images/ahli-logo.png';
    $logo_exists = file_exists($logo_path);
    $logo_size = $logo_exists ? filesize($logo_path) : 0;

    $del_dir($extract_dir);

    wp_cache_flush();
    if (function_exists('run_litespeed_cache')) {
        do_action('litespeed_purge_all');
    }

    return [
        'success' => true,
        'path' => $dst,
        'version' => $version,
        'logo_exists' => $logo_exists,
        'logo_size' => $logo_size,
        'files_count' => count(scandir($dst))
    ];
}
"""

    # If deploy_ahli_mpgs already exists, remove it first
    if "if ($action === 'deploy_ahli_mpgs')" in code:
        code = re.sub(r"if \(\$action === 'deploy_ahli_mpgs'\).*?return \['success' => true, 'path' => \$dst.*?;\s*}\n", "", code, flags=re.DOTALL)

    target = "$action = $params['action'] ?? '';"
    if target in code:
        code = code.replace(target, target + "\n" + deploy_code)
    else:
        print("[!] Target marker not found in snippet code")
        sys.exit(1)

    payload = {
        "name": "Taj Migration REST Route",
        "code": code,
        "scope": "global",
        "active": True
    }

    with open("/tmp/test_snippet_syntax_ahli.php", "w", encoding="utf-8") as f:
        f.write("<?php\n" + code)
    syntax_check = subprocess.run(["php", "-l", "/tmp/test_snippet_syntax_ahli.php"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    if syntax_check.returncode != 0:
        print("[!] PHP Syntax error in snippet:", syntax_check.stdout, syntax_check.stderr)
        sys.exit(1)
    print("[+] Snippet PHP syntax OK!")

    with open("/tmp/payload_deploy_snippet_ahli.json", "w", encoding="utf-8") as f:
        json.dump(payload, f)

    cmd = [
        "curl", "-s", "-b", COOKIE_FILE,
        "-H", f"X-WP-Nonce: {nonce}",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5",
        "-d", "@/tmp/payload_deploy_snippet_ahli.json"
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
        "action": "deploy_ahli_mpgs",
        "zip_b64": zip_b64
    }

    with open("/tmp/deploy_payload_ahli.json", "w", encoding="utf-8") as f:
        json.dump(payload, f)

    print("[*] Calling deploy_ahli_mpgs on epay.taj-alhdaya.com...")
    cmd = [
        "curl", "-s",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        f"{BASE_URL}/wp-json/taj/v1/migrate",
        "-d", "@/tmp/deploy_payload_ahli.json"
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
    print(" WC AHLI MPGS v1.0.1 PRODUCTION DEPLOYER")
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

    print("[*] 3. Deploying plugin...")
    res = deploy_plugin()
    if res and res.get("success"):
        print("\n[SUCCESS] Plugin wc-ahli-mpgs deployed successfully to epay.taj-alhdaya.com!")
    else:
        print("\n[FAILURE] Deployment did not complete successfully.")
        sys.exit(1)

if __name__ == "__main__":
    main()
