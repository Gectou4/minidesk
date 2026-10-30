<?php

/**
 * MiniDesk - Configuration
 *
 * Les paramètres de connexion non sensibles viennent de l'environnement.
 * Le mot de passe est lu depuis un Docker secret (variable MYSQL_PASSWORD_FILE).
 *
 * Sur un hébergement sans variables d'environnement (mutualisé), un fichier
 * env.json placé au-dessus du dossier de l'application les fournit.
 */

/**
 * Charge un fichier JSON {"NOM": "valeur"} dans l'environnement.
 * Sans effet si le fichier n'existe pas, et ne remplace jamais une variable déjà définie.
 */
function load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $values = json_decode((string) file_get_contents($path), true);
    if (!is_array($values)) {
        error_log(sprintf('Fichier de configuration invalide : %s', $path));
        return;
    }

    foreach ($values as $name => $value) {
        if (is_scalar($value) && getenv((string) $name) === false) {
            putenv($name . '=' . $value);
        }
    }
}

load_env_file(__DIR__ . '/../env.json');

function require_env(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new \RuntimeException(sprintf("Variable d'environnement manquante : %s", $name));
    }
    return $value;
}

/**
 * Lit un secret depuis le fichier indiqué par <NAME>_FILE (Docker secrets),
 * avec repli sur la variable <NAME> pour un usage local sans Docker.
 */
function read_secret(string $name): string
{
    $file = getenv($name . '_FILE');
    if ($file !== false && $file !== '') {
        if (!is_readable($file)) {
            throw new \RuntimeException(sprintf('Fichier de secret illisible pour %s', $name));
        }
        $value = trim((string) file_get_contents($file));
    } else {
        $value = (string) getenv($name);
    }

    if ($value === '') {
        throw new \RuntimeException(sprintf('Secret introuvable : %s', $name));
    }
    return $value;
}

define('APP_ENV', getenv('APP_ENV') ?: 'production');

/**
 * Retourne une connexion PDO partagée (singleton simple).
 */
function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;port=%s;charset=utf8mb4',
            require_env('DB_HOST'),
            require_env('MYSQL_DATABASE'),
            require_env('DB_PORT')
        );
        $pdo = new PDO($dsn, require_env('MYSQL_USER'), read_secret('MYSQL_PASSWORD'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}
