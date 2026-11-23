#!/usr/bin/env python3
import json
import subprocess
import sys
import time

COOKIE_FILE = "/tmp/wp_cookies.txt"
BASE_URL = "https://epay.taj-alhdaya.com"
REST_URL = f"{BASE_URL}/wp-json/taj/v1/migrate"
TOKEN = "taj_sec_migration_9827"

def get_nonce():
    cmd = ["curl", "-s", "-b", COOKIE_FILE, f"{BASE_URL}/wp-admin/"]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    for line in p.stdout.splitlines():
        if "wpApiSettings" in line and "nonce" in line:
            start = line.find('"nonce":"') + 9
            end = line.find('"', start)
            return line[start:end]
    return None

def fetch_snippet(nonce):
    cmd = [
        "curl", "-s", "-b", COOKIE_FILE,
        "-H", f"X-WP-Nonce: {nonce}",
        f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    return json.loads(p.stdout)

def main():
    print("[*] 1. Getting REST Nonce...")
    nonce = get_nonce()
    if not nonce:
        print("[!] Failed to get nonce")
        sys.exit(1)
    print(f"    Nonce: {nonce}")

    print("[*] 2. Fetching current snippet 5...")
    data = fetch_snippet(nonce)
    code = data.get("code", "")

    # Clean any previous broken injection if present
    # We locate from add_action('rest_api_init' down to wp_set_current_user(1);
    cut_start = code.find("wp_set_current_user(1);")
    if cut_start != -1:
        prefix = code[:cut_start + len("wp_set_current_user(1);")]
    else:
        print("[!] Could not locate wp_set_current_user in snippet 5")
        sys.exit(1)

    # Locate where action handling starts
    # Find apply_settings_logos_widgets_pages
    apply_pos = code.find("if ($action === 'apply_settings_logos_widgets_pages')")
    if apply_pos == -1:
        print("[!] Could not locate apply_settings_logos_widgets_pages")
        sys.exit(1)

    suffix = code[apply_pos:]

    # Remove phone and whatsapp numbers from apply_settings_logos_widgets_pages inside suffix
    # Old text pattern in snippet:
    old_phone_line = "<strong>الهاتف:</strong>"
    old_whatsapp_line = "<strong>واتساب:</strong>"
    
    # Clean suffix of those two lines
    new_suffix_lines = []
    for line in suffix.splitlines(True):
        if old_phone_line in line or old_whatsapp_line in line:
            continue
        new_suffix_lines.append(line)
    suffix_cleaned = "".join(new_suffix_lines)

    new_actions = """

            $action = $params['action'] ?? '';

            if ($action === 'get_current_widgets') {
                return [
                    'widget_text' => get_option('widget_text', []),
                    'sidebars_widgets' => get_option('sidebars_widgets', [])
                ];
            }

            if ($action === 'update_footer_remove_numbers') {
                $widgets = get_option('widget_text', []);
                if (!is_array($widgets)) {
                    $widgets = [];
                }
                $logo_url = 'https://epay.taj-alhdaya.com/wp-content/uploads/2026/11/taj-alhdaya-logo.png';
                $widgets['8'] = [
                    'title' => '',
                    'text' => '<p><img src="' . $logo_url . '" alt="شعار متجر تاج الهدايا" style="max-height: 60px; width: auto;" /></p>' . "\\r\\n" .
                              '<p><strong>مؤسسة تاج الهدايا</strong><br>' . "\\r\\n" .
                              'متخصصون في مجال الهدايا والمناسبات وباقات الورد والساعات والنظارات والعطور.<br>' . "\\r\\n" .
                              '<strong>العنوان:</strong> المملكة العربية السعودية . حفر الباطن<br>' . "\\r\\n" .
                              '<strong>السجل التجاري:</strong> 2511104959<br>' . "\\r\\n" .
                              '<strong>البريد الإلكتروني:</strong> <mark><a href="mailto:su7man@hotmail.com">su7man@hotmail.com</a></mark></p>',
                    'filter' => false,
                    'visual' => false
                ];
                update_option('widget_text', $widgets);
                wp_cache_flush();
                if (function_exists('run_litespeed_cache')) {
                    do_action('litespeed_purge_all');
                }
                return [
                    'success' => true,
                    'message' => 'Footer numbers removed successfully!',
                    'widget_8' => $widgets['8']
                ];
            }
"""

    final_code = prefix + new_actions + suffix_cleaned

    print("[*] 3. Uploading updated snippet 5...")
    payload = {
        "name": "Taj Migration REST Route",
        "code": final_code,
        "scope": "global",
        "active": True
    }
    with open("/tmp/clean_snippet_payload.json", "w", encoding="utf-8") as f:
        json.dump(payload, f)

    cmd = [
        "curl", "-s", "-b", COOKIE_FILE,
        "-H", f"X-WP-Nonce: {nonce}",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5",
        "-d", "@/tmp/clean_snippet_payload.json"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    res = json.loads(p.stdout)
    print(f"    Snippet updated: ID {res.get('id')}, Active: {res.get('active')}")

    print("[*] 4. Activating snippet 5...")
    cmd = [
        "curl", "-s", "-b", COOKIE_FILE,
        "-H", f"X-WP-Nonce: {nonce}",
        "-X", "POST",
        f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5/activate"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    print(f"    Activation response: {p.stdout}")

    time.sleep(1)

    print("[*] 5. Calling update_footer_remove_numbers action...")
    call_payload = {
        "token": TOKEN,
        "action": "update_footer_remove_numbers"
    }
    with open("/tmp/action_call.json", "w", encoding="utf-8") as f:
        json.dump(call_payload, f)

    cmd = [
        "curl", "-s",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        REST_URL,
        "-d", "@/tmp/action_call.json"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    print(f"    Action response: {p.stdout}")

    print("[*] 6. Flushing cache and rewrite rules...")
    clean_payload = {
        "token": TOKEN,
        "action": "finish_and_cleanup"
    }
    with open("/tmp/clean_call.json", "w", encoding="utf-8") as f:
        json.dump(clean_payload, f)

    cmd = [
        "curl", "-s",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        REST_URL,
        "-d", "@/tmp/clean_call.json"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    print(f"    Cleanup response: {p.stdout}")

if __name__ == "__main__":
    main()
