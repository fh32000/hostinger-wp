#!/usr/bin/env python3
"""
Hostinger WP Ecosystem - Sites Overview CLI
Lists all sites configured in the multi-site monorepo with category, Cloudflare account, and status.
"""

import os
import json
import re

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITES_DIR = os.path.join(BASE_DIR, "sites")

def main():
    sites = []
    for d in sorted(os.listdir(SITES_DIR)):
        if d.startswith(".") or d == "_lean-template":
            continue
        site_path = os.path.join(SITES_DIR, d)
        manifest_path = os.path.join(site_path, "lean-manifest.json")
        readme_path = os.path.join(site_path, "README.md")
        
        manifest = {}
        if os.path.exists(manifest_path):
            with open(manifest_path, encoding="utf-8") as f:
                try:
                    manifest = json.load(f)
                except Exception:
                    pass
                    
        cf_account = "-"
        status = "Active"
        if os.path.exists(readme_path):
            with open(readme_path, encoding="utf-8") as f:
                content = f.read()
                m_cf = re.search(r"حساب Cloudflare:\*\* `([^`]+)`", content)
                if m_cf:
                    cf_account = m_cf.group(1)
                m_st = re.search(r"الحالة الحالية:\*\* (.+)", content)
                if m_st:
                    status = m_st.group(1)[:30]
        elif d == "epay.taj-alhdaya.com":
            cf_account = "Woostore.dev"
            status = "Local Dev + Prod"

        sites.append({
            "domain": d,
            "name": manifest.get("name", d)[:28],
            "category": manifest.get("category", "ecommerce-stores"),
            "cf": cf_account,
            "status": status,
            "is_lean": manifest.get("lean_wordpress", True)
        })

    print(f"\n{'='*95}")
    print(f" Hostinger WordPress Sites Ecosystem ({len(sites)} sites registered)")
    print(f"{'='*95}")
    print(f"{'#':<3} {'Domain':<26} {'Category':<20} {'Cloudflare':<13} {'Name'}")
    print(f"{'-'*95}")
    for idx, s in enumerate(sites, 1):
        print(f"{idx:<3} {s['domain']:<26} {s['category']:<20} {s['cf']:<13} {s['name']}")
    print(f"{'='*95}\n")

if __name__ == "__main__":
    main()
