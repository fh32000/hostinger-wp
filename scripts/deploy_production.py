#!/usr/bin/env python3
import json
import os
import sys
import time
import subprocess

BASE_URL = "https://tajalhdaya.roseise.com"
REST_URL = f"{BASE_URL}/wp-json/taj/v1/migrate"
TOKEN = "taj_sec_migration_9827"
COOKIE_FILE = "/tmp/wp_cookies.txt"

def load_dataset():
    with open("data/taj_alhdaya_full_dataset.json", "r", encoding="utf-8") as f:
        return json.load(f)

def get_nonce():
    cmd = ["curl", "-s", "-b", COOKIE_FILE, f"{BASE_URL}/wp-admin/"]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    for line in p.stdout.splitlines():
        if "wpApiSettings" in line and "nonce" in line:
            start = line.find('"nonce":"') + 9
            end = line.find('"', start)
            return line[start:end]
    return None

def update_snippet():
    with open("/tmp/taj_endpoint.php", "r", encoding="utf-8") as f:
        code = f.read()

    code_clean = code.replace("<?php\n", "").replace("<?php", "")

    nonce = get_nonce()
    if not nonce:
        raise Exception("Failed to get REST nonce from wp-admin")

    payload = {
        "name": "Taj Migration REST Route",
        "code": code_clean,
        "scope": "global",
        "active": True
    }
    with open("/tmp/snippet_payload.json", "w", encoding="utf-8") as pf:
        json.dump(payload, pf)

    cmd = [
        "curl", "-s", "-b", COOKIE_FILE,
        "-H", f"X-WP-Nonce: {nonce}",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        f"{BASE_URL}/wp-json/code-snippets/v1/snippets/5",
        "-d", "@/tmp/snippet_payload.json"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    try:
        resp = json.loads(p.stdout)
        if resp.get("id") == 5:
            print("[+] Snippet 5 updated and activated successfully.")
            return True
    except Exception as e:
        print("[!] Error updating snippet:", p.stdout)
        raise

def call_action(action, extra_params=None):
    payload = {
        "token": TOKEN,
        "action": action
    }
    if extra_params:
        payload.update(extra_params)

    tmp_file = f"/tmp/taj_payload_{action}.json"
    with open(tmp_file, "w", encoding="utf-8") as f:
        json.dump(payload, f)

    cmd = [
        "curl", "-s",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        REST_URL,
        "-d", f"@{tmp_file}"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, text=True)
    try:
        return json.loads(p.stdout)
    except Exception as e:
        print(f"[!] Failed to parse JSON response for action {action}:", p.stdout[:400])
        raise

def main():
    print("==================================================")
    print(" TAJ ALHDAYA PRODUCTION FULL MIGRATION & DEPLOYMENT")
    print("==================================================")

    # 1. Update snippet
    print("\n[*] 1/6 Deploying migration REST route to production...")
    update_snippet()
    time.sleep(1)

    # 2. Update Settings, Logos, Widgets, Pages
    print("\n[*] 2/6 Applying Site Settings, Logos, Widgets, and Pages...")
    res = call_action("apply_settings_logos_widgets_pages")
    print("    Result:", res)

    # 3. Delete old products
    print("\n[*] 3/6 Deleting Old Products on Production...")
    res = call_action("delete_old_products")
    print(f"    Result: Deleted {res.get('deleted_count')} old products.")

    # 4. Create categories
    print("\n[*] 4/6 Creating/Verifying Product Categories...")
    res = call_action("create_categories")
    cat_map = res.get("category_map", {})
    print("    Categories Map:", cat_map)

    # 5. Import products in batches
    dataset = load_dataset()
    products = dataset["products"]
    total = len(products)
    batch_size = 6
    print(f"\n[*] 5/6 Importing {total} Products in batches of {batch_size}...")

    imported_total = 0
    for i in range(0, total, batch_size):
        chunk = products[i:i + batch_size]
        print(f"    -> Sending batch {i // batch_size + 1}/{(total + batch_size - 1) // batch_size} (Products {i + 1} to {min(i + batch_size, total)})...")
        res = call_action("import_products_batch", {"batch": chunk, "cat_map": cat_map})
        items = res.get("imported", [])
        for itm in items:
            print(f"       + [{itm.get('id')}] {itm.get('title')} ({itm.get('price')} SAR) - {itm.get('category')}")
        imported_total += len(items)
        time.sleep(1)

    print(f"\n[+] Total imported products: {imported_total}/{total}")

    # 6. Finish and flush
    print("\n[*] 6/6 Flushing Caches & Rules on Production...")
    res = call_action("finish_and_cleanup")
    print("    Result:", res)

    print("\n==================================================")
    print(" MIGRATION COMPLETED SUCCESSFULLY!")
    print("==================================================")

if __name__ == "__main__":
    main()
