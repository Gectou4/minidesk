<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests de l'API en HTTP réel, via le serveur intégré de PHP (voir TestServer).
 * Les tickets créés sont supprimés à la fin de chaque test.
 */
class ApiTest extends TestCase
{
    private static TestServer $server;

    /** @var int[] */
    private array $created = [];

    public static function setUpBeforeClass(): void
    {
        self::$server = new TestServer();
        self::$server->start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    protected function tearDown(): void
    {
        if ($this->created !== []) {
            $marqueurs = implode(',', array_fill(0, count($this->created), '?'));
            get_db()->prepare("DELETE FROM tickets WHERE id IN ($marqueurs)")->execute($this->created);
            $this->created = [];
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function creer(array $data = ['title' => 'Ticket HTTP']): int
    {
        $reponse = self::$server->request('POST', '/tickets', $data);
        $this->assertSame(201, $reponse['status'], $reponse['body']);

        $id = (int) $reponse['json']['id'];
        $this->created[] = $id;

        return $id;
    }

    // ------------------------------------------------------------------
    // Racine, JSON, routes inconnues, méthodes non supportées
    // ------------------------------------------------------------------

    public function testLaRacineRenvoieLaVersionDuChangelog(): void
    {
        $reponse = self::$server->request('GET', '/');

        $this->assertSame(200, $reponse['status']);
        $this->assertSame('ok', $reponse['json']['status']);
        $this->assertSame('MiniDesk API', $reponse['json']['service']);

        preg_match('/^## \[([0-9]+\.[0-9]+\.[0-9]+)\]/m', (string) file_get_contents(__DIR__ . '/../../CHANGELOG.md'), $m);
        $this->assertSame($m[1], $reponse['json']['version'], 'La version de GET / doit être celle du CHANGELOG.');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function reponsesVariees(): array
    {
        return [
            'succès'          => ['GET', '/'],
            'route inconnue'  => ['GET', '/nimporte'],
            'méthode refusée' => ['DELETE', '/tickets/1'],
            'données invalides' => ['GET', '/tickets?limit=0'],
        ];
    }

    #[DataProvider('reponsesVariees')]
    public function testToutesLesReponsesSontEnJson(string $methode, string $chemin): void
    {
        $reponse = self::$server->request($methode, $chemin);

        $this->assertStringStartsWith('application/json', $reponse['headers']['content-type']);
        $this->assertIsArray($reponse['json']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function routesInconnues(): array
    {
        return [
            'chemin inconnu'       => ['/nimporte'],
            'id non numérique'     => ['/tickets/abc'],
            'sous-route inconnue'  => ['/tickets/1/autre'],
            'slash final'          => ['/tickets/'],
        ];
    }

    #[DataProvider('routesInconnues')]
    public function testUneRouteInconnueRepond404(string $chemin): void
    {
        $reponse = self::$server->request('GET', $chemin);

        $this->assertSame(404, $reponse['status']);
        $this->assertSame('Route introuvable.', $reponse['json']['error']);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function methodesNonSupportees(): array
    {
        return [
            'DELETE ticket'    => ['DELETE', '/tickets/1', 'GET'],
            'PUT statut'       => ['PUT', '/tickets/1/status', 'PATCH'],
            'GET priorité'     => ['GET', '/tickets/1/priority', 'PATCH'],
            'POST racine'      => ['POST', '/', 'GET'],
            'PATCH collection' => ['PATCH', '/tickets', 'GET, POST'],
            'ticket inexistant' => ['DELETE', '/tickets/999999999', 'GET'],
        ];
    }

    #[DataProvider('methodesNonSupportees')]
    public function testUneMethodeNonSupporteeRepond405AvecAllow(string $methode, string $chemin, string $allow): void
    {
        $reponse = self::$server->request($methode, $chemin);

        $this->assertSame(405, $reponse['status']);
        $this->assertSame($allow, $reponse['headers']['allow']);
        $this->assertSame('Méthode non autorisée.', $reponse['json']['error']);
    }

    // ------------------------------------------------------------------
    // GET /tickets : liste, filtres, pagination
    // ------------------------------------------------------------------

    public function testLaListeContientLeTicketEtLEnTeteTotal(): void
    {
        $id = $this->creer();

        $reponse = self::$server->request('GET', '/tickets');

        $this->assertSame(200, $reponse['status']);
        $this->assertContains($id, array_map('intval', array_column($reponse['json'], 'id')));
        $this->assertMatchesRegularExpression('/^[0-9]+$/', $reponse['headers']['x-total-count']);
        $this->assertSame(count($reponse['json']), (int) $reponse['headers']['x-total-count']);
    }

    public function testLaListeSeFiltreParStatutEtPriorite(): void
    {
        $cible = $this->creer(['title' => 'Cible', 'priority' => 'critical']);
        $autre = $this->creer(['title' => 'Autre', 'priority' => 'critical']);
        self::$server->request('PATCH', "/tickets/$cible/status", ['status' => 'closed']);

        $reponse = self::$server->request('GET', '/tickets?status=closed&priority=critical');

        $ids = array_map('intval', array_column($reponse['json'], 'id'));
        $this->assertSame(200, $reponse['status']);
        $this->assertContains($cible, $ids);
        $this->assertNotContains($autre, $ids);
        $this->assertSame(['closed'], array_values(array_unique(array_column($reponse['json'], 'status'))));
        $this->assertSame(['critical'], array_values(array_unique(array_column($reponse['json'], 'priority'))));
    }

    public function testLaListeSePagineEtLEnTeteTotalIgnoreLaPagination(): void
    {
        $a = $this->creer(['title' => 'A']);
        $b = $this->creer(['title' => 'B']);
        $c = $this->creer(['title' => 'C']);

        $page1 = self::$server->request('GET', '/tickets?limit=2');
        $page2 = self::$server->request('GET', '/tickets?limit=2&offset=1');

        $this->assertSame([$c, $b], array_map('intval', array_column($page1['json'], 'id')));
        $this->assertSame([$b, $a], array_map('intval', array_column($page2['json'], 'id')));
        $this->assertGreaterThanOrEqual(3, (int) $page1['headers']['x-total-count']);
        $this->assertSame($page1['headers']['x-total-count'], $page2['headers']['x-total-count']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function parametresDeListeInvalides(): array
    {
        return [
            'statut inconnu'      => ['status=nimporte', 'status'],
            'statut en tableau'   => ['status[]=open', 'status'],
            'priorité inconnue'   => ['priority=urgent', 'priority'],
            'limit à zéro'        => ['limit=0', 'limit'],
            'limit trop grande'   => ['limit=101', 'limit'],
            'limit texte'         => ['limit=abc', 'limit'],
            'offset négatif'      => ['offset=-1', 'offset'],
        ];
    }

    #[DataProvider('parametresDeListeInvalides')]
    public function testLaListeRefuseUnParametreInvalide(string $query, string $champ): void
    {
        $reponse = self::$server->request('GET', "/tickets?$query");

        $this->assertSame(422, $reponse['status']);
        $this->assertSame([$champ], array_keys($reponse['json']['details']));
    }

    // ------------------------------------------------------------------
    // GET /tickets/{id} et POST /tickets
    // ------------------------------------------------------------------

    public function testLeDetailDUnTicket(): void
    {
        $id = $this->creer(['title' => 'Détail']);

        $trouve   = self::$server->request('GET', "/tickets/$id");
        $inconnu  = self::$server->request('GET', '/tickets/999999999');

        $this->assertSame(200, $trouve['status']);
        $this->assertSame('Détail', $trouve['json']['title']);
        $this->assertSame(404, $inconnu['status']);
        $this->assertSame('Ticket introuvable.', $inconnu['json']['error']);
    }

    public function testLaCreationAppliqueLesValeursParDefaut(): void
    {
        $id = $this->creer(['title' => '  Titre à nettoyer  ']);

        $ticket = self::$server->request('GET', "/tickets/$id")['json'];

        $this->assertSame('Titre à nettoyer', $ticket['title']);
        $this->assertSame('medium', $ticket['priority']);
        $this->assertSame('open', $ticket['status']);
        $this->assertSame('', $ticket['description']);
    }

    public function testLaCreationCumuleLesErreursParChamp(): void
    {
        $reponse = self::$server->request('POST', '/tickets', ['title' => '', 'description' => 42, 'priority' => 'urgent']);

        $this->assertSame(422, $reponse['status']);
        $this->assertSame('Données invalides.', $reponse['json']['error']);
        $this->assertSame(['title', 'description', 'priority'], array_keys($reponse['json']['details']));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function corpsIllisibles(): array
    {
        return [
            'corps vide'      => [''],
            'pas du JSON'     => ['pas du json'],
            'JSON scalaire'   => ['42'],
        ];
    }

    #[DataProvider('corpsIllisibles')]
    public function testUnCorpsIllisibleRepond400(string $corps): void
    {
        $reponse = self::$server->request('POST', '/tickets', $corps);

        $this->assertSame(400, $reponse['status']);
        $this->assertSame('Corps JSON invalide ou manquant.', $reponse['json']['error']);
    }

    public function testUnCorpsDePlusDe512KioRepond413(): void
    {
        $reponse = self::$server->request('POST', '/tickets', str_repeat('a', 524289));

        $this->assertSame(413, $reponse['status']);
        $this->assertStringStartsWith('application/json', $reponse['headers']['content-type']);
    }

    public function testUnCorpsDeExactement512KioEstLuParPhp(): void
    {
        // Pas du JSON, donc 400 : preuve que la limite de 413 n'est franchie qu'au-delà.
        $reponse = self::$server->request('POST', '/tickets', str_repeat('a', 524288));

        $this->assertSame(400, $reponse['status']);
    }

    // ------------------------------------------------------------------
    // PATCH /tickets/{id}/status et /priority
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function champsPatchables(): array
    {
        return [
            'statut'   => ['status', 'in_progress', 'nimporte_quoi'],
            'priorité' => ['priority', 'high', 'urgent'],
        ];
    }

    #[DataProvider('champsPatchables')]
    public function testPatchMetAJourLeChamp(string $champ, string $valide, string $invalide): void
    {
        $id = $this->creer();

        $reponse = self::$server->request('PATCH', "/tickets/$id/$champ", [$champ => $valide]);

        $this->assertSame(200, $reponse['status']);
        $this->assertSame($valide, $reponse['json'][$champ]);
        $this->assertSame($valide, self::$server->request('GET', "/tickets/$id")['json'][$champ]);
    }

    #[DataProvider('champsPatchables')]
    public function testPatchRefuseUneValeurInvalideOuAbsente(string $champ, string $valide, string $invalide): void
    {
        $id = $this->creer();

        foreach ([[$champ => $invalide], [], [$champ => 123], [$champ => ['x']]] as $corps) {
            $reponse = self::$server->request('PATCH', "/tickets/$id/$champ", $corps === [] ? '{}' : $corps);
            $this->assertSame(422, $reponse['status'], json_encode($corps));
        }
    }

    #[DataProvider('champsPatchables')]
    public function testPatchRepond404SiLeTicketNExistePas(string $champ, string $valide, string $invalide): void
    {
        $inconnu = self::$server->request('PATCH', "/tickets/999999999/$champ", [$champ => $valide]);
        // L'existence est vérifiée avant la valeur : un ticket inconnu reste un 404 même avec une valeur invalide.
        $inconnuInvalide = self::$server->request('PATCH', "/tickets/999999999/$champ", [$champ => $invalide]);

        $this->assertSame(404, $inconnu['status']);
        $this->assertSame(404, $inconnuInvalide['status']);
    }

    #[DataProvider('champsPatchables')]
    public function testPatchRepond400SiLeCorpsEstIllisible(string $champ, string $valide, string $invalide): void
    {
        $id = $this->creer();

        $reponse = self::$server->request('PATCH', "/tickets/$id/$champ", 'pas du json');

        $this->assertSame(400, $reponse['status']);
    }

    public function testPatchStatutNeModifiePasLaPriorite(): void
    {
        $id = $this->creer(['title' => 'Indépendance', 'priority' => 'low']);

        self::$server->request('PATCH', "/tickets/$id/status", ['status' => 'closed']);

        $ticket = self::$server->request('GET', "/tickets/$id")['json'];
        $this->assertSame('closed', $ticket['status']);
        $this->assertSame('low', $ticket['priority']);
    }
}
