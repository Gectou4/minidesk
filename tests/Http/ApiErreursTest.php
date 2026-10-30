<?php

use PHPUnit\Framework\TestCase;

/**
 * Erreurs serveur de l'API : chaque test lance son propre serveur avec une
 * configuration volontairement cassée (variables d'environnement surchargées).
 */
class ApiErreursTest extends TestCase
{
    private ?TestServer $server = null;

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->server = null;
    }

    /**
     * @param array<string, string> $env
     */
    private function demarrer(array $env): TestServer
    {
        $this->server = new TestServer($env);
        $this->server->start();

        return $this->server;
    }

    public function testUneBaseInjoignableRepond503EnJson(): void
    {
        // Port 1 : connexion refusée immédiatement.
        $serveur = $this->demarrer(['DB_HOST' => '127.0.0.1', 'DB_PORT' => '1']);

        $reponse = $serveur->request('GET', '/tickets');

        $this->assertSame(503, $reponse['status']);
        $this->assertStringStartsWith('application/json', $reponse['headers']['content-type']);
        $this->assertSame('Service momentanément indisponible', $reponse['json']['error']);
    }

    public function testUneErreurSqlEnCoursDeRequeteRepond500SansDetailTechnique(): void
    {
        // information_schema se connecte sans problème mais n'a pas de table `tickets`.
        $serveur = $this->demarrer(['MYSQL_DATABASE' => 'information_schema']);

        $reponse = $serveur->request('GET', '/tickets');

        $this->assertSame(500, $reponse['status']);
        $this->assertStringStartsWith('application/json', $reponse['headers']['content-type']);
        $this->assertSame('Erreur interne du serveur', $reponse['json']['error']);
        $this->assertStringNotContainsString('SQLSTATE', $reponse['body']);
        $this->assertStringNotContainsString('tickets', $reponse['body']);
    }

    public function testLesRoutesSansAccesALaBaseRestentDisponiblesApresUneErreurSql(): void
    {
        $serveur = $this->demarrer(['MYSQL_DATABASE' => 'information_schema']);

        $serveur->request('GET', '/tickets');
        $reponse = $serveur->request('GET', '/');

        $this->assertSame(200, $reponse['status']);
    }
}
