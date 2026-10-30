# Changelog

Toutes les évolutions notables de l'API MiniDesk. Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et les versions suivent [SemVer](https://semver.org/lang/fr/). La version courante est renvoyée par `GET /` (`app/index.php`).

## [1.3.1] - 2026-10-30

### Corrigé
- Un corps de requête de plus de 512 Kio répond `413` en JSON (nginx via `client_max_body_size`, PHP via `MAX_BODY_BYTES` pour Apache), au lieu d'être lu en entier.

## [1.3.0] - 2026-10-30

### Ajouté
- `GET /tickets` accepte les filtres `status` et `priority` et la pagination `limit` (1 à 100) et `offset` ; un paramètre invalide répond `422`.
- En-tête `X-Total-Count` sur `GET /tickets` : nombre de tickets correspondant aux filtres, hors pagination.

### Modifié
- `GET /tickets` trie à date de création égale par id décroissant, pour une pagination stable.

## [1.2.3] - 2026-10-30

### Corrigé
- Une méthode HTTP non supportée sur une route existante répond `405` avec l'en-tête `Allow` (au lieu d'un `404`).

## [1.2.2] - 2026-10-30

### Corrigé
- Toute exception non rattrapée pendant le routage (erreur PDO, `TypeError`...) répond `500` en JSON, sans détail technique ; la trace est écrite dans les logs.

## [1.2.1] - 2026-10-30

### Corrigé
- `POST /tickets` valide `description` (chaîne, 65 535 octets maximum) et la longueur de `title` (255 caractères maximum) et répond `422` au lieu d'une erreur SQL.

## [1.2.0] - 2026-10-30

### Ajouté
- `PATCH /tickets/{id}/priority` pour changer la priorité d'un ticket.
- Enum `TicketPriority` (`low`, `medium`, `high`, `critical`).

### Modifié
- `POST /tickets` : `priority` vaut `medium` si elle est absente ; une priorité invalide répond `422`.
- `POST /tickets` : `title` absent, vide ou non textuel répond `422` avec le détail par champ.

## [1.1.0] - 2026-10-30

### Ajouté
- `PATCH /tickets/{id}/status` pour changer le statut d'un ticket (`open`, `in_progress`, `closed`), avec l'enum `TicketStatus`.
- Tests PHPUnit (`docker compose run --rm phpunit`), CI GitHub Actions et déploiement sur la branche `prod`.
- Healthchecks Docker (`php`, `nginx`, `db`), lecture de la configuration depuis `env.json` et `.htaccess` pour Apache.

### Corrigé
- Bugs #1 à #5 : mot de passe MySQL retiré du code, requête préparée dans `findById`, en-tête `Content-Type` JSON, image `php:8.2-fpm`, port nginx `8080:80`.
- Réponse `503` en JSON si la configuration ou la base est indisponible ; encodage `utf8mb4` forcé sur MySQL.

### Modifié
- nginx 1.30 et MySQL 8.4 (LTS).

## [1.0.0] - 2026-06-05

### Ajouté
- Version initiale de l'exercice : `GET /`, `GET /tickets`, `POST /tickets`, `GET /tickets/{id}`.
