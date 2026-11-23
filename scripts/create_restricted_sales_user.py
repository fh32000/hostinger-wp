#!/usr/bin/env python3
import json
import secrets
import string
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

def generate_strong_password(length=20):
    # Generates a strong, human-typeable password with upper, lower, digits, and safe symbols
    chars = string.ascii_letters + string.digits + "!@#$%&*-_=+"
    while True:
        pwd = "".join(secrets.choice(chars) for _ in range(length))
        if (any(c.islower() for c in pwd)
            and any(c.isupper() for c in pwd)
            and any(c.isdigit() for c in pwd)
            and any(c in "!@#$%&*-_=+" for c in pwd)):
            return pwd

def main():
    print("[*] 1. Generating strong password...")
    password = generate_strong_password(22)
    username = "sales"
    email = "sales@epay.taj-alhdaya.com"
    display_name = "مسؤول المبيعات"
    print(f"    Username: {username}")
    print(f"    Email: {email}")
    print(f"    Password: {password}")

    print("[*] 2. Getting REST Nonce...")
    nonce = get_nonce()
    if not nonce:
        print("[!] Failed to get nonce")
        sys.exit(1)

    print("[*] 3. Fetching snippet 5 to register user creation action...")
    cmd = [
        "curl", "-s", "-b", COOKIE_FILE,
        "-H", f"X-WP-Nonce: {nonce}",
        f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    data = json.loads(p.stdout)
    code = data.get("code", "")

    # Clean old inspect_roles_and_users if present
    if "if ($action === 'inspect_roles_and_users')" in code:
        # We can replace from inspect start to next action
        s_idx = code.find("if ($action === 'inspect_roles_and_users')")
        next_idx = code.find("if ($action === 'get_current_widgets')", s_idx)
        if next_idx != -1:
            code = code[:s_idx] + code[next_idx:]

    target = "$action = $params['action'] ?? '';"
    user_action = """
            if ($action === 'create_sales_user') {
                $user_login = $params['username'] ?? 'sales';
                $user_email = $params['email'] ?? 'sales@epay.taj-alhdaya.com';
                $user_pass = $params['password'] ?? '';
                $display_name = $params['display_name'] ?? 'مسؤول المبيعات';

                if (empty($user_pass)) {
                    return ['success' => false, 'error' => 'Password is required'];
                }

                // Check if user exists by username or email
                $existing_user = get_user_by('login', $user_login);
                if (!$existing_user) {
                    $existing_user = get_user_by('email', $user_email);
                }

                if ($existing_user) {
                    $user_id = $existing_user->ID;
                    wp_set_password($user_pass, $user_id);
                    wp_update_user([
                        'ID' => $user_id,
                        'user_email' => $user_email,
                        'display_name' => $display_name,
                        'role' => 'shop_manager'
                    ]);
                    $created = false;
                } else {
                    $user_id = wp_create_user($user_login, $user_pass, $user_email);
                    if (is_wp_error($user_id)) {
                        return ['success' => false, 'error' => $user_id->get_error_message()];
                    }
                    wp_update_user([
                        'ID' => $user_id,
                        'display_name' => $display_name,
                        'role' => 'shop_manager'
                    ]);
                    $created = true;
                }

                $user = new WP_User($user_id);
                // Ensure role is shop_manager
                $user->set_role('shop_manager');

                // Explicitly strip sensitive capabilities
                $strip_caps = [
                    'install_plugins', 'activate_plugins', 'edit_plugins', 'delete_plugins',
                    'update_plugins', 'switch_themes', 'edit_themes', 'install_themes',
                    'update_themes', 'update_core', 'edit_users', 'delete_users',
                    'create_users', 'promote_users', 'remove_users', 'manage_options',
                    'edit_files', 'manage_network', 'manage_sites'
                ];
                foreach ($strip_caps as $cap) {
                    $user->remove_cap($cap);
                    $user->add_cap($cap, false);
                }

                // Verify permissions
                $check_caps = [
                    'manage_woocommerce' => $user->has_cap('manage_woocommerce'),
                    'edit_shop_orders' => $user->has_cap('edit_shop_orders'),
                    'edit_products' => $user->has_cap('edit_products'),
                    'read_private_shop_orders' => $user->has_cap('read_private_shop_orders'),
                    'install_plugins' => $user->has_cap('install_plugins'),
                    'activate_plugins' => $user->has_cap('activate_plugins'),
                    'delete_plugins' => $user->has_cap('delete_plugins'),
                    'edit_plugins' => $user->has_cap('edit_plugins'),
                    'edit_users' => $user->has_cap('edit_users'),
                    'manage_options' => $user->has_cap('manage_options')
                ];

                return [
                    'success' => true,
                    'created' => $created,
                    'user_id' => $user_id,
                    'user_login' => $user->user_login,
                    'user_email' => $user->user_email,
                    'roles' => $user->roles,
                    'capabilities_check' => $check_caps
                ];
            }
"""
    new_code = code.replace(target, target + user_action)

    payload = {
        "name": "Taj Migration REST Route",
        "code": new_code,
        "scope": "global",
        "active": True
    }
    with open("/tmp/user_create_payload.json", "w", encoding="utf-8") as f:
        json.dump(payload, f)

    cmd = [
        "curl", "-s", "-b", COOKIE_FILE,
        "-H", f"X-WP-Nonce: {nonce}",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5",
        "-d", "@/tmp/user_create_payload.json"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    res = json.loads(p.stdout)
    print(f"    Snippet updated: ID {res.get('id')}, Active: {res.get('active')}")

    print("[*] 4. Activating snippet 5 if needed...")
    cmd = [
        "curl", "-s", "-b", COOKIE_FILE,
        "-H", f"X-WP-Nonce: {nonce}",
        "-X", "POST",
        f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5/activate"
    ]
    subprocess.run(cmd, stdout=subprocess.PIPE, text=True)

    time.sleep(1)

    print("[*] 5. Calling create_sales_user action...")
    call_payload = {
        "token": TOKEN,
        "action": "create_sales_user",
        "username": username,
        "email": email,
        "password": password,
        "display_name": display_name
    }
    with open("/tmp/create_user_call.json", "w", encoding="utf-8") as f:
        json.dump(call_payload, f)

    cmd = [
        "curl", "-s",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        REST_URL,
        "-d", "@/tmp/create_user_call.json"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    print(f"    Creation response: {p.stdout}")

    # Save credentials locally for summary
    with open("/tmp/created_credentials.json", "w", encoding="utf-8") as f:
        json.dump({
            "username": username,
            "email": email,
            "password": password,
            "login_url": f"{BASE_URL}/wp-login.php",
            "api_response": json.loads(p.stdout)
        }, f, indent=2)

if __name__ == "__main__":
    main()
