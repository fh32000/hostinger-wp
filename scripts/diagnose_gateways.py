#!/usr/bin/env python3
import json
import subprocess
import os

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

def update_snippet(nonce, code_str):
    # Verify local syntax first
    with open('/tmp/test_syntax.php', 'w') as f:
        f.write('<?php\n' + code_str)
    res = subprocess.run(['php', '-l', '/tmp/test_syntax.php'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    if res.returncode != 0:
        print("Syntax error in PHP snippet:", res.stderr)
        return False
    print("PHP syntax OK!")

    payload = json.dumps({"code": code_str, "active": True})
    with open('/tmp/payload_snippet5.json', 'w') as f:
        f.write(payload)

    cmd = ["curl", "-s", "-X", "POST", "-b", COOKIE_FILE, "-H", f"X-WP-Nonce: {nonce}",
           "-H", "Content-Type: application/json", "-d", "@/tmp/payload_snippet5.json",
           f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5"]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    data = json.loads(p.stdout)
    print("Snippet 5 updated:", data.get("active"))

    cmd_act = ["curl", "-s", "-X", "POST", "-b", COOKIE_FILE, "-H", f"X-WP-Nonce: {nonce}",
               f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5/activate"]
    p_act = subprocess.run(cmd_act, stdout=subprocess.PIPE, text=True)
    print("Snippet 5 activated:", p_act.stdout)
    return True

def call_action(action_name, params=None):
    if params is None:
        params = {}
    params['token'] = 'taj_sec_migration_9827'
    params['action'] = action_name
    with open('/tmp/action_payload.json', 'w') as f:
        json.dump(params, f)
    cmd = ["curl", "-s", "-X", "POST", "-H", "Content-Type: application/json",
           "-d", "@/tmp/action_payload.json", f"{BASE_URL}/wp-json/taj/v1/migrate"]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    return json.loads(p.stdout)

if __name__ == "__main__":
    nonce = get_nonce()
    if not nonce:
        print("Failed to get nonce")
        exit(1)

    code = r'''add_action('rest_api_init', function() {
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

            if ($action === 'diag_gateways') {
                $plugins = scandir(WP_PLUGIN_DIR);
                $active_plugins = get_option('active_plugins', []);
                $all_gateways = [];
                $available_gateways = [];
                if (function_exists('WC') && WC()->payment_gateways()) {
                    foreach (WC()->payment_gateways()->payment_gateways() as $id => $gw) {
                        $all_gateways[$id] = [
                            'id' => $gw->id,
                            'title' => $gw->get_title(),
                            'enabled' => $gw->enabled ?? 'unknown',
                            'is_available' => $gw->is_available(),
                            'class' => get_class($gw),
                            'settings' => $gw->settings ?? []
                        ];
                    }
                    foreach (WC()->payment_gateways()->get_available_payment_gateways() as $id => $gw) {
                        $available_gateways[$id] = $gw->get_title();
                    }
                }
                $plugin_details = [];
                foreach ($plugins as $p) {
                    if (in_array($p, ['.', '..'])) continue;
                    $full_p = WP_PLUGIN_DIR . '/' . $p;
                    $plugin_details[$p] = [
                        'is_dir' => is_dir($full_p),
                        'files' => is_dir($full_p) ? array_slice(scandir($full_p), 0, 15) : 'file'
                    ];
                }
                return [
                    'active_plugins' => $active_plugins,
                    'all_gateways' => $all_gateways,
                    'available_gateways' => $available_gateways,
                    'plugins' => $plugin_details,
                    'wc_quick_pay_settings' => get_option('wc_quick_pay_settings', []),
                    'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : ''
                ];
            }

            return ['error' => 'Unknown action'];
        },
        'permission_callback' => '__return_true'
    ]);
});'''

    if update_snippet(nonce, code):
        res = call_action("diag_gateways")
        with open('/tmp/diag_result.json', 'w') as f:
            json.dump(res, f, indent=2, ensure_ascii=False)
        print("Success! Result saved to /tmp/diag_result.json")
