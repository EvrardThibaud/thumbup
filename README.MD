# ThumbUp — Gestion de miniatures YouTube (Symfony/Twig)

## 🎯 Objectifs d’apprentissage

* **Routing → Controller → Response** (cycle HTTP) et **Twig** (layouts, composants, formulaires)
* **Forms & Validator** : création/édition sécurisée, messages d’erreur
* **Doctrine ORM** : entités, relations (OneToMany/ManyToOne), requêtes, **migrations**
* **Security** : authentification (form login), rôles, restrictions d’accès
* **Symfony UX** : Turbo/LiveComponent (petites interactions), **Autocomplete**, **Dropzone**, **Chart.js**
* **Agrégations & reporting** avec Doctrine pour alimenter des **dashboards**
* **(Optionnel plus tard)** : **API Platform** (+ Serializer) pour un portail client / intégrations, **Mercure** (temps réel)

---

## 🧾 Brief du projet

Outil interne pour freelance « miniatures YouTube » permettant de :

* Gérer des **Clients** (YouTubeurs) et leurs **Commandes** (miniatures)
* Suivre le **temps** passé par commande (TimeEntries)
* Enregistrer les **prix** pratiqués et le **reste à encaisser** par client (suivi des soldes/impayés **sans** génération de facture ni envoi d’emails)
* Visualiser des **statistiques** (miniatures/mois, temps/semaine, CA estimé, répartition statuts)
* *(Optionnel)* Ouvrir un **portail client** pour déposer une demande et suivre son statut

Modèle minimum :

* `User` (login, rôles)
* `Client`
* `Order` (client, titre, brief, prix, statut, échéance)
* `TimeEntry` (order, minutes, note)
* *(Optionnel)* `Payment` (client, amount, paidAt, method) pour suivre les encaissements — **sans facture ni email**

---

## 🗺️ Roadmap — Tâches identifiées

### Semaine 1 — **MVP Admin CRUD** (atteignable)

**^ objectif**

* [ ] **Setup** : projet webapp, SQLite, Bootstrap (CDN), UX packs installés
* [ ] **Security** : `User` + login form, rôle `ROLE_ADMIN`
* [ ] **Entités** : `Client`, `Order`, `TimeEntry` (+ relations, migrations)
* [ ] **CRUD Twig** : listes, création, édition, suppression (forms + validations)
* [ ] **UX** : Autocomplete pour choisir un client, Dropzone pour brief (fichier), action Live pour changer le statut d’une commande
* [ ] **Dashboard minimal** :

  * [ ] KPI : miniatures du mois, temps total semaine, **CA estimé** (∑ price des orders « Livrés »)
  * [ ] 1 graphe Chart.js : **miniatures par mois**

**bonus :**

* [ ] Pagination + filtres (par client, statut, période)
* [ ] Export CSV des commandes

---

### Phase 2 — **Suivi financier simple & Stats+** (sans facture/email)

* [ ] **Champ solde/impayé** côté client (calculé à partir des commandes « Facturables »/« Payées » sans générer de facture)
* [ ] **(Optionnel)** Entité `Payment` pour consigner un encaissement manuel (montant, date, méthode)
* [ ] Page **Impayés** : liste des clients avec montant dû + actions manuelles (marquer comme réglé)
* [ ] Dashboard+ :

  * [ ] Graphe **temps/semaine** (ligne)
  * [ ] Graphe **répartition statuts** (doughnut)
  * [ ] Graphe **CA estimé par mois** (barres)

**bonus :**

* [ ] Règles de transitions de statut (workflow simple)
* [ ] Import CSV de clients

---

### Phase 3 — **Portail Client & API (optionnel)**

* [ ] Rôle `ROLE_CLIENT` + restriction (un client ne voit que ses données)
* [ ] **Portail** : formulaire de **demande** (Order), suivi statut, accès à ses commandes
* [ ] **API Platform** sur `Client`/`Order`/`TimeEntry` (lecture/écriture sécurisée)
* [ ] **Mercure** pour maj de statut en temps réel (optionnel)

**bonus :**

* [ ] Intégration d’un paiement en ligne (ex. PayPal) pour régler un solde
