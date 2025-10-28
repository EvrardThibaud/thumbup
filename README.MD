# ThumbUp — Gestion de miniatures YouTube (Symfony/Twig)

Un mini-ERP pour freelance : suivre clients, commandes de miniatures, temps passé, facturation, paiements et stats.

## Stack

* **Symfony 6.4+** (Twig, Doctrine, Security)
* **Symfony UX**: Turbo, LiveComponent, Autocomplete, Dropzone, Chart.js
* **Bootstrap 5 (CDN)** pour les formulaires et le layout
* **DB**: SQLite (dev) / MySQL ou PostgreSQL (prod)

---

## Prérequis

* macOS/Linux/WSL
* PHP 8.2+
* Composer
* Symfony CLI
* Git & GitHub CLI (`gh`)

```bash
brew install php composer symfony-cli gh
```

---

## Démarrage rapide

```bash
# 1) Créer le projet (déjà fait)
symfony new thumbup --webapp
cd thumbup

# 2) DB (SQLite dev par défaut) et serveur
yes | php bin/console doctrine:database:create || true
symfony server:start -d  # http://127.0.0.1:8000
```

---

## Configuration UI

**`config/packages/twig.yaml`**

```yaml
twig:
  form_themes: ['bootstrap_5_layout.html.twig']
```

**`templates/base.html.twig`** – ajoute Bootstrap via CDN dans `<head>` avant `</head>`

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5/dist/css/bootstrap.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5/dist/js/bootstrap.bundle.min.js"></script>
```

---

## Modèle de données (MVP)

* `User` (id, email, password, roles, name)
* `Client` (id, name, channelUrl, contactEmail)
* `Order` (id, client, title, brief, priceHT, status, dueAt, createdAt, updatedAt)
* `TimeEntry` (id, order, minutes, note, createdAt)

> Étapes suivantes : `Invoice`, `InvoiceLine`, `Payment`, `Asset` (uploads), etc.

---

## Génération initiale (MakerBundle)

```bash
composer require symfony/maker-bundle --dev

# Sécurité (login + users)
php bin/console make:user         # => User (email, roles, password)
php bin/console make:auth         # => LoginFormAuthenticator

# Entités métier
php bin/console make:entity Client
php bin/console make:entity Order
php bin/console make:entity TimeEntry

# Migrations
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# CRUD (Twig)
php bin/console make:crud Client
php bin/console make:crud Order
php bin/console make:crud TimeEntry
```

---

## Tableaux de bord & Stats

* Installer **Symfony UX Chart.js** (déjà plus haut).
* Créer un contrôleur `DashboardController` (+ route `/dashboard`).
* Fournir au template des séries (CA/mois, temps/sem, impayés) à partir de requêtes Doctrine.

Exemples de graphes à implémenter :

* **Barres** : nombre de miniatures par mois
* **Ligne** : temps total par semaine
* **Doughnut** : répartition statuts des commandes

---

## Uploads & Assets (optionnel MVP+)

* `composer require vich/uploader-bundle`
* Permet d'associer des briefs, références et livrables aux `Order`.

---

## Emails & Rappels (optionnel MVP+)

* `composer require symfony/mailer symfony/messenger`
* Gabarits Twig d'email (livraison, facture, rappel d'échéance)
* Transport Messenger Doctrine pour l'async

---

## Sécurité & Accès

* Rôles : `ROLE_ADMIN` (toi) et `ROLE_CLIENT` (portail client plus tard)
* Voters pour restreindre l'accès aux commandes d'un client

---

## Scripts utiles

```bash
# Lancer serveur & ouvrir
symfony server:start -d && symfony open:local

# Fixtures (si ajoutées plus tard)
php bin/console doctrine:fixtures:load --no-interaction

# Vérifier migrations en attente
php bin/console doctrine:migrations:status
```

---

## Roadmap

1. **Auth + CRUD**: Users, Clients, Orders, TimeEntries
2. **Dashboard**: 3 KPI + 2 charts (mois/semaine)
3. **Qualité**: validation, pagination, filtres
4. **Uploads**: briefs/réfs
5. **Finance**: invoices, PDF (Dompdf/KnpSnappy)
6. **Rappels**: emails + Messenger
7. **Portail client** (option): soumissions & suivi

---

## Déploiement (aperçu)

* Variables d’env (`APP_ENV=prod`, DSN Mailer, DB)
* `php bin/console cache:clear --env=prod`
* Migration DB sur le serveur
* Web server: Nginx/Apache pointant sur `/public`

---

## Licence

MIT (ou à définir)
