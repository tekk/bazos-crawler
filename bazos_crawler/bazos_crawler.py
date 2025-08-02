#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import os, json, hashlib, re, logging, shutil
from datetime import datetime, timedelta
from pathlib import Path
from urllib.parse import urljoin
import requests
from bs4 import BeautifulSoup

# Setup logging
def setup_logging():
    today_str = datetime.now().strftime("%Y-%m-%d")
    log_file = Path("../web/data/logs") / f"bazos_crawl_{today_str}.log"
    log_file.parent.mkdir(parents=True, exist_ok=True)
    
    # Create formatter
    formatter = logging.Formatter(
        '%(asctime)s - %(levelname)s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    
    # Setup file handler
    file_handler = logging.FileHandler(log_file, encoding='utf-8')
    file_handler.setLevel(logging.DEBUG)
    file_handler.setFormatter(formatter)
    
    # Setup console handler
    console_handler = logging.StreamHandler()
    console_handler.setLevel(logging.INFO)
    console_handler.setFormatter(formatter)
    
    # Setup logger
    logger = logging.getLogger('bazos_crawler')
    logger.setLevel(logging.DEBUG)
    logger.addHandler(file_handler)
    logger.addHandler(console_handler)
    
    return logger

logger = setup_logging()

SEARCHES = [
    # Macbook Pro M4 - PC kategória
    { "query": "Macbook Pro M4", "cena_od": 1500, "cena_do": 4000, "max_age": 14, "category_id": 801 },
    
    # Yaesu vysielačky - Elektronika kategória  
    { "query": "Yaesu", "cena_od": 30, "cena_do": 2000, "max_age": 14, "category_id": 807 },
]

CATEGORIES = { 
    807: "elektro",     # Elektronika
    1: "auto",          # Autá
    801: "pc",          # Počítače
    813: "mobil",       # Telefóny
    82: "reality",      # Reality
    85: "zahrada"       # Dom a záhrada
}

HEADERS    = {"User-Agent": "Mozilla/5.0 BazosCrawler/1.0"}
PUSH_USER  = os.getenv("PUSHOVER_USER")
PUSH_TOKEN = os.getenv("PUSHOVER_TOKEN")
PUSH_URL   = "https://api.pushover.net/1/messages.json"

OUT_DIR   = Path("../web/data/found_items")
HIST_DIR  = Path("../web/data/history")
EXPORT    = []

def md5(t): return hashlib.md5(t.encode()).hexdigest()

def bazos_date(txt):
    today = datetime.today()
    if "Dnes" in txt: return today
    if "Včera" in txt: return today - timedelta(days=1)
    m = re.search(r"(\d{1,2})\.\s*(\d{1,2})\.", txt)
    if m:
        d, mth = map(int, m.groups()); y=today.year
        dt = datetime(y, mth, d)
        return dt if dt<=today else dt.replace(year=y-1)
    return today - timedelta(days=9999)

def pushover(title, msg, url):
    if not (PUSH_USER and PUSH_TOKEN): return
    logger.debug(f"Sending Pushover notification: {title}")
    requests.post(PUSH_URL, data={
        "token": PUSH_TOKEN, "user": PUSH_USER,
        "title": title[:250], "message": msg[:1024],
        "url": url, "url_title": "Otvoriť inzerát"
    }, headers=HEADERS, timeout=10)

HTML = """<!DOCTYPE html><html lang=sk><head><meta charset=utf-8>
<title>{t}</title><style>:root{{color-scheme:dark;background:#121212;color:#e0e0e0;font-family:sans-serif}}
a{{color:#80cbc4}}img{{max-width:100%;height:auto;margin:10px 0}}
.meta{{font-size:.9em;color:#aaa}}.content{{white-space:pre-wrap;margin-top:1em}}</style></head>
<body><h1>{t}</h1><p class=meta>{d} | {p}</p><p><a href="{u}" target=_blank>Otvoriť inzerát</a></p>{imgs}
<div class=content>{desc}</div>{contact}</body></html>"""

def detail(url, folder):
    logger.debug(f"Fetching product details from: {url}")
    try:
        response = requests.get(url, headers=HEADERS, timeout=20)
        s = BeautifulSoup(response.text, "html.parser")
        
        # Extract description
        desc_elem = s.find("div", class_="popisdetail")
        desc = desc_elem.get_text("\n", strip=True) if desc_elem else ""
        
        # Contact info is usually not on detail page, handled from search page
        contact = ""
        
        imgs = []
        
        # Find images using correct selector for Bazos.sk
        image_elements = s.select('img[src*=".jpg"]')
        # Filter to get only product images (not ads, icons etc)
        product_images = [img for img in image_elements 
                         if 'bazos.sk/img/' in img.get('src', '') 
                         and not img.get('src', '').endswith('t.jpg')]  # exclude thumbnails
        
        for i, im in enumerate(product_images):
            src = im.get("src") or ""
            if not src:
                continue
                
            img_url = urljoin(url, src)
            ext = Path(src).suffix.split("?")[0] or ".jpg"
            
            # Save to found_items folder
            p = folder / f"{i+1:02d}{ext}"
            try:
                logger.debug(f"Downloading image {i+1}: {img_url}")
                r = requests.get(img_url, headers=HEADERS, timeout=20)
                if r.ok:
                    p.write_bytes(r.content)
                    imgs.append(p)
                        
            except Exception as e:
                logger.warning(f"Failed to download image {img_url}: {e}")
                
        logger.info(f"Downloaded {len(imgs)} images for product")
        return desc, imgs, contact
        
    except Exception as e:
        logger.error(f"Failed to fetch product details from {url}: {e}")
        return "", [], ""



def crawl():
    logger.info("Starting Bazos crawler")
    for s in SEARCHES:
        q,o,d,smax,cat = s.values()
        sub=CATEGORIES[cat]
        url=(f"https://{sub}.bazos.sk/?hledat={q}&rubriky=www&hlokalita=&humkreis=25&cenaod={o}&cenado={d}&submit=H%25C4%2584ada%25C5%25A5&order=nejnovejsi")
        
        logger.info(f"Searching for '{q}' in category '{sub}' with price range {o}-{d}€")
        logger.debug(f"Search URL: {url}")
        
        hist_file=HIST_DIR/md5(url); hist=set(json.loads(hist_file.read_text()) if hist_file.exists() else [])
        
        try:
            logger.debug(f"Fetching search results from: {url}")
            response = requests.get(url,headers=HEADERS,timeout=20)
            soup=BeautifulSoup(response.text,"html.parser")
        except Exception as e:
            logger.error(f"Failed to fetch search results: {e}")
            continue
            
        cutoff=datetime.now()-timedelta(days=smax)
        logger.info(f"Looking for ads newer than {cutoff.strftime('%Y-%m-%d')}")
        
        found_count = 0
        processed_count = 0
        
        for box in soup.select("div.inzeraty.inzeratyflex"):
            found_count += 1
            # Find the title link in the new structure
            title_elem = box.select_one("div.inzeratynadpis h2.nadpis a")
            if not title_elem: continue
            link = title_elem["href"]
            # Only add base URL if href is relative (starts with /)
            if link.startswith("/"):
                link = "https://bazos.sk" + link
            if link in hist: 
                logger.debug(f"Skipping already processed ad: {link}")
                continue
            title=title_elem.get_text(strip=True)
            
            # Find price in the new structure
            price_elem = box.select_one("div.inzeratycena")
            price = price_elem.get_text(strip=True) if price_elem else ""
            
            # Find date in the new structure  
            date_elem = box.select_one("div.inzeratynadpis span.velikost10")
            date_text = date_elem.get_text(strip=True) if date_elem else ""
            date=bazos_date(date_text)
            
            # Extract location info
            location_elem = box.select_one("div.inzeratylok")
            location = location_elem.get_text(strip=True) if location_elem else ""
            
            # Extract view count
            view_elem = box.select_one("div.inzeratyview")
            view_count = view_elem.get_text(strip=True) if view_elem else ""
            
            # Extract seller info from contact area (if available)
            seller_info = ""
            
            if date<cutoff: 
                logger.debug(f"Skipping old ad '{title}' from {date.strftime('%Y-%m-%d')}")
                continue
                
            logger.info(f"Processing new ad: '{title}' - {price} ({date.strftime('%Y-%m-%d')})")
            logger.info(f"Product URL: {link}")
            
            # Create directories
            day_dir=OUT_DIR/q/date.strftime("%Y-%m-%d"); day_dir.mkdir(parents=True, exist_ok=True)
            
            # Get product details with images
            desc,imgs,contact=detail(link,day_dir)
            
            ad_id=md5(link)
            imgs_html="".join(f'<img src="{p.name}">' for p in imgs)
            
            # Create HTML file
            html_content = HTML.format(t=title,d=date.date(),p=price,u=link,imgs=imgs_html,desc=desc,contact=contact)
            (day_dir/f"{ad_id}.html").write_text(html_content,encoding="utf-8")
            
            # Send notification
            pushover(title,price,link)
            
            # Add to export with relative paths from web/data/found_items
            product_data = {
                "id":ad_id,
                "title":title,
                "price":price,
                "date":date.strftime('%Y-%m-%d'),
                "found_at":datetime.now().isoformat(),
                "query":q,
                "url":link,
                "images":[f"data/found_items/{str(p.relative_to(OUT_DIR))}" for p in imgs],
                "htmlPath":f"data/found_items/{str((day_dir/f'{ad_id}.html').relative_to(OUT_DIR))}",
                "description":desc,
                "contact":contact,
                "location": location,
                "view_count": view_count,
                "seller_info": seller_info,
                "category": sub
            }
            
            EXPORT.append(product_data)
            hist.add(link)
            processed_count += 1
            
            logger.info(f"Successfully processed product '{title}' with {len(imgs)} images")
            
        logger.info(f"Found {found_count} ads, processed {processed_count} new ads for query '{q}'")
        hist_file.write_text(json.dumps(sorted(hist)),encoding="utf-8")
        
    logger.info(f"Crawler finished. Total products processed: {len(EXPORT)}")
    (OUT_DIR/"index.json").write_text(json.dumps({"ads":EXPORT},ensure_ascii=False,indent=2),encoding="utf-8")

if __name__=="__main__":
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    HIST_DIR.mkdir(parents=True, exist_ok=True)
    crawl()
