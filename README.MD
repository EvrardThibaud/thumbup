````markdown
# ThumbUp 🎨📊

ThumbUp est un outil interne pour gérer des **commandes de miniatures YouTube** pour des clients (YouTubeurs) :
- Les **admins** gèrent les clients, les commandes, le temps passé, les paiements…
- Les **clients** peuvent créer des commandes, uploader des assets, suivre le statut et récupérer leurs miniatures.

> 💻 Version actuelle : projet surtout pensé pour un **rendu scolaire**.  
> 🔗 Démo en production : https://thumb-up.byevti.com/  
> ⚠️ En prod, **la confirmation d’email ne fonctionne pas encore** pour la création de compte (les mails ne partent pas).

---

## 🧱 Stack technique

- **PHP** ≥ 8.2
- **Symfony** 7+
- **Doctrine ORM**
- **Twig** (pas de front SPA, que du serveur-side)
- **SQLite** en développement (plus simple à lancer)
- **MariaDB/MySQL** en production
- Bundles principaux :
  - symfony/security-bundle
  - symfony/ux (Stimulus, Turbo, Autocomplete, Chart.js)
  - doctrine/doctrine-bundle, doctrine/migrations-bundle
  - liip/imagine-bundle (prévisualisation des images)
  - vich/uploader-bundle (upload de fichiers)

---

## ⚙️ Fonctionnalités principales

- Gestion des **Users** (ROLE_ADMIN / ROLE_CLIENT) et des **Clients**
- Gestion des **Orders** (commandes de miniatures) avec statut (CREATED, ACCEPTED, DOING, DELIVERED, REVISION, FINISHED…)
- Upload d’**assets client** (OrderAsset) et de **miniatures finales** (Thumbnail)
- Suivi du **temps passé** par commande (TimeEntry)
- Suivi basique des **paiements** (Payment)
- Invitations clients via **token** pour créer un compte lié à un Client
- Dashboard admin / client (statuts, dernières miniatures, etc.)

---

## 🚀 Lancer le projet en développement

### 1. Cloner le dépôt

```bash
git clone <URL_DU_REPO> thumbup
cd thumbup
````

### 2. Installer les dépendances PHP

```bash
composer install
```

En dev, les dépendances de debug (profiler, maker, fixtures…) sont installées.

### 3. Configurer l’environnement

Par défaut, le projet utilise **SQLite en développement**.
Vérifie que dans `.env` ou `.env.local` tu as quelque chose de ce genre :

```env
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

(Si tu utilises un autre DSN, adapte les commandes de DB en conséquence.)

### 4. Générer le schéma + migrations

Si tu veux utiliser les migrations :

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

Avec SQLite, la DB sera créée automatiquement dans `var/`.

### 5. Charger les DataFixtures

Les fixtures créent notamment un compte admin de test.

```bash
php bin/console doctrine:fixtures:load
```

> Identifiants admin créés par les fixtures :
>
> * **Email** : `admin@example.com`
> * **Mot de passe** : `adminpass`

### 6. Lancer le serveur de dev

Avec la CLI Symfony (recommandé) :

```bash
symfony server:start
```

Ou avec PHP natif :

```bash
php -S 127.0.0.1:8000 -t public
```

Le site sera alors accessible sur :
👉 [http://127.0.0.1:8000](http://127.0.0.1:8000)

---

## 🔑 Accès & rôles

### Compte admin (fixtures)

Utilise ces identifiants en dev :

* Email : `admin@example.com`
* Mot de passe : `adminpass`

L’admin peut :

* voir tous les clients et leurs commandes
* créer / éditer les commandes
* uploader les miniatures finales
* consulter les paiements / time entries
* gérer les utilisateurs

### Comptes clients

En dev, les fixtures peuvent aussi créer des **clients** et des **users ROLE_CLIENT** (selon configuration actuelle des fixtures).
Sinon, un admin peut créer un Client et un User depuis le back-office.

---

## 🌐 Environnement de production

* URL : [https://thumb-up.byevti.com/](https://thumb-up.byevti.com/)
* Base de données : **MariaDB** (configurée via `DATABASE_URL` côté Infomaniak)
* PHP : 8.3

Limitations actuelles en prod (important pour le rendu scolaire) :

* La **confirmation d’email** lors de l’inscription ne fonctionne pas encore (problème de configuration d’envoi de mails).
* Le reste du site (auth, back-office, création d’orders, upload de fichiers…) est fonctionnel.

---

## 🧪 Quelques commandes utiles

Recréer le schéma à partir des entités (en dev uniquement, à manipuler avec précaution) :

```bash
php bin/console doctrine:schema:update --force
```

Voir les migrations disponibles :

```bash
php bin/console doctrine:migrations:list
```

Générer une nouvelle migration après modification des entités :

```bash
php bin/console doctrine:migrations:diff
```

---

## 📁 Structure générale (simplifiée)

* `src/Entity`

  * `User` : utilisateur (admin / client)
  * `Client` : client (YouTubeur)
  * `Order` : commande de miniature
  * `TimeEntry` : temps passé sur une commande
  * `OrderAsset` : fichiers / assets fournis par le client
  * `Thumbnail` : miniatures finales
  * `Payment` : paiement lié à un utilisateur / client
  * `Invitation` : token d’invitation pour créer un compte
  * `YoutubeChannel` : chaînes YouTube liées à un client
* `src/Controller`

  * `HomeController` : dashboard / landing
  * `OrderController`, `ClientController`, `UserController`, etc.
  * `RegistrationController` : inscription + verification email
* `templates/` : vues Twig (dark theme, Bootstrap)

---

## ✅ État actuel (pour le rendu)

* Fonctionnel :

  * Authentification (login / logout)
  * Compte admin via fixtures
  * Gestion des clients, orders, assets, thumbnails
  * Dashboard admin / client
  * Suivi du temps et des statuts de commandes
* Partiellement fonctionnel :

  * Système d’inscription + email de vérification (**OK en logique**, mais email non fonctionnel en prod).
* À améliorer plus tard :

  * Gestion complète des paiements (PayPal/Stripe)
  * Emailing robuste en prod
  * Plus de dashboards / stats

---

```
```
