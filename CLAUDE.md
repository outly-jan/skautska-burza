# Pravidla pro práci na tomto projektu

Samostatný WordPress plugin **Skautská burza** pro web skautchlumec.cz.
Původně vznikl jako složka `skautska-burza/` v repozitáři
`outly-jan/vlcci-svetylka`, odkud byl i s historií přesunut sem.

## Workflow
- Po každé smysluplné změně: commit → push → PR → squash merge do `main`
- Commity a PR popisky piš česky

## Před každým mergem — povinná kontrola
Před vytvořením PR vždy spusť:
```
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```
Pokud PHP hlásí chybu, nesmíš mergovat.

## Deploy
- Po mergi do `main` se automaticky spustí GitHub Actions a zavolá
  `https://skautchlumec.cz/wp-content/plugins/skautska-burza/deploy-webhook.php`
- Token je uložen v GitHub secret `DEPLOY_SECRET` tohoto repozitáře
- Soubor `deploy-webhook.php` na serveru má token nastaven ručně a webhook
  sám sebe nikdy nestahuje — změny jeho kódu je nutné nahrát na server ručně
  (FTP), nikdy v něm přes git neměň `CHANGE_ME`
- Webhook nestahuje `.github/`, `CLAUDE.md` ani sám sebe; vše ostatní v repu
  se nasadí do `wp-content/plugins/skautska-burza/`
- Na FTP je správná cesta `/public_html/wp-content/plugins/skautska-burza/`;
  složka `/wp-content/plugins/` mimo `public_html` je chybná, nepoužívat
