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

    # Clean previous email line from snippet 5 code
    old_email_line = "<strong>البريد الإلكتروني:</strong>"
    
    lines = code.splitlines(True)
    cleaned_lines = []
    for l in lines:
        if old_email_line in l:
            # Skip this line
            continue
        cleaned_lines.append(l)
    
    code_cleaned = "".join(cleaned_lines)
    
    # Ensure lines ending with commercial register close the <p> properly
    # In widget_8 definitions:
    # '<strong>السجل التجاري:</strong> 2511104959<br>' . "\r\n" .
    # needs to be:
    # '<strong>السجل التجاري:</strong> 2511104959</p>',
    target_cr = "'<strong>السجل التجاري:</strong> 2511104959<br>' . \"\\r\\n\" ."
    replacement_cr = "'<strong>السجل التجاري:</strong> 2511104959</p>',"
    code_cleaned = code_cleaned.replace(target_cr, replacement_cr)

    target_cr_nl = "'<strong>السجل التجاري:</strong> 2511104959<br>' . \"\\r\\n\""
    code_cleaned = code_cleaned.replace(target_cr_nl, "'<strong>السجل التجاري:</strong> 2511104959</p>'")

    print("[*] 3. Uploading updated snippet 5...")
    payload = {
        "name": "Taj Migration REST Route",
        "code": code_cleaned,
        "scope": "global",
        "active": True
    }
    with open("/tmp/email_remove_snippet.json", "w", encoding="utf-8") as f:
        json.dump(payload, f)

    cmd = [
        "curl", "-s", "-b", COOKIE_FILE,
        "-H", f"X-WP-Nonce: {nonce}",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5",
        "-d", "@/tmp/email_remove_snippet.json"
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

    print("[*] 5. Calling update_footer_remove_numbers action...")
    call_payload = {
        "token": TOKEN,
        "action": "update_footer_remove_numbers"
    }
    with open("/tmp/action_call_email.json", "w", encoding="utf-8") as f:
        json.dump(call_payload, f)

    cmd = [
        "curl", "-s",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        REST_URL,
        "-d", "@/tmp/action_call_email.json"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    print(f"    Action response: {p.stdout}")

    print("[*] 6. Flushing cache and rewrite rules...")
    clean_payload = {
        "token": TOKEN,
        "action": "finish_and_cleanup"
    }
    with open("/tmp/clean_call_email.json", "w", encoding="utf-8") as f:
        json.dump(clean_payload, f)

    cmd = [
        "curl", "-s",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        REST_URL,
        "-d", "@/tmp/clean_call_email.json"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    print(f"    Cleanup response: {p.stdout}")

if __name__ == "__main__":
    main()

