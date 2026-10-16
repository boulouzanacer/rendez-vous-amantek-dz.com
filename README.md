# Amantek Rendez-vous

Application web de gestion des rendez-vous, devis, interventions et encaissements pour une activité
d'installation de caméras de surveillance et de systèmes réseaux.

Conçue pour être utilisée aussi bien sur téléphone (sur le terrain) que sur PC (au bureau) :
interface responsive, navigation rapide en bas d'écran sur mobile, alertes en temps réel sur les
rendez-vous en retard et les devis arrivant à échéance.

## Fonctionnalités

- **Tableau de bord** : programme du jour, rendez-vous des 7 prochains jours, rendez-vous en retard,
  encaissements des 6 derniers mois, devis en attente, interventions en cours.
- **Agenda** : vue calendrier des rendez-vous avec navigation par jour.
- **Rendez-vous** : création, planification, suivi de statut (démarrer / terminer / client absent),
  filtres par période, recherche.
- **Clients** : fiche client complète (téléphones, e-mail, adresse, commune / wilaya), historique des
  rendez-vous, devis, interventions et paiements, solde restant.
- **Devis** : lignes détaillées, remise (montant ou pourcentage), TVA configurable, totaux
  recalculés automatiquement, statuts (brouillon / envoyé / accepté / refusé), **impression A4**.
- **Interventions** : planification des chantiers avec matériel posé, technicien assigné,
  suivi de statut et date de début / fin.
- **Paiements** : encaissements liés au client, au devis et à l'intervention, totaux par mois
  et par année.
- **Rapports** : affaires signées, montant encaissé, reste à encaisser, taux d'acceptation,
  évolution mensuelle, pipeline des devis, meilleurs clients.
- **Rôles** : administrateur (accès complet, suppression) et technicien (accès limité à ses
  propres interventions). Autorisations gérées par *policies*.
- **Thème clair / sombre**, devise DZD, fuseau `Africa/Algiers`, interface en français.

## Stack technique

| Élément | Version |
| --- | --- |
| PHP | ^8.3 |
| Laravel | ^13 |
| Livewire + Volt | ^3.6 / ^1.7 |
| Tailwind CSS | ^3 |
| Vite | ^8 |
| Pest | ^5 |
| Base de données | SQLite (local) / MySQL (production) |

## Prérequis

- PHP 8.3 ou supérieur avec les extensions `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`,
  `json`, `bcmath`, `fileinfo`
- Composer 2
- Node.js 20+ et npm

## Installation locale

```sh
# 1. Dépendances PHP
composer install

# 2. Fichier d'environnement
cp .env.example .env
php artisan key:generate

# 3. Base de données SQLite
touch database/database.sqlite
php artisan migrate --seed

# 4. Dépendances front et compilation des assets
npm install
npm run build

# 5. Lancer le serveur de développement
php artisan serve
```

L'application est disponible sur <http://localhost:8000>.

En développement, `composer dev` lance en parallèle le serveur PHP, la file d'attente, les logs
et Vite.

### Compte administrateur

Le compte est créé par le seeder à partir des variables d'environnement :

```env
ADMIN_NAME="Votre nom"
ADMIN_EMAIL="vous@amantek-dz.com"
ADMIN_PASSWORD="un-mot-de-passe-solide"
```

Valeurs par défaut en local : `admin@amantek-dz.com` / `password`.

> **En production, définissez impérativement `ADMIN_PASSWORD` avant de lancer le seeder.**

## Tests

```sh
php artisan test
```

La suite couvre l'authentification, les rendez-vous, les clients, les devis (y compris l'impression),
les interventions, les paiements, ainsi qu'un test de fumée qui parcourt toutes les URLs de
l'application.

Formatage du code PHP :

```sh
vendor/bin/pint
```

---

# Déploiement sur `rend.amantek-dz.com`

Deux scénarios sont décrits : **VPS / serveur dédié** (recommandé, section A) et
**hébergement mutualisé cPanel** (section B, courant chez les hébergeurs algériens).

Dans les deux cas, l'étape préalable est la même : **le sous-domaine doit pointer vers le serveur**.

## 0. Créer le sous-domaine et le DNS

1. Dans le panneau de votre registrar (ou de votre hébergeur), ajoutez un enregistrement DNS :

   | Type | Nom | Valeur |
   | --- | --- | --- |
   | `A` | `rend` | *l'adresse IP de votre serveur* |

   Si vous utilisez Cloudflare, laissez l'enregistrement en mode **DNS only** (nuage gris) le temps
   du premier déploiement et de l'émission du certificat SSL.

2. Vérifiez la propagation :

   ```sh
   dig +short rend.amantek-dz.com
   ```

   La commande doit renvoyer l'IP de votre serveur (la propagation peut prendre quelques minutes
   à quelques heures).

## A. Déploiement sur VPS (Nginx + PHP-FPM + MySQL)

### A.1 Installer les paquets

Sur Ubuntu / Debian :

```sh
sudo apt update
sudo apt install -y nginx mysql-server php8.3-fpm php8.3-mysql php8.3-mbstring \
    php8.3-xml php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd unzip git
```

Installez ensuite Composer et Node.js :

```sh
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### A.2 Créer la base de données MySQL

```sh
sudo mysql
```

```sql
CREATE DATABASE amantek_rendezvous CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'amantek'@'localhost' IDENTIFIED BY 'un-mot-de-passe-solide';
GRANT ALL PRIVILEGES ON amantek_rendezvous.* TO 'amantek'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### A.3 Déployer le code

```sh
sudo mkdir -p /var/www/rend.amantek-dz.com
sudo chown -R $USER:www-data /var/www/rend.amantek-dz.com

git clone https://github.com/boulouzanacer/rendez-vous-amantek-dz.com.git \
    /var/www/rend.amantek-dz.com
cd /var/www/rend.amantek-dz.com
```

### A.4 Configurer l'environnement de production

```sh
cp .env.example .env
php artisan key:generate
```

Éditez `.env` et adaptez au minimum ces valeurs :

```env
APP_NAME="Amantek Rendez-vous"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rend.amantek-dz.com
APP_TIMEZONE=Africa/Algiers

COMPANY_NAME="Amantek"
COMPANY_PHONE="+213 ..."
COMPANY_EMAIL="contact@amantek-dz.com"
COMPANY_ADDRESS="Alger, Algérie"
COMPANY_CURRENCY=DZD
COMPANY_TAX_RATE=19

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=amantek_rendezvous
DB_USERNAME=amantek
DB_PASSWORD="un-mot-de-passe-solide"

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database

ADMIN_NAME="Votre nom"
ADMIN_EMAIL="vous@amantek-dz.com"
ADMIN_PASSWORD="un-mot-de-passe-solide"

LOG_LEVEL=warning
```

> `APP_DEBUG=false` est **obligatoire** en production : sinon les erreurs exposent vos données
> et vos identifiants.

### A.5 Installer les dépendances et les assets

```sh
composer install --no-dev --optimize-autoloader

npm ci
npm run build
```

> `npm run build` doit être exécuté sur le serveur (ou le dossier `public/build` doit être copié) :
> il est exclu de Git et n'existe donc pas après le `git clone`.

### A.6 Base de données, permissions et caches

```sh
php artisan migrate --force --seed

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

### A.7 Configurer Nginx

Créez `/etc/nginx/sites-available/rend.amantek-dz.com` :

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name rend.amantek-dz.com;
    root /var/www/rend.amantek-dz.com/public;

    index index.php;
    charset utf-8;
    client_max_body_size 20M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Activez le site et rechargez Nginx :

```sh
sudo ln -s /etc/nginx/sites-available/rend.amantek-dz.com /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

Testez avec `http://rend.amantek-dz.com` : l'application doit s'afficher.

> Le `DocumentRoot` pointe sur le dossier `public/`, **jamais** sur la racine du projet : sans cela,
> le fichier `.env` et vos données seraient accessibles publiquement.

### A.8 Activer le HTTPS (Let's Encrypt)

```sh
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d rend.amantek-dz.com
```

Certbot configure la redirection HTTP → HTTPS et renouvelle le certificat automatiquement tous
les 60 jours. Vérifiez le renouvellement :

```sh
sudo certbot renew --dry-run
```

### A.9 Tâches planifiées (rappels et maintenance)

Ajoutez une seule entrée cron pour l'utilisateur `www-data` :

```sh
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/rend.amantek-dz.com && php artisan schedule:run >> /dev/null 2>&1
```

### A.10 File d'attente (e-mails et traitements différés)

Créez le service systemd `/etc/systemd/system/amantek-worker.service` :

```ini
[Unit]
Description=Amantek Rendez-vous - file d'attente
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
WorkingDirectory=/var/www/rend.amantek-dz.com
ExecStart=/usr/bin/php /var/www/rend.amantek-dz.com/artisan queue:work --sleep=3 --tries=3

[Install]
WantedBy=multi-user.target
```

```sh
sudo systemctl daemon-reload
sudo systemctl enable --now amantek-worker
```

## B. Déploiement sur hébergement mutualisé (cPanel)

1. **Sous-domaine** : dans *Domains → Subdomains*, créez `rend` sur `amantek-dz.com` et
   indiquez comme racine un dossier dédié, par exemple `rend` (le dossier contenant le projet
   cloné se nommera `rend.amantek-dz.com`).
2. **Base de données** : dans *MySQL Databases*, créez la base et l'utilisateur, puis autorisez
   l'utilisateur sur la base. Notez le nom exact (souvent préfixé par votre compte).
3. **Code** : dans *Git™ Version Control*, clonez
   `https://github.com/boulouzanacer/rendez-vous-amantek-dz.com.git` dans
   `/home/VOTRE_COMPTE/rendez-vous-amantek-dz.com`.
4. **Composer** : ouvrez le Terminal cPanel (ou utilisez le bouton *Run Composer* du Git VC) :

   ```sh
   cd ~/rendez-vous-amantek-dz.com
   composer install --no-dev --optimize-autoloader
   ```

5. **Node** : si Node.js est disponible (nvm ou `Application Manager`), lancez `npm ci && npm run build`.
   Sinon, compilez les assets **sur votre machine** puis copiez le dossier `public/build` sur le
   serveur via le *File Manager*.
6. **`.env`** : créez le fichier `.env` à partir de `.env.example` via le *File Manager* (ou
   `cp .env.example .env`), renseignez `APP_ENV=production`, `APP_DEBUG=false`,
   `APP_URL=https://rend.amantek-dz.com` et les accès MySQL, puis :

   ```sh
   php artisan key:generate
   php artisan migrate --force --seed
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```

7. **Rediriger la racine du sous-domaine vers `public/`** : c'est l'étape la plus importante.
   Dans le *File Manager*, déplacez le contenu de `public/` (ou créez un lien) ou, plus simple,
   modifiez la racine du sous-domaine dans *Subdomains* pour pointer sur
   `rendez-vous-amantek-dz.com/public`.
   Si vous ne pouvez pas modifier la racine, utilisez un `.htaccess` à la racine du projet :

   ```apache
   RewriteEngine On
   RewriteRule ^(.*)$ public/$1 [L]
   ```

8. **PHP** : dans *MultiPHP Manager*, sélectionnez PHP 8.3 pour le sous-domaine.
9. **SSL** : dans *SSL/TLS Status*, activez *AutoSSL* pour `rend.amantek-dz.com`.
10. **Cron** : dans *Cron Jobs*, ajoutez une tâche toutes les minutes :

    ```sh
    cd /home/VOTRE_COMPTE/rendez-vous-amantek-dz.com && php artisan schedule:run >> /dev/null 2>&1
    ```

## C. Mises à jour après le premier déploiement

À chaque évolution publiée sur GitHub :

```sh
cd /var/www/rend.amantek-dz.com

git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```

## D. Points de contrôle

- [ ] `APP_DEBUG=false` et `APP_ENV=production` dans `.env`
- [ ] `APP_URL=https://rend.amantek-dz.com`
- [ ] `ADMIN_PASSWORD` défini avant le premier `--seed`
- [ ] La racine web pointe sur `public/`
- [ ] Le fichier `.env` n'est **pas** accessible depuis un navigateur
      (testez `https://rend.amantek-dz.com/.env` → doit renvoyer 403 ou 404)
- [ ] HTTPS actif et redirection HTTP → HTTPS effective
- [ ] `php artisan migrate --force` exécuté
- [ ] Caches `config` / `route` / `view` régénérés après chaque modification du `.env`
- [ ] Cron `schedule:run` actif
- [ ] Sauvegarde automatique de la base de données planifiée

---

# Publication sur GitHub

Le dépôt cible est <https://github.com/boulouzanacer/rendez-vous-amantek-dz.com.git>.

```sh
cd /chemin/vers/le/projet

git init
git branch -M main
git add .
git commit -m "Amantek Rendez-vous : application de gestion des rendez-vous et interventions"
git remote add origin https://github.com/boulouzanacer/rendez-vous-amantek-dz.com.git
git push -u origin main
```

Le fichier `.gitignore` exclut déjà `.env`, `vendor/`, `node_modules/`, `public/build/`, les
fichiers de log et les fichiers SQLite : aucun secret ni dossier volumineux n'est publié.

> Si le dépôt distant a été initialisé avec un fichier `README.md` ou une licence, faites d'abord
> `git pull --rebase origin main` avant de pousser.

## Licence

Projet privé — Amantek, Algérie.
