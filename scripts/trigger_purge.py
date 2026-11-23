import json
import subprocess

COOKIE_FILE = "/tmp/wp_cookies.txt"
BASE_URL = "https://epay.taj-alhdaya.com"

def get_nonce():
    cmd = ["curl", "-s", "-b", COOKIE_FILE, BASE_URL + "/wp-admin/"]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    for line in p.stdout.splitlines():
        if "wpApiSettings" in line and "nonce" in line:
            start = line.find('"nonce":"') + 9
            end = line.find('"', start)
            return line[start:end]
    return None

def main():
    nonce = get_nonce()
    cmd = ["curl", "-s", "-b", COOKIE_FILE, "-H", f"X-WP-Nonce: {nonce}", f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5"]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    code = json.loads(p.stdout).get("code", "")

    mu_code = r"""
if ($action === 'install_mu_purge') {
    $mu_dir = '/home/u286043199/domains/muskise.com/public_html/wp-content/mu-plugins';
    if (!is_dir($mu_dir)) {
        wp_mkdir_p($mu_dir);
    }
    $mu_file = $mu_dir . '/lscache_purge_trigger.php';
    $mu_content = '<?php
add_action("init", function() {
    if (isset($_GET["do_lscache_purge"]) && $_GET["do_lscache_purge"] === "musk9827") {
        if (defined("LSCWP_V")) {
            do_action("litespeed_purge_all");
        }
        if (class_exists("\\LiteSpeed\\Purge")) {
            \\LiteSpeed\\Purge::purge_all();
        }
        wp_cache_flush();
        header("X-LiteSpeed-Purge: *");
        echo "LSC_PURGED_SUCCESSFULLY";
        exit;
    }
}, 1);
';
    file_put_contents($mu_file, $mu_content);
    return ['created' => file_exists($mu_file)];
}

if ($action === 'remove_mu_purge') {
    $mu_file = '/home/u286043199/domains/muskise.com/public_html/wp-content/mu-plugins/lscache_purge_trigger.php';
    if (file_exists($mu_file)) {
        @unlink($mu_file);
    }
    return ['removed' => !file_exists($mu_file)];
}
"""
    if "install_mu_purge" not in code:
        target = "terms' => wp_get_post_terms(6536, 'product_visibility')\n    ];\n}"
        code = code.replace(target, target + "\n\n" + mu_code)
        payload = {"name": "Taj Migration REST Route", "code": code, "scope": "global", "active": True}
        with open("/tmp/snip_mu.json", "w") as f:
            json.dump(payload, f)
        subprocess.run(["curl", "-s", "-b", COOKIE_FILE, "-H", f"X-WP-Nonce: {nonce}", "-H", "Content-Type: application/json", "-X", "POST", f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5", "-d", "@/tmp/snip_mu.json"])
        subprocess.run(["curl", "-s", "-b", COOKIE_FILE, "-H", f"X-WP-Nonce: {nonce}", "-X", "POST", f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5/activate"])

    # 1. Install mu purge
    p_install = subprocess.run(["curl", "-s", "-H", "Content-Type: application/json", "-X", "POST", f"{BASE_URL}/wp-json/taj/v1/migrate", "-d", '{"token":"taj_sec_migration_9827","action":"install_mu_purge"}'], stdout=subprocess.PIPE, text=True)
    print("Install mu-plugin:", p_install.stdout)

    # 2. Trigger purge via external curl to muskise.com
    p_curl = subprocess.run(["curl", "-s", "https://muskise.com/?do_lscache_purge=musk9827"], stdout=subprocess.PIPE, text=True)
    print("Trigger purge result:", p_curl.stdout[:200])

    # 3. Remove mu-plugin
    p_rem = subprocess.run(["curl", "-s", "-H", "Content-Type: application/json", "-X", "POST", f"{BASE_URL}/wp-json/taj/v1/migrate", "-d", '{"token":"taj_sec_migration_9827","action":"remove_mu_purge"}'], stdout=subprocess.PIPE, text=True)
    print("Remove mu-plugin:", p_rem.stdout)

if __name__ == "__main__":
    main()
