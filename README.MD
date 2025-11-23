
# ThumbUp 🎨📊

ThumbUp est un outil interne pour gérer des **commandes de miniatures YouTube** pour des clients (YouTubeurs) :
- Les **admins** gèrent les clients, les commandes, le temps passé, les paiements…
- Les **clients** peuvent créer des commandes, uploader des assets, suivre le statut et récupérer leurs miniatures.

> 🔗 Démo en production : https://thumb-up.byevti.com/  
> ⚠️ En prod, **la confirmation d’email ne fonctionne pas encore** pour la création de compte (les mails ne partent pas).

---

## 🧱 Stack technique

- **PHP** ≥ 8.2
- **Symfony** 7+
- **Doctrine ORM**
- **Twig**
- **SQLite** en développement
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
git clone https://github.com/EvrardThibaud/thumbup.git thumbup
cd thumbup
````

### 2. Installer les dépendances PHP

```bash
composer install
```

En dev, les dépendances de debug (profiler, maker, fixtures…) sont installées.

### 3. Générer le schéma + migrations


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

---

## 🌐 Environnement de production

* URL : [https://thumb-up.byevti.com/](https://thumb-up.byevti.com/)
* Base de données : **MariaDB** (configurée via `DATABASE_URL` côté Infomaniak)
* PHP : 8.3

Limitations actuelles en prod :

* La création de compte fonctionne, mais il est impossible de se connecter car l’email ne peut pas être validé.
→ Le système de vérification fonctionne en développement via Mailtrap, mais pas encore configuré en production (les emails ne sont pas envoyés).

---

## 💳 Configuration des paiements PayPal (sandbox)

Le projet utilise l’API **PayPal Orders** en mode **sandbox** pour les paiements.

### 1. Créer / configurer le compte développeur

1. Aller sur : [https://developer.paypal.com/tools/sandbox/](https://developer.paypal.com/tools/sandbox/)
2. Se connecter (ou créer un compte PayPal Developer).
3. Créer une **application sandbox** pour obtenir :

   * un **Client ID**
   * un **Client Secret**

Ensuite, mettre ces infos dans `.env.local` :

```env
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID="..."
PAYPAL_CLIENT_SECRET="..."
```

### 2. Créer un compte sandbox “payer”

Important :
Le compte utilisé dans `PAYPAL_CLIENT_ID` / `PAYPAL_CLIENT_SECRET` correspond au **compte marchand**, c’est celui qui **reçoit** l’argent.

Pour tester un vrai paiement :

1. Dans la section **Sandbox > Accounts**, créer un **compte personnel sandbox** (un faux utilisateur PayPal).
2. Lors du checkout, se connecter avec **ce** faux compte personnel (payer).
3. Le compte marchand (lié au Client ID/Secret) ne doit **pas** être le même que celui qui paie.


---

## ✉️ Configuration des emails (validation, reset password, notifications)

Plusieurs fonctionnalités reposent sur l’envoi d’emails :

* **Validation d’email** lors de l’inscription
* **Mot de passe oublié** (reset password)
* **Notification admin** quand une nouvelle commande est créée

En dev, tout est pensé pour fonctionner avec **Mailtrap** (sandbox email).

### 1. Créer un compte Mailtrap

1. Aller sur : [https://mailtrap.io](https://mailtrap.io)
2. Créer un compte gratuit.
3. Dans l’interface, aller dans la section **Sandbox**.
4. Récupérer le **DSN Symfony** proposé (ou les identifiants SMTP).

Ça ressemble à quelque chose comme :

```env
MAILER_DSN="smtp://<USER>:<PASSWORD>@sandbox.smtp.mailtrap.io:2525"
```

(Coller cette ligne dans ton `.env.local`.)

### 2. Résultat

Une fois `MAILER_DSN` configuré :

* La **validation d’email** à l’inscription fonctionne en dev (le lien de confirmation arrive dans Mailtrap).
* Le **forgot password** pourra aussi envoyer un lien.
* Les notifications d’orders fonctionnent : l'admin reçoit un email dès qu’un client crée une nouvelle commande.

---