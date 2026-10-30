# MiniDesk 🎫

> **Exercice technique — Développeur FullStack**
> Durée estimée : **2 à 3 heures**. Délai de rendu : **72 heures** après réception via Github.

---

## Contexte

Tu intègres une équipe qui gère un parc applicatif en production.
En urgence, tu dois livrer un micro-service de ticketing interne utilisé par le support IT.

Une première ébauche de code existe déjà — elle contient des **bugs intentionnels**.  
Tu dois la **corriger**, la **compléter**, la **conteneuriser** et la **mettre en ligne**.

Ce contexte est volontairement proche du réel : TMA + développement nouveau, en même temps.

---

## Structure du projet

```
minidesk/
├── app/
│   ├── index.php             # Routeur principal (point d'entrée)
│   ├── TicketRepository.php  # Accès aux données (méthode manquante)
│   └── config.php            # Configuration / connexion PDO
├── db/
│   └── schema.sql            # Schéma MySQL + données de démo
├── nginx/
│   └── default.conf          # Configuration nginx
├── Dockerfile
├── docker-compose.yml
├── Makefile                  # Commandes utilitaires
├── .env.example
├── .gitignore
└── README.md                 ← tu es ici
```

---

## Missions

### Partie 1 — Audit & correction *(~45 min)*

Le code fourni contient **5 bugs** répartis dans plusieurs fichiers.

Ta mission :
- **Identifier et corriger** chacun d'eux
- **Documenter chaque correction** dans la section [Corrections](#corrections-apportées) ci-dessous :
  - Ce qui était cassé
  - Pourquoi c'est un problème
  - Comment tu l'as corrigé

> 💡 Les bugs couvrent des catégories différentes : sécurité, configuration Docker, HTTP. Lis tous les fichiers avant de toucher au code.

---

### Partie 2 — Développement *(~1h15)*

Implémente les fonctionnalités manquantes pour que l'API soit complète et fonctionnelle.

#### Endpoints attendus

| Méthode  | Route                    | Description                                              |
|----------|--------------------------|----------------------------------------------------------|
| `GET`    | `/`                      | ✅ Déjà implémenté — health check                        |
| `GET`    | `/tickets`               | ✅ Déjà implémenté — liste tous les tickets              |
| `POST`   | `/tickets`               | ✅ Déjà implémenté — crée un ticket                      |
| `GET`    | `/tickets/{id}`          | ✅ Déjà implémenté — détail d'un ticket                  |
| `PATCH`  | `/tickets/{id}/status`   | ✅ Implémenté en **v1.1.0** — met à jour le statut d'un ticket (`open`, `in_progress`, `closed`) |
| `PATCH`  | `/tickets/{id}/priority` | ✅ Implémenté en **v1.2.0** — met à jour la priorité d'un ticket (`low`, `medium`, `high`, `critical`) |

#### Contraintes techniques

- **Toujours utiliser PDO avec des requêtes préparées** — aucune interpolation directe de variable dans une requête SQL
- Valider les entrées utilisateur et retourner des **codes HTTP appropriés** (200, 201, 400, 404, 422...)
- Les réponses doivent être du **JSON valide** avec le bon `Content-Type`
- Respecter le schéma SQL fourni (champs, types, valeurs ENUM)

#### Corps attendu pour `POST /tickets`

```json
{
  "title": "Titre du ticket",
  "description": "Description optionnelle",
  "priority": "low|medium|high|critical"
}
```

Si `priority` est absente (ou `null`), elle vaut `medium`. Si `description` est absente (ou `null`), elle vaut une chaîne vide.

Limites (alignées sur `db/schema.sql`) :

| Champ         | Règle                                                                 |
|---------------|-----------------------------------------------------------------------|
| `title`       | obligatoire, chaîne non vide, 255 caractères maximum (espaces de bord retirés) |
| `description` | chaîne, 65 535 octets maximum                                         |
| `priority`    | `low`, `medium`, `high` ou `critical`                                 |

Si une règle n'est pas respectée, l'API répond `422` avec le détail par champ :

```json
{
  "error": "Données invalides.",
  "details": {
    "title": "Le champ \"title\" est obligatoire.",
    "priority": "Priorité invalide. Valeurs acceptées : low, medium, high, critical."
  }
}
```

#### Corps attendu pour `PATCH /tickets/{id}/status`

```json
{
  "status": "open|in_progress|closed"
}
```

#### Corps attendu pour `PATCH /tickets/{id}/priority`

```json
{
  "priority": "low|medium|high|critical"
}
```

Réponses : `200` avec le ticket mis à jour, `404` si le ticket n'existe pas, `422` si `priority` est absente ou invalide.

---

### Partie 3 — Ops & livraison *(~45 min)*

- La stack doit démarrer avec **`docker compose up -d`** (ou `make up`) sans intervention manuelle
- Ajouter un **healthcheck** sur le service `php` ou `nginx` dans le `docker-compose.yml`
- Le fichier `.env.example` doit être complet — **aucun secret ne doit apparaître dans le dépôt Git**
- Exposer l'application sur une **URL publique** accessible au moment du rendu

  Exemples de solutions acceptées (liste non exhaustive) :
  - [Ngrok](https://ngrok.com/) (tunnel temporaire, gratuit)
  - [Railway](https://railway.app/) / [Render](https://render.com/) (PaaS gratuit)
  - VPS personnel avec un sous-domaine

  > L'URL doit répondre au moment où tu envoies ton rendu. Pas besoin que ce soit en production permanente.

---

### Partie 4 — Bonus *(si le temps le permet)*

Non obligatoire. Choisis **ce qui te parle le plus** — ne te force pas à tout faire.

| Bonus | Description |
|-------|-------------|
| 🧪 Tests | Un ou plusieurs tests PHPUnit **ou** un script `test.sh` curl documenté |
| 🖥️ Frontend | Une page HTML ou React qui liste les tickets et permet d'en créer un |
| 🔁 CI | Un workflow GitHub Actions qui vérifie que la stack monte (`docker compose up`) |
| 📝 Réflexion | 200 mots max dans la section [Réflexion](#réflexion) sur ce que tu ferais avec plus de temps |

---

## Rendu

1. **Fork** ce dépôt sur ton compte GitHub (dépôt public)
2. Travaille sur ta fork — commits atomiques et lisibles attendus
3. Renseigne les sections ci-dessous avant de soumettre
4. Envoie le **lien de ton dépôt GitHub + l'URL publique** par email

---

## Sections à compléter

### Corrections apportées

> Remplis cette section au fil de tes corrections. Une ligne par bug.

| # | Fichier | Ce qui était cassé | Pourquoi c'est un problème | Correction apportée |
|---|---------|--------------------|-----------------------------|----------------------|
| 1 | `app/config.php` | Mot de passe MySQL écrit en clair dans le code (`define('DB_PASS', …)`). | Il est visible par tous ceux qui lisent le dépôt et reste dans l'historique Git. | Mot de passe retiré du code, lu depuis un Docker secret (`secrets/*.txt`, non versionnés). L'ancien mot de passe est remplacé. |
| 2 | `app/TicketRepository.php` | `findById()` utilise l'id directement dans la requête SQL. | Risque d'injection SQL. | Requête préparée avec paramètre lié (`:id`). |
| 3 | `app/index.php` | Le header `Content-Type` JSON n'était jamais envoyé. | Les clients (curl, fetch, Postman) ne savent pas qu'ils reçoivent du JSON. | `header('Content-Type: application/json; charset=utf-8')` ajouté une seule fois, avant toutes les routes. |
| 4 | `Dockerfile` | L'image `php:8.2-apache` embarque Apache, alors que nginx attend PHP-FPM sur le port 9000. | Nginx ne peut pas joindre PHP, l'application ne répond pas. | Image `php:8.2-fpm`. |
| 5 | `docker-compose.yml` | Le port était mappé en `8080:8080`, alors que nginx écoute sur le port 80 dans le conteneur. | Le port 8080 de l'hôte ne mène à rien, l'application est inaccessible. | Mapping corrigé en `8080:80`. |

---

### Lancer le projet

```bash
# 1. Cloner le dépôt
git clone https://github.com/<ton-compte>/minidesk.git
cd minidesk

# 2. Configurer l'environnement
cp .env.example .env
# (renseigne les valeurs dans .env, il ne contient aucun mot de passe)

# Les mots de passe sont des Docker secrets (fichiers non versionnés)
cp secrets/db_password.txt.example secrets/db_password.txt
cp secrets/db_root_password.txt.example secrets/db_root_password.txt
# (remplace les valeurs `changeme` avant le premier démarrage)

# 3. Démarrer la stack
make up
# ou : docker compose up -d --build

# 4. Vérifier que tout fonctionne
docker compose run --rm phpunit    # tests PHPUnit (dans Docker, sur la base de la stack)
make test                          # raccourci de la commande ci-dessus
make test-smoke                    # appels curl sur l'API (stack démarrée)
```

> **URL publique** : *https://minidesk.gectou4.com*

---

### Déploiement OVH (hébergement mutualisé)

Un push sur la branche `prod` lance la CI, puis déploie le contenu de `app/` par FTPS sur l'hébergement OVH (`.github/workflows/deploy.yml`).

**Réglages GitHub** (environnement `production`) :
- secrets : `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
- variables : `PROD_URL`, `FTP_APP_DIR` (dossier racine du site, avec un `/` final)

**Une seule fois côté OVH** : choisir PHP 8.2 ou plus, créer la base MySQL, puis importer le schéma sans ses deux premières lignes :

```bash
sed '/^CREATE DATABASE/d;/^USE /d' db/schema.sql > schema-ovh.sql
```

**Limites** : pas de retour arrière ni de migration de base automatiques. Le mot de passe de la base est dans `env.json` sur le serveur, hors du dossier web.

---

### Choix techniques

> Explique ici tes choix significatifs (architecture, outils, compromis) — quelques lignes suffisent.

- **Healthchecks** : `php` vérifie que PHP-FPM accepte les connexions sur le port 9000, `nginx` vérifie que `GET /` répond (donc nginx, PHP et la base fonctionnent ensemble), `db` fait un `mysqladmin ping` en TCP (`127.0.0.1`) pour ne pas être déclaré prêt pendant l'initialisation de MySQL.
- **Robustesse** : les variables de connexion sont obligatoires (`require_env`, `DB_PORT` ajouté), et si la configuration, le secret ou la base sont indisponibles l'API répond 503 en JSON sans détail (le détail va dans les logs).
- **Encodage** : le serveur MySQL est lancé en `utf8mb4` / `utf8mb4_unicode_ci` (`command` du service `db`), et `LANG=C.UTF-8` fait lire `schema.sql` en UTF-8 à l'import. Sans cela, les accents étaient double-encodés (`bloquÃ©es`).
- **Images** : nginx 1.30 et MySQL 8.4 (LTS), versions supportées. L'option `--character-set-client-handshake` n'existe plus en MySQL 8.4, d'où `LANG=C.UTF-8`.
- **CI** : le workflow `.github/workflows/ci.yml` (GitHub Actions) construit la stack avec des mots de passe jetables, attend que les healthchecks soient verts, puis teste l'API avec `curl` (`/`, `/tickets`, `/tickets/{id}`, 404, création).
- **Tests PHPUnit** : `docker compose run --rm phpunit` (ou `make test`) lance PHPUnit dans un conteneur dédié (`Dockerfile.test`, service `phpunit` du profil `test`, donc absent de `docker compose up`), relié à la base de la stack. Chaque test de `TicketRepository` tourne dans une transaction annulée à la fin, donc les données ne changent pas. `TicketRepository` accepte un `PDO` en paramètre (défaut : `get_db()`) pour rester testable. Les tests de `config.php` n'ont pas besoin de la base.
- **Changement de statut** : les statuts sont définis par l'enum `TicketStatus` (`app/TicketStatus.php` : `open`, `in_progress`, `closed`, alignés sur `db/schema.sql`). `PATCH /tickets/{id}/status` convertit le corps avec `TicketStatus::tryFrom()` et répond 422 si le statut est invalide. `updateStatus(int, TicketStatus)` (requête préparée avec `WHERE id`) renvoie `false` si le ticket n'existe pas, ce qui donne un 404.
- **Méthode non supportée** : si la route existe mais pas avec la méthode HTTP utilisée (par exemple `DELETE /tickets/1`), l'API répond `405` avec `{"error": "Méthode non autorisée."}` et un en-tête `Allow` listant les méthodes acceptées. Une route inconnue reste un `404`.
- **Erreurs inattendues** : `index.php` installe un `set_exception_handler()`. Toute exception non rattrapée pendant le routage (erreur PDO, `TypeError`...) est écrite dans les logs (message, fichier, trace) et l'API répond `500` avec `{"error": "Erreur interne du serveur"}`, sans détail technique. Si la connexion à la base échoue à l'initialisation, la réponse reste un `503`.
- **Configuration sans variables d'environnement** : sur un hébergement mutualisé, `config.php` lit un fichier `env.json` placé au-dessus du dossier de l'application (hors du dossier web). Les variables déjà définies (Docker) restent prioritaires.
- **Apache** : `app/.htaccess` est l'équivalent de `nginx/default.conf` pour l'hébergement mutualisé (routage vers `index.php`, fichiers sensibles interdits). Il est sans effet sous nginx.

---

### Réflexion

> *(Bonus — 200 mots max)* Qu'aurais-tu fait différemment avec plus de temps ?
> Authentification, scalabilité, CI/CD, observabilité, automatisation, évolution ou connexion avec un autre service... ce que tu juges pertinent.

*(à renseigner)*

---

## Critères d'évaluation

| Dimension | Ce qu'on regarde |
|-----------|-----------------|
| **Correction de bugs** | Tous trouvés ? Explications claires ? |
| **Qualité du code** | Lisibilité, sécurité, séparation des responsabilités |
| **Maîtrise Docker** | Stack qui monte du premier coup, env propre |
| **Méthode Git** | Commits atomiques et lisibles |
| **Documentation** | README utile, sections remplies honnêtement |
| **Livraison** | L'URL publique fonctionne-t-elle ? |
| **Jugement** | Choix pragmatiques et justifiés dans le temps imparti |

---

*Bonne chance 🚀*
