<?php

/**
 * Lance le serveur web intégré de PHP (`php -S`) sur app/index.php pour tester
 * l'API en HTTP réel : routage, codes, en-têtes et corps (php://input).
 *
 * Le serveur partage la base de la stack. Contrairement à TicketRepositoryTest
 * (transaction annulée), ses requêtes sont validées : les tests doivent
 * supprimer les tickets qu'ils créent.
 */
final class TestServer
{
    /** @var resource|null */
    private $process = null;

    private int $port = 0;

    /**
     * @param array<string, string> $env Variables d'environnement ajoutées ou remplacées
     *                                   pour le serveur (ex. simuler une base cassée)
     */
    public function __construct(private array $env = [])
    {
    }

    public function start(): void
    {
        $this->port = self::freePort();
        $app  = dirname(__DIR__, 2) . '/app';
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $this->port, '-t', $app, $app . '/index.php'],
            [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes,
            null,
            array_merge(getenv(), $this->env)
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Impossible de lancer le serveur de test PHP.');
        }
        $this->process = $process;

        for ($i = 0; $i < 100; $i++) {
            $socket = @fsockopen('127.0.0.1', $this->port, $errno, $error, 0.1);
            if ($socket !== false) {
                fclose($socket);
                return;
            }
            usleep(50_000);
        }

        $this->stop();
        throw new RuntimeException("Le serveur de test PHP n'a pas démarré sur le port {$this->port}.");
    }

    public function stop(): void
    {
        if ($this->process !== null) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }

    /**
     * Envoie une requête (corps JSON par défaut) et renvoie la réponse décodée.
     *
     * @param array<string, mixed>|string|null $body Tableau encodé en JSON, ou corps brut
     * @return array{status: int, headers: array<string, string>, body: string, json: mixed}
     */
    public function request(string $method, string $path, array|string|null $body = null): array
    {
        $content = is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : (string) $body;

        $context = stream_context_create(['http' => [
            'method'        => $method,
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $content,
            'ignore_errors' => true,
            'timeout'       => 10,
        ]]);

        $raw = file_get_contents('http://127.0.0.1:' . $this->port . $path, false, $context);
        if ($raw === false) {
            throw new RuntimeException("Pas de réponse du serveur de test pour $method $path.");
        }

        $status  = 0;
        $headers = [];
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
                continue;
            }
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $headers[strtolower(trim($name))] = trim($value);
        }

        return ['status' => $status, 'headers' => $headers, 'body' => $raw, 'json' => json_decode($raw, true)];
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new RuntimeException("Aucun port libre : $error");
        }
        $name = (string) stream_socket_get_name($socket, false);
        $port = (int) substr($name, (int) strrpos($name, ':') + 1);
        fclose($socket);

        return $port;
    }
}
