<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Documentation de l'API en HTTP réel : GET /docs (Swagger UI), GET /openapi
 * (spécification) et cohérence entre la spécification et le routeur.
 */
class ApiDocTest extends TestCase
{
    private static TestServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = new TestServer();
        self::$server->start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    public function testDocsAfficheSwaggerUiAvecUneCspStricte(): void
    {
        $reponse = self::$server->request('GET', '/docs');

        $this->assertSame(200, $reponse['status']);
        $this->assertStringStartsWith('text/html', $reponse['headers']['content-type']);
        $this->assertStringContainsString('swagger-ui-bundle.js', $reponse['body']);
        $this->assertStringContainsString("url: 'openapi'", $reponse['body']);

        $csp = $reponse['headers']['content-security-policy'];
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringNotContainsString("script-src 'unsafe-inline'", $csp);
        $this->assertSame(1, preg_match("/script-src 'nonce-([^']+)'/", $csp, $m), $csp);
        $this->assertStringContainsString('nonce="' . $m[1] . '"', $reponse['body'], 'Le script d\'initialisation doit porter le nonce de la CSP.');
        $this->assertSame(2, substr_count($reponse['body'], 'integrity="sha384-'), 'Les ressources du CDN doivent être contrôlées par SRI.');
    }

    public function testLeNonceChangeAChaqueRequete(): void
    {
        $a = self::$server->request('GET', '/docs')['headers']['content-security-policy'];
        $b = self::$server->request('GET', '/docs')['headers']['content-security-policy'];

        $this->assertNotSame($a, $b);
    }

    public function testOpenapiRenvoieLaSpecificationEnJson(): void
    {
        $reponse = self::$server->request('GET', '/openapi');

        $this->assertSame(200, $reponse['status']);
        $this->assertStringStartsWith('application/json', $reponse['headers']['content-type']);
        $this->assertStringStartsWith('3.', $reponse['json']['openapi']);
        $this->assertSame('MiniDesk API', $reponse['json']['info']['title']);
        $this->assertEquals(
            json_decode((string) file_get_contents(__DIR__ . '/../../app/api-spec.json'), true),
            $reponse['json']
        );
    }

    #[DataProvider('cheminsDeDocumentation')]
    public function testLaDocumentationNeSupporteQueGet(string $chemin): void
    {
        $reponse = self::$server->request('POST', $chemin, []);

        $this->assertSame(405, $reponse['status']);
        $this->assertSame('GET', $reponse['headers']['allow']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cheminsDeDocumentation(): array
    {
        return ['/docs' => ['/docs'], '/openapi' => ['/openapi']];
    }

    public function testLaDocumentationResteDisponibleSiLaBaseEstInjoignable(): void
    {
        $serveur = new TestServer(['DB_HOST' => '127.0.0.1', 'DB_PORT' => '1']);
        $serveur->start();

        try {
            $this->assertSame(200, $serveur->request('GET', '/docs')['status']);
            $this->assertSame(200, $serveur->request('GET', '/openapi')['status']);
            $this->assertSame(503, $serveur->request('GET', '/tickets')['status']);
        } finally {
            $serveur->stop();
        }
    }

    // ------------------------------------------------------------------
    // La spécification décrit bien les routes du routeur
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string, string[]}> chemin documenté => méthodes documentées
     */
    public static function cheminsDocumentes(): array
    {
        $spec = json_decode((string) file_get_contents(__DIR__ . '/../../app/api-spec.json'), true);

        $cas = [];
        foreach ($spec['paths'] as $chemin => $operations) {
            $cas[$chemin] = [$chemin, array_map('strtoupper', array_keys($operations))];
        }

        return $cas;
    }

    /**
     * @param string[] $methodes
     */
    #[DataProvider('cheminsDocumentes')]
    public function testLesMethodesDocumenteesSontCellesDuRouteur(string $chemin, array $methodes): void
    {
        // Une méthode non supportée répond 405 avec la liste exacte des méthodes de la route.
        $reponse = self::$server->request('OPTIONS', str_replace('{id}', '1', $chemin));

        $this->assertSame(405, $reponse['status']);
        $autorisees = array_map('trim', explode(',', $reponse['headers']['allow']));
        sort($autorisees);
        sort($methodes);
        $this->assertSame($methodes, $autorisees, "Les méthodes documentées pour $chemin diffèrent de celles du routeur.");
    }

    /**
     * @param string[] $methodes
     */
    #[DataProvider('cheminsDocumentes')]
    public function testChaqueOperationDocumenteeExisteDansLeRouteur(string $chemin, array $methodes): void
    {
        foreach ($methodes as $methode) {
            $reponse = self::$server->request($methode, str_replace('{id}', '1', $chemin), in_array($methode, ['POST', 'PATCH'], true) ? [] : null);

            $this->assertNotSame(405, $reponse['status'], "$methode $chemin est documentée mais refusée.");
            $this->assertNotSame('Route introuvable.', $reponse['json']['error'] ?? null, "$methode $chemin est documentée mais inconnue du routeur.");
        }
    }
}
