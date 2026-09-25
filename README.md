# Skautská burza

WordPress plugin pro burzu použitého skautského oblečení a vybavení, ze
kterého děti vyrostly. Rodiče vkládají inzeráty sami a domlouvají se mezi
sebou přímo (telefon, e-mail) — středisko je jen provozovatelem nástěnky,
žádnou komunikaci nezprostředkovává.

---

## Instalace

1. Nahraj obsah repozitáře do `wp-content/plugins/skautska-burza/` (je
   automaticky nasazovaný, viz sekce **Deploy** níže, ruční FTP upload je
   potřeba jen výjimečně).
2. Aktivuj plugin v administraci WordPressu — při aktivaci se založí
   taxonomie a sedm výchozích kategorií a naplánuje se WP-Cron úloha.
3. Vlož všechny tři shortcody (viz níže) na jednu stránku — na
   skautchlumec.cz je to `/bazar/` s Elementor PRO widgetem Panely, pod
   každým panelem jeden shortcode. Adresu stránky zkontroluj v
   **Burza → Nastavení** (prázdné = `/bazar/`).
4. V administraci pluginu (menu **Burza → Nastavení**) zkontroluj/uprav
   výchozí hodnoty a doplň kontaktní e-mail střediska.
5. Nastav systémový cron podle sekce **WP-Cron** níže — bez něj by
   e-mailové výzvy chodily jen nepravidelně (při návštěvě webu).

---

## Shortcody

| Shortcode | Použití |
|---|---|
| `[burza_vypis]` | Výpis aktivních inzerátů — dlaždice (název, fotka, velikost, cena, tlačítko Detail, štítek Rezervováno), filtr podle kategorie, fulltext, stránkování. |
| `[burza_formular]` | Vložení nového nebo úprava vlastního inzerátu (jen pro přihlášené). Nad formulářem pro nový inzerát je nápověda s pravidly burzy (čísla bere z nastavení). Úprava se otevírá jako `?burza_uprava=ID`. |
| `[burza_moje]` | Přehled vlastních inzerátů s akcemi (upravit, rezervovat / zrušit rezervaci, prodáno, prodloužit platnost, smazat; u archivovaných znovu zveřejnit). U aktivních počet dní do archivace (posledních 14 dní červeně), u archivovaných počet dní do smazání. |

Všechny odkazy burzy (zpět na přehled z detailu, „upravit“ a „Moje
inzeráty“, odkaz v e-mailu o archivaci, návrat po akcích v přehledu
a přihlášení) vedou na stránku burzy s parametrem `?burza_panel=vypis|formular|moje`.
Skript `assets/js/burza-panely.js` podle něj otevře panel, ve kterém je
příslušný shortcode (hledá nadřazený `[role="tabpanel"]` a klikne na jeho
titulek) — funguje s panely/záložkami Elementoru i s jinými přístupnými
záložkami. Úprava inzerátu (`?burza_uprava=ID`) a formulář s chybami po
odeslání otevřou panel s formulářem automaticky.

Detail jednotlivého inzerátu (`/inzerat/<slug>/`) má vlastní šablonu
(`templates/single-burza_inzerat.php`), kterou přebije stejnojmenná šablona
`single-burza_inzerat.php` v aktivním tématu, pokud existuje. Detail
ukazuje hlavní fotku s údaji vedle ní, pro přihlášené navíc popis,
nepovinné dodatečné informace (např. vyzvednutí), další
fotky, jméno a příjmení prodávajícího (z WP profilu, ne přezdívka),
telefon a e-mail. Nahoře i dole je odkaz zpět na přehled.

### Cena

Ve formuláři přepínač: částka v celých Kč (0 = zdarma), „Dohodou“, nebo
„Za odvoz“. Do `_burza_cena` se ukládá číslo, `dohodou` nebo `za_odvoz`;
zobrazuje se jako „1 500 Kč“, „zdarma“, „dohodou“, „za odvoz“ (viz
`skaut_burza_cena_text()`). Starší inzeráty s cenou zapsanou volným textem
se zobrazují beze změny.

### Limity

- max. 10 aktivních inzerátů na uživatele, nový inzerát nejdřív 30 s po předchozím,
- max. 3 fotky (nastavitelné), JPG/PNG/WEBP, max. 3 MB na soubor (prohlížeč
  fotky před odesláním sám zmenší na delší stranu 1600 px, takže limit
  běžně nepotká).

---

## Viditelnost kontaktů a cache

Telefon, e-mail, popis a další fotky se u nepřihlášených uživatelů
nezobrazují — server-side kontrola přes `is_user_logged_in()`. Aby se
tahle citlivá část nikdy nedostala do zacachovaného HTML (a neukázala se
tak omylem i nepřihlášenému), načítá se blok s kontakty a popisem přes
AJAX až po vykreslení stránky (`assets/js/burza-kontakt.js` →
`admin-ajax.php`), kde je oprávnění ověřeno znovu, per-request.

**Pokud na webu běží stránkovací cache** (WP Rocket, LiteSpeed Cache,
statická cache na hostingu…), zvaž jako doplňkové opatření vyloučení
detailu inzerátu z cache úplně — např. pravidlem pro cestu `/inzerat/*`
v nastavení cache pluginu. Není to nutné (AJAX blok je bezpečný sám
o sobě), ale ušetří to zbytečné cachování stránky, která se stejně vždy
dotahuje dynamicky.

---

## WP-Cron a systémový cron na Savaně

WordPress si standardně spouští naplánované úlohy (`wp-cron.php`) při
návštěvě webu — na běžném hostingu to znamená, že e-mailové výzvy
(`skaut_burza_kontrola`, denně) a úklid starého archivu
(`skaut_burza_uklid`, měsíčně) by chodily nepravidelně, nebo vůbec, pokud
by web měl zrovna málo návštěv.

### 1. Vypnutí WP-Cronu vázaného na návštěvnost

Do `wp-config.php` (nad řádek `/* That's all, stop editing! */`) přidej:

```php
define( 'DISABLE_WP_CRON', true );
```

### 2. Systémový cron na Savaně

Ve správě hostingu Savana najdi sekci pro plánované úlohy (cron) a přidej
úlohu spouštěnou každých 15 minut, která zavolá `wp-cron.php`:

```
*/15 * * * * wget -q -O /dev/null "https://skautchlumec.cz/wp-cron.php?doing_wp_cron" >/dev/null 2>&1
```

Pokud hosting nabízí `curl` místo `wget`, stejně tak funguje:

```
*/15 * * * * curl -s "https://skautchlumec.cz/wp-cron.php?doing_wp_cron" >/dev/null 2>&1
```

Interval 15 minut stačí s velkou rezervou — `skaut_burza_kontrola` běží
jednou denně, `skaut_burza_uklid` jednou měsíčně, obě úlohy se spustí při
nejbližším zavolání `wp-cron.php` po termínu.

---

## Datový model

CPT `burza_inzerat`, hierarchická taxonomie `burza_kategorie` (sedm
předvyplněných kategorií), vlastní post statusy `burza_rezervovano`
(veřejný) a `burza_archiv` (soukromý — vidí ho jen autor a admin) a deset
meta polí (`_burza_velikost`, `_burza_stav`, `_burza_cena`, `_burza_dodatecne_info`,
`_burza_telefon`, `_burza_email`, `_burza_fotky`,
`_burza_posledni_potvrzeni`, `_burza_pocet_vyzev`, `_burza_token`).
Telefon a e-mail jsou chráněné meta s `auth_callback`, který je
nepřihlášeným nikdy nevrátí.

## Fotky

Nahrávají se do vlastní podsložky `uploads/burza/`, označené meta klíčem
`_burza_foto`, ať jsou odlišitelné od ostatní medializace na webu. Pro
uploady z burzy vzniká jen jedna generovaná velikost (čtvercový náhled
400×400 do výpisu); jako detail slouží samotný originál, který se na
serveru zmenší na šířku max. 1200 px. Hlavní ochranu
proti zahlcení hostingu ale dělá zmenšení přímo v prohlížeči před
odesláním (`assets/js/burza-upload.js`).

## Životní cyklus a e-maily

Nezaktualizovaný inzerát: 30 dní od vložení/potvrzení → první výzva
e-mailem, pak každých dalších 14 dní další výzva a 14 dní po třetí
nezodpovězené přesun do archivu (celkem 72 dní). E-mail s výzvou obsahuje dva
jednorázové odkazy (potvrdit / už je pryč) — token se po použití vždy
regeneruje. Archivované inzeráty starší 6 měsíců se jednou měsíčně
nenávratně smažou i s fotkami.

Všechny výchozí lhůty (dny do první výzvy, interval dalších výzev, počet
výzev před archivací), max. počet fotek, adresa stránky burzy a kontaktní
e-mail střediska se dají upravit v **Burza → Nastavení**.

## Ochrana osobních údajů

Formulář vyžaduje souhlas se zveřejněním kontaktu přihlášeným uživatelům.
Smazání inzerátu (ručně i cronem) maže i fotky a veškerá postmeta včetně
kontaktů. Plugin je napojený na exportér a mazač osobních údajů WordPressu
(`wp_privacy_personal_data_exporters`/`_erasers`).

---

## Deploy

Po každém mergi do větve `main` se spustí GitHub Actions workflow
(`.github/workflows/deploy.yml`), který zavolá webhook na serveru:

```
https://skautchlumec.cz/wp-content/plugins/skautska-burza/deploy-webhook.php
```

s hlavičkou `X-Deploy-Token` (hodnota z GitHub secret `DEPLOY_SECRET`)
a `X-Deploy-Sha` (commit, který se má nasadit). Webhook si přes GitHub API
načte seznam souborů v repozitáři přesně pro tento commit a stáhne je do
složky pluginu — kromě `.github/`, `CLAUDE.md` a sebe sama. Stahování podle
SHA (ne podle větve) je nutné: raw.githubusercontent.com drží soubory
z větve několik minut v cache.

- Výstup webhooku je v logu kroku „Zavolat deploy webhook“: `OK — datum — SHA`
  a seznam souborů s velikostmi. Chybí-li za datem SHA, běží na serveru
  starý webhook.
- Server za Cloudflare občas při prvním pokusu vrátí 522 — `curl` volání
  až třikrát zopakuje.
- Deploy jde spustit i ručně: **Actions → Deploy na server → Run workflow**.

### Prvotní zprovoznění

1. V repozitáři na GitHubu nastav secret `DEPLOY_SECRET`
   (Settings → Secrets and variables → Actions) na náhodný řetězec.
2. Nahraj `deploy-webhook.php` přes FTP do
   `/public_html/wp-content/plugins/skautska-burza/` a na serveru v něm
   nahraď `CHANGE_ME` stejným řetězcem. (Na FTP existuje i
   `/wp-content/plugins/` mimo `public_html` — ta je chybná, nepoužívat.)

> **Pozor:** `deploy-webhook.php` na serveru obsahuje tajný token — neupravuj
> ho přes git, jinak se přepíše na `CHANGE_ME`. Webhook sám sebe nikdy
> nestahuje, takže po každé změně jeho kódu je potřeba ho znovu ručně nahrát
> na server.
