# GitHub Actions Workflows

Tento projekt používa dva oddelené GitHub Actions workflows:

## 1. Crawler Only (`crawler_only.yml`)

**Spúšťanie:** Každé 2 hodiny (0, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22)

**Účel:** 
- Spúšťa iba Python crawler pre zber nových inzerátov
- Udržuje perzistentné dáta medzi spusteniami
- Automaticky commituje nové dáta do repozitára

**Funkcie:**
- ✅ Persistence dát cez GitHub Artifacts (30 dní)
- ✅ Automatické commitovanie zmien
- ✅ Pravidelné aktualizácie každé 2 hodiny
- ✅ Pushover notifikácie pre nové inzeráty

**Kroky:**
1. Stiahne predchádzajúce dáta z artifacts
2. Spustí Python crawler
3. Uloží nové dáta ako artifacts
4. Commitne a pushne zmeny do repozitára

## 2. Build + Deploy (`crawl_and_deploy.yml`)

**Spúšťanie:** 
- Denne o 6:00 UTC
- Pri push-och do main vetvy, ktoré menia `web/data/**`
- Manuálne cez workflow_dispatch

**Účel:**
- Kompletný build a deploy webovej aplikácie
- Spúšťa sa keď sú dostupné nové dáta

**Kroky:**
1. Spustí Python crawler (pre úplnosť)
2. Vybuduje React aplikáciu
3. Skopíruje dáta do dist priečinka
4. Deployuje na GitHub Pages

## Výhody tohto prístupu

1. **Efektivita:** Crawler beží často, build/deploy iba keď je potrebné
2. **Persistence:** Dáta sa neztraťajú medzi spusteniami
3. **Automatizácia:** Nové inzeráty sa automaticky objavujú na webe
4. **Flexibilita:** Možnosť manuálneho spúšťania oboch workflow-ov

## Nastavenie secrets

Pre správne fungovanie je potrebné nastaviť tieto GitHub secrets:

```
PUSHOVER_USER=váš_pushover_user_key
PUSHOVER_TOKEN=váš_pushover_app_token
GITHUB_TOKEN=automaticky_dostupný
```

## Monitorovanie

- Logy crawler-a sa ukladajú do `web/data/logs/`
- Artifacts sú dostupné 30 dní
- Pushover notifikácie pre nové inzeráty