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
| `PATCH`  | `/tickets/{id}/status`   | ❌ **À compléter** — met à jour le statut d'un ticket    |

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

#### Corps attendu pour `PATCH /tickets/{id}/status`

```json
{
  "status": "open|in_progress|closed"
}
```

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
make test
# ou : curl http://localhost:8080/
```

> **URL publique** : *(à renseigner)*

---

### Choix techniques

> Explique ici tes choix significatifs (architecture, outils, compromis) — quelques lignes suffisent.

- **Healthchecks** : `php` vérifie que PHP-FPM accepte les connexions sur le port 9000, `nginx` vérifie que `GET /` répond (donc nginx, PHP et la base fonctionnent ensemble), `db` fait un `mysqladmin ping` en TCP (`127.0.0.1`) pour ne pas être déclaré prêt pendant l'initialisation de MySQL.
- **Robustesse** : les variables de connexion sont obligatoires (`require_env`, `DB_PORT` ajouté), et si la configuration, le secret ou la base sont indisponibles l'API répond 503 en JSON sans détail (le détail va dans les logs).
- **Encodage** : le serveur MySQL est lancé en `utf8mb4` / `utf8mb4_unicode_ci` (`command` du service `db`). Sans cela, l'import de `schema.sql` double-encodait les accents (`bloquÃ©es`).
- **CI** : le workflow `.github/workflows/ci.yml` (GitHub Actions) construit la stack avec des mots de passe jetables, attend que les healthchecks soient verts, puis teste l'API avec `curl` (`/`, `/tickets`, `/tickets/{id}`, 404, création).

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
