<?php

/**
 * MiniDesk - Configuration
 *
 * Le mot de passe de la base n'est jamais écrit dans le code :
 * il est lu depuis un Docker secret (variable MYSQL_PASSWORD_FILE).
 */

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'minidesk');
define('DB_USER', getenv('MYSQL_USER') ?: 'minidesk_user');

define('APP_ENV', getenv('APP_ENV') ?: 'production');

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

/**
 * Retourne une connexion PDO partagée (singleton simple).
 */
function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, read_secret('MYSQL_PASSWORD'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}
