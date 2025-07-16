#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import os, json, hashlib, re
from datetime import datetime, timedelta
from pathlib import Path
from urllib.parse import urljoin
import requests
from bs4 import BeautifulSoup

SEARCHES = [
    { "query": "osciloskop", "cena_od": 50, "cena_do": 200, "max_age": 3, "category_id": 807 },
]

CATEGORIES = { 807: "elektro" }

HEADERS    = {"User-Agent": "Mozilla/5.0 BazosCrawler/1.0"}
PUSH_USER  = os.getenv("PUSHOVER_USER")
PUSH_TOKEN = os.getenv("PUSHOVER_TOKEN")
PUSH_URL   = "https://api.pushover.net/1/messages.json"

OUT_DIR   = Path("ads")
HIST_DIR  = Path("history")
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
    s = BeautifulSoup(requests.get(url, headers=HEADERS, timeout=20).text, "html.parser")
    desc = (s.find("div", class_="popisdetail") or {}).get_text("\n", strip=True)
    contact = (s.find("div", id="left").find("div", class_="inzeratydetdetail").get_text("\n", strip=True)
               if s.find("div", id="left") else "")
    imgs=[]
    for i,im in enumerate(s.select("div.thumbcontainer img")):
        src = im.get("src") or ""
        img_url = urljoin(url, src)
        ext = Path(src).suffix.split("?")[0] or ".jpg"
        p = folder / f"{i+1:02d}{ext}"
        r=requests.get(img_url, headers=HEADERS, timeout=20)
        if r.ok: p.write_bytes(r.content); imgs.append(p)
    return desc, imgs, contact

def crawl():
    for s in SEARCHES:
        q,o,d,smax,cat = s.values()
        sub=CATEGORIES[cat]
        url=(f"https://{sub}.bazos.sk/?hledat={q}&rubriky=www&hlokalita=&humkreis=25&cenaod={o}&cenado={d}&submit=H%25C4%2584ada%25C5%25A5&order=nejnovejsi")
        hist_file=HIST_DIR/md5(url); hist=set(json.loads(hist_file.read_text()) if hist_file.exists() else [])
        soup=BeautifulSoup(requests.get(url,headers=HEADERS,timeout=20).text,"html.parser")
        cutoff=datetime.now()-timedelta(days=smax)
        for box in soup.select("div.inzeraty.inzerat"):
            a=box.find("a",class_="nadpis"); 
            if not a: continue
            link="https://bazos.sk"+a["href"]
            if link in hist: continue
            title=a.get_text(strip=True)
            price=(box.find("div",class_="cena") or {}).get_text(strip=True)
            date=bazos_date((box.find("span",class_="velikost10") or {}).get_text(strip=True))
            if date<cutoff: continue
            day_dir=OUT_DIR/q/date.strftime("%Y-%m-%d"); day_dir.mkdir(parents=True, exist_ok=True)
            desc,imgs,contact=detail(link,day_dir)
            ad_id=md5(link)
            imgs_html="".join(f'<img src="{p.name}">' for p in imgs)
            (day_dir/f"{ad_id}.html").write_text(HTML.format(t=title,d=date.date(),p=price,u=link,imgs=imgs_html,desc=desc,contact=contact),encoding="utf-8")
            pushover(title,price,link)
            EXPORT.append({"id":ad_id,"title":title,"price":price,"date":date.strftime('%Y-%m-%d'),"query":q,"images":[str(p.relative_to(OUT_DIR)) for p in imgs],"htmlPath":str((day_dir/f'{ad_id}.html').relative_to(OUT_DIR)),"description":desc,"contact":contact})
            hist.add(link)
        hist_file.write_text(json.dumps(sorted(hist)),encoding="utf-8")
    (OUT_DIR/"index.json").write_text(json.dumps({"ads":EXPORT},ensure_ascii=False,indent=2),encoding="utf-8")

if __name__=="__main__":
    OUT_DIR.mkdir(exist_ok=True); HIST_DIR.mkdir(exist_ok=True)
    crawl()
