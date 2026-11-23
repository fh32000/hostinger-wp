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

    query_code = r"""
if ($action === 'query_muskise_pay_page') {
    $cfg_file = '/home/u286043199/domains/muskise.com/public_html/wp-config.php';
    $cfg = file_get_contents($cfg_file);
    preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $cfg, $m_name);
    preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $cfg, $m_user);
    preg_match("/define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $cfg, $m_pass);
    preg_match("/define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $cfg, $m_host);
    preg_match("/\\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]/", $cfg, $m_prefix);

    $mysqli = new mysqli($m_host[1] ?? 'localhost', $m_user[1], $m_pass[1] ?? '', $m_name[1]);
    $prefix = $m_prefix[1] ?? 'wp_';
    
    // Check page
    $res = $mysqli->query("SELECT ID, post_title, post_name, post_status, post_content FROM {$prefix}posts WHERE post_name = 'pay' OR post_name LIKE '%quick-pay%'");
    $posts = [];
    while ($r = $res->fetch_assoc()) {
        $posts[] = $r;
    }
    
    // Check options
    $res2 = $mysqli->query("SELECT option_name, option_value FROM {$prefix}options WHERE option_name LIKE '%quick_pay%'");
    $options = [];
    while ($r2 = $res2->fetch_assoc()) {
        $options[$r2['option_name']] = maybe_unserialize($r2['option_value']);
    }

    // Check active plugins
    $res3 = $mysqli->query("SELECT option_value FROM {$prefix}options WHERE option_name = 'active_plugins'");
    $r3 = $res3 ? $res3->fetch_assoc() : null;
    $plugins = maybe_unserialize($r3['option_value'] ?? '');

    return [
        'posts' => $posts,
        'options' => $options,
        'active_plugins' => $plugins
    ];
}
"""
    if "query_muskise_pay_page" not in code:
        target = "terms' => wp_get_post_terms(6536, 'product_visibility')\n    ];\n}"
        code = code.replace(target, target + "\n\n" + query_code)
        payload = {"name": "Taj Migration REST Route", "code": code, "scope": "global", "active": True}
        with open("/tmp/snip_query.json", "w") as f:
            json.dump(payload, f)
        subprocess.run(["curl", "-s", "-b", COOKIE_FILE, "-H", f"X-WP-Nonce: {nonce}", "-H", "Content-Type: application/json", "-X", "POST", f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5", "-d", "@/tmp/snip_query.json"])
        subprocess.run(["curl", "-s", "-b", COOKIE_FILE, "-H", f"X-WP-Nonce: {nonce}", "-X", "POST", f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5/activate"])

    payload_call = {"token": "taj_sec_migration_9827", "action": "query_muskise_pay_page"}
    p2 = subprocess.run(["curl", "-s", "-H", "Content-Type: application/json", "-X", "POST", f"{BASE_URL}/wp-json/taj/v1/migrate", "-d", json.dumps(payload_call)], stdout=subprocess.PIPE, text=True)
    print(p2.stdout)

if __name__ == "__main__":
    main()
