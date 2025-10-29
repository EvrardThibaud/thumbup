# ThumbUp — Gestion de miniatures YouTube (Symfony/Twig)

## 🎯 Objectifs d’apprentissage

* **Routing → Controller → Response** (cycle HTTP) et **Twig** (layouts, composants, formulaires)
* **Forms & Validator** : création/édition sécurisée, messages d’erreur
* **Doctrine ORM** : entités, relations (OneToMany/ManyToOne), requêtes, **migrations**
* **Security** : authentification (form login), rôles, restrictions d’accès
* **Symfony UX** : Turbo/LiveComponent (petites interactions), **Autocomplete**, **Dropzone**, **Chart.js**
* **Agrégations & reporting** avec Doctrine pour alimenter des **dashboards**
* **API Platform** (+ Serializer) pour un portail client / intégrations, **Mercure** (temps réel)

---

## 🧾 Brief du projet

Outil interne pour freelance « miniatures YouTube » permettant de :

* Gérer des **Clients** (YouTubeurs) et leurs **Commandes** (miniatures)
* Suivre le **temps** passé par commande (TimeEntries)
* Enregistrer les **prix** pratiqués et le **reste à encaisser** par client (suivi des soldes/impayés, génération de facture ? envoi d’emails ?)
* Visualiser des **statistiques** (miniatures/mois, temps/semaine, CA estimé, répartition statuts)
* Ouvrir un **portail client** pour déposer une demande et suivre son statut

Modèle minimum :

* `User` (login, rôles)
* `Client`
* `Order` (client, titre, brief, prix, statut, échéance)
* `TimeEntry` (order, minutes, note)
* `Payment` (client, amount, paidAt, method) pour suivre les encaissements

---
## 🗺️ Roadmap — Tâches identifiées

* [x] **Setup** : projet webapp, SQLite, Bootstrap (CDN), UX packs
* [x] **Security** : `User` + login form, rôle `ROLE_ADMIN`, `/admin/*` protégé
* [x] **Entités** : `Client`, `Order`, `TimeEntry` (+ relations)
* [x] **Migrations** : génération & exécution
* [x] **CRUD Twig** : Clients / Orders / TimeEntries
* [x] **Timestamps** : `createdAt`/`updatedAt` sur `Order`
* [x] **Status enum** : `todo|doing|delivered|paid` (PHP Enum + select)
* [x] **Navbar & routes** : liens admin cohérents (`/admin/...`)
* [x] **Autocomplete** : `Order.client` et `TimeEntry.relatedOrder`
* [x] **Dashboard minimal** : 3 KPI (miniatures/mois, temps/semaine, CA estimé) + 1 graphe (orders/mois)
* [x] **Affichages utiles** :
  * [x] Prix formaté en € (listes/vues)
  * [x] Total temps par commande (min & h) + tableau des TimeEntries
* [x] **Filtres Orders** (index) :
  * [x] par **Client** (select)
  * [x] par **Statut** (enum)
  * [x] par **Période** (date min/max sur `createdAt` ou `dueAt`)
* [x] **Recherche texte** : champ libre sur `title`
* [x] **Pagination** : paginer l’index (conserver filtres dans la query string)
* [x] **Tri** : titre, client, dates
* [x] **Solde par client** : total dû basé sur orders `delivered|paid` (sans facture/email)
* [ ] **Payment** : encaissements manuels (montant, date, méthode)
* [x] **Page Impayés** : clients + montants dus + action « marqué réglé »
* [ ] **Dash+** :
  * [ ] Ligne **temps/semaine**
  * [ ] Doughnut **répartition statuts**
  * [ ] Barres **CA estimé par mois**
* [ ] Rôle `ROLE_CLIENT` + Voters (un client voit ses données)
* [ ] **Portail client** : créer une demande (Order), suivre statut, voir ses commandes
* [ ] **API Platform** : exposer `Client`/`Order`/`TimeEntry` (lecture/écriture sécurisée)
* [ ] **Mercure** : MAJ de statut en temps réel
* [ ] **Dropzone** sur brief (upload fichiers)
* [ ] Export CSV 
  * [ ] Orders
  * [x] Clients
* [ ] Import CSV (Clients)
* [ ] Workflow de statuts (règles/guard)
* [ ] Report bug
* [ ] Responsivité
* [ ] Production
