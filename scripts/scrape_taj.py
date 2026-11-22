#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Taj Al-Hdaya Store Scraper
Extracts products, pages, footer, contact information, and store metadata from https://taj-alhdaya.com/
"""

import os
import sys
import json
import time
import re
import urllib.request
import urllib.parse
import xml.etree.ElementTree as ET

BASE_URL = "https://taj-alhdaya.com"
HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
    "Accept-Language": "ar,en-US;q=0.7,en;q=0.3"
}

def fetch_url(url, retries=3):
    encoded_url = urllib.parse.quote(url, safe=":/?=&%")
    req = urllib.request.Request(encoded_url, headers=HEADERS)
    for attempt in range(retries):
        try:
            with urllib.request.urlopen(req, timeout=20) as resp:
                return resp.read().decode("utf-8", errors="ignore")
        except Exception as e:
            if attempt == retries - 1:
                print(f"[!] Error fetching {url}: {e}", file=sys.stderr)
                return ""
            time.sleep(1.5)
    return ""

def cf_decode(cf_hex):
    try:
        r = int(cf_hex[:2], 16)
        return "".join([chr(int(cf_hex[i:i+2], 16) ^ r) for i in range(2, len(cf_hex), 2)])
    except Exception:
        return ""

def clean_html_text(raw_html):
    if not raw_html:
        return ""
    text = re.sub(r"<script[^>]*>.*?</script>", " ", raw_html, flags=re.DOTALL | re.IGNORECASE)
    text = re.sub(r"<style[^>]*>.*?</style>", " ", text, flags=re.DOTALL | re.IGNORECASE)
    text = re.sub(r"<[^>]+>", " ", text)
    lines = [line.strip() for line in text.splitlines() if line.strip()]
    return "\n".join(lines)

def scrape_store_info(home_html):
    print("[*] Extracting store information & footer data...")
    store_info = {
        "store_name": "تاج الهدايا",
        "legal_name": "مؤسسة تاج الهدايا",
        "cr_number": "2511104959",
        "city": "حفر الباطن",
        "country": "المملكة العربية السعودية",
        "address": "المملكة العربية السعودية . حفر الباطن",
        "phone": "+966569628748",
        "phone_formatted": "0569628748",
        "phone_secondary": "0551886005",
        "whatsapp": "+966541804299",
        "whatsapp_url": "https://wa.me/966541804299",
        "email": "su7man@hotmail.com",
        "logo_url": "https://images.matjrah.online/7295/image/catalog/1771162475-logo.png",
        "favicon_url": "https://images.matjrah.online/7295/image/catalog/1771162485-icon.png",
        "copyright": "جميع الحقوق محفوظة لمتجر تاج الهدايا @ 2026",
        "social_links": {
            "whatsapp": "https://wa.me/966541804299",
            "twitter": "https://x.com/tajalhdaya",
            "instagram": "https://instagram.com/tajalhdaya",
            "tiktok": "https://www.tiktok.com/@tajalhdaya",
            "snapchat": "https://snapchat.com/add/tajalhdaya"
        }
    }

    # Extract email if found in cf obfuscation
    cf_matches = re.findall(r"email-protection#([a-f0-9]+)", home_html)
    for h in set(cf_matches):
        dec = cf_decode(h)
        if "@" in dec:
            store_info["email"] = dec

    # Extract CR if found
    cr_match = re.search(r"السجل التجاري\s*(\d+)", home_html)
    if cr_match:
        store_info["cr_number"] = cr_match.group(1)

    return store_info

def scrape_pages():
    print("[*] Scraping information pages...")
    page_urls = [
        {
            "key": "refund_policy",
            "url": f"{BASE_URL}/%D8%B3%D9%8A%D8%A7%D8%B3%D8%A9-%D8%A7%D9%84%D8%AD%D8%AC%D8%B2-%D9%88%D8%A7%D9%84%D8%A7%D8%B3%D8%AA%D8%B1%D8%AC%D8%A7%D8%B9",
            "default_title": "سياسة الحجز والاسترجاع",
            "target_wp_slug": "refund",
            "target_wp_id": 6353
        },
        {
            "key": "privacy_policy",
            "url": f"{BASE_URL}/%D8%A7%D9%84%D8%B3%D9%8A%D8%A7%D8%B3%D9%87-%D9%88%D8%A7%D9%84%D8%AE%D8%B5%D9%88%D8%B5%D9%8A%D9%87",
            "default_title": "السياسة والخصوصية",
            "target_wp_slug": "privacy-policy",
            "target_wp_id": 3
        },
        {
            "key": "contact_us",
            "url": f"{BASE_URL}/contact",
            "default_title": "اتصل بنا",
            "target_wp_slug": "contact-us",
            "target_wp_id": 4267
        },
        {
            "key": "about_us",
            "url": f"{BASE_URL}/%D8%A7%D9%82%D8%B1%D8%A3-%D8%B9%D9%86%D8%A7",
            "default_title": "اقرأ عنا",
            "target_wp_slug": "about-us",
            "target_wp_id": 3825
        }
    ]

    scraped_pages = []
    for item in page_urls:
        html = fetch_url(item["url"])
        title = item["default_title"]
        title_m = re.search(r"<h1[^>]*>(.*?)</h1>", html)
        if title_m:
            title = re.sub(r"<[^>]+>", "", title_m.group(1)).strip()

        # Extract content block inside id="content"
        content_html = ""
        c_block = re.search(r"id=\"content\"[^>]*>(.*?)</div\s*>\s*</div\s*>", html, re.DOTALL)
        if c_block:
            content_html = c_block.group(1).strip()
        else:
            content_html = clean_html_text(html)

        # For contact page specifically, parse the address and phone fields
        contact_meta = {}
        if item["key"] == "contact_us":
            phone_m = re.search(r"(\+?966\d{9}|05\d{8})", html)
            if phone_m:
                contact_meta["phone"] = phone_m.group(1)

        scraped_pages.append({
            "key": item["key"],
            "title": title,
            "url": item["url"],
            "target_wp_slug": item["target_wp_slug"],
            "target_wp_id": item["target_wp_id"],
            "content_html": content_html,
            "content_plain": clean_html_text(content_html),
            "meta": contact_meta
        })
        print(f"  [+] Scraped page: {title} ({item['key']})")

    return scraped_pages

def scrape_categories_mapping():
    print("[*] Scraping category pages to build product mapping...")
    category_urls = {
        "ساعات": f"{BASE_URL}/ساعات",
        "عطورات": f"{BASE_URL}/عطورات",
        "نظارات": f"{BASE_URL}/نظارات",
        "خواتم": f"{BASE_URL}/خاتم",
        "طاولات تفاصيل": f"{BASE_URL}/طاول-تفاصيل",
        "استقبال مواليد": f"{BASE_URL}/استقبال-مواليد",
        "استقبالات": f"{BASE_URL}/استقبالات",
        "كوشات": f"{BASE_URL}/كوشات"
    }

    prod_to_cat = {}
    for cat_name, cat_url in category_urls.items():
        page = 1
        while True:
            target_url = f"{cat_url}?page={page}" if page > 1 else cat_url
            html = fetch_url(target_url)
            if not html:
                break
            links = set(re.findall(r"href=[\"'](https://taj-alhdaya\.com/[^\"'#\?]+)[\"']", html))
            found_count = 0
            for l in links:
                if any(x in l for x in ["/cart", "/contact", "/search", "/my-account", "/login", "/register"]):
                    continue
                if l == cat_url or l.rstrip("/") == BASE_URL:
                    continue
                if l not in prod_to_cat:
                    prod_to_cat[l] = []
                if cat_name not in prod_to_cat[l]:
                    prod_to_cat[l].append(cat_name)
                    found_count += 1
            if f"page={page+1}" in html and found_count > 0:
                page += 1
            else:
                break

    return prod_to_cat

def determine_category(title, url, scraped_cat_list):
    if scraped_cat_list:
        return scraped_cat_list[0]
    
    t = (title + " " + urllib.parse.unquote(url)).lower()
    if "نظار" in t:
        return "نظارات"
    if "عطر" in t or "مسك" in t or "عود" in t or "بارفان" in t:
        return "عطورات"
    if any(w in t for w in ["ساعة", "ساعه", "رولكس", "اوميقا", "كارتير", "بياجيه", "بلغاري"]):
        return "ساعات"
    if "خاتم" in t:
        return "خواتم"
    if "طاول" in t:
        return "طاولات تفاصيل"
    if "كوش" in t:
        return "كوشات"
    if "مواليد" in t:
        return "استقبال مواليد"
    if "استقبال" in t:
        return "استقبالات"
    if "ورد" in t or "شمع" in t or "بوكي" in t:
        return "هدايا وورد"
    return "منتجات عامة"

def scrape_products(prod_to_cat):
    print("[*] Fetching product sitemap...")
    sitemap_xml = fetch_url(f"{BASE_URL}/sitemap_products.xml")
    root = ET.fromstring(sitemap_xml)
    namespaces = {
        "sm": "http://www.sitemaps.org/schemas/sitemap/0.9",
        "image": "http://www.google.com/schemas/sitemap-image/1.1"
    }

    sitemap_items = []
    for u in root.findall("sm:url", namespaces):
        loc = u.find("sm:loc", namespaces).text.strip()
        img_elem = u.find("image:image", namespaces)
        img_url = ""
        caption = ""
        if img_elem is not None:
            img_loc = img_elem.find("image:loc", namespaces)
            if img_loc is not None and img_loc.text:
                img_url = img_loc.text.strip()
            img_title = img_elem.find("image:title", namespaces)
            if img_title is not None and img_title.text:
                caption = img_title.text.strip()
        sitemap_items.append({"url": loc, "image": img_url, "caption": caption})

    print(f"[*] Found {len(sitemap_items)} products in sitemap. Scraping details...")
    products = []
    for idx, item in enumerate(sitemap_items, 1):
        p_url = item["url"]
        html = fetch_url(p_url)

        # Parse Schema JSON-LD
        schema_prod = None
        json_lds = re.findall(r"<script\s+type=[\"']application/ld\+json[\"']>(.*?)</script>", html, re.DOTALL)
        for j in json_lds:
            try:
                data = json.loads(j)
                if data.get("@type") == "Product":
                    schema_prod = data
                    break
            except Exception:
                pass

        # Title
        title = item["caption"]
        if schema_prod and schema_prod.get("name"):
            title = schema_prod["name"]
        if not title:
            h1_m = re.search(r"<h1[^>]*>(.*?)</h1>", html)
            if h1_m:
                title = re.sub(r"<[^>]+>", "", h1_m.group(1)).strip()
        if not title:
            title = urllib.parse.unquote(p_url.split("/")[-1]).replace("-", " ")

        # Price
        price = 0.0
        if schema_prod and schema_prod.get("offers"):
            offers = schema_prod["offers"]
            if isinstance(offers, dict):
                try:
                    price = float(offers.get("price", 0))
                except Exception:
                    pass
        if not price:
            p_m = re.search(r"class=\"product-price[^\"]*\">.*?([\d\.,]+)", html, re.DOTALL)
            if p_m:
                try:
                    price = float(p_m.group(1).replace(",", ""))
                except Exception:
                    pass

        # SKU / Model
        sku = ""
        if schema_prod and schema_prod.get("sku"):
            sku = str(schema_prod["sku"])
        if not sku:
            m_m = re.search(r"class=\"product-model\"[^>]*>(.*?)</li>", html)
            if m_m:
                sku = re.sub(r"<[^>]+>", "", m_m.group(1)).replace("الموديل:", "").strip()
        if not sku:
            sku = f"TAJ-{idx:03d}"

        # Description
        desc_html = ""
        d_m = re.search(r"id=\"tab-description\"[^>]*>(.*?)</div>", html, re.DOTALL)
        if d_m:
            desc_html = d_m.group(1).strip()
        elif schema_prod and schema_prod.get("description"):
            desc_html = f"<p>{schema_prod['description']}</p>"
        else:
            desc_html = f"<p>{title}</p>"

        # Images
        images = []
        if item["image"]:
            images.append(item["image"])
        
        # Additional gallery images
        gallery = re.findall(r"href=[\"'](https://images\.matjrah\.online/[^\"']+/catalog/products/[^\"']+)[\"']", html)
        for g in gallery:
            if g not in images:
                images.append(g)

        # Fallback to schema image if none
        if not images and schema_prod and schema_prod.get("image"):
            s_img = schema_prod["image"]
            if isinstance(s_img, str):
                images.append(s_img)
            elif isinstance(s_img, list):
                images.extend(s_img)

        # Category
        matched_cats = prod_to_cat.get(p_url, [])
        primary_cat = determine_category(title, p_url, matched_cats)

        prod_record = {
            "index": idx,
            "title": title,
            "sku": sku,
            "price": price,
            "regular_price": price,
            "sale_price": "",
            "category": primary_cat,
            "all_categories": list(set([primary_cat] + matched_cats)),
            "description": desc_html,
            "short_description": clean_html_text(desc_html)[:150],
            "main_image": images[0] if images else "",
            "gallery_images": images[1:] if len(images) > 1 else [],
            "in_stock": True,
            "source_url": p_url
        }
        products.append(prod_record)
        print(f"  [{idx:02d}/{len(sitemap_items)}] {title} | {price} SAR | {primary_cat} | SKU: {sku}")

    return products

def main():
    print("=" * 60)
    print(" TAJ AL-HDAYA DATA SCRAPER")
    print("=" * 60)
    
    os.makedirs("data", exist_ok=True)

    # 1. Scrape Homepage & Store Info
    home_html = fetch_url(BASE_URL)
    store_info = scrape_store_info(home_html)
    with open("data/store_info.json", "w", encoding="utf-8") as f:
        json.dump(store_info, f, indent=2, ensure_ascii=False)
    print("[+] Saved data/store_info.json")

    # 2. Scrape Pages
    pages = scrape_pages()
    with open("data/pages.json", "w", encoding="utf-8") as f:
        json.dump(pages, f, indent=2, ensure_ascii=False)
    print("[+] Saved data/pages.json")

    # 3. Category Mapping
    cat_mapping = scrape_categories_mapping()

    # 4. Scrape Products
    products = scrape_products(cat_mapping)
    with open("data/products.json", "w", encoding="utf-8") as f:
        json.dump(products, f, indent=2, ensure_ascii=False)
    print("[+] Saved data/products.json")

    # 5. Combined bundle
    bundle = {
        "store_info": store_info,
        "pages": pages,
        "products": products,
        "total_products": len(products),
        "exported_at": time.strftime("%Y-%m-%d %H:%M:%S")
    }
    with open("data/taj_alhdaya_full_dataset.json", "w", encoding="utf-8") as f:
        json.dump(bundle, f, indent=2, ensure_ascii=False)
    print("[+] Saved data/taj_alhdaya_full_dataset.json")

    print("\n" + "=" * 60)
    print(f" SCRAPING COMPLETE: {len(products)} products, {len(pages)} pages, store & footer info.")
    print("=" * 60)

if __name__ == "__main__":
    main()

