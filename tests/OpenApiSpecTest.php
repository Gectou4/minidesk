<?php

use PHPUnit\Framework\TestCase;

/**
 * La spécification app/api-spec.json est générée par swagger-php à partir des
 * attributs de app/ApiDoc.php et des enums. Ces tests garantissent qu'elle est
 * à jour, valide et cohérente avec le code.
 */
class OpenApiSpecTest extends TestCase
{
    private const RACINE = __DIR__ . '/..';

    /** @return array<string, mixed> */
    private static function committee(): array
    {
        return json_decode((string) file_get_contents(self::RACINE . '/app/api-spec.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testLaSpecificationCommitteeEstAJourEtValide(): void
    {
        $sortie = tempnam(sys_get_temp_dir(), 'openapi') . '.json';
        // Mêmes options que la cible `make openapi` du Makefile.
        $commande = sprintf(
            '%s %s %s -o %s --version 3.0.0 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(self::RACINE . '/vendor/bin/openapi'),
            escapeshellarg(self::RACINE . '/app'),
            escapeshellarg($sortie)
        );

        exec($commande, $lignes, $code);
        $journal = implode("\n", $lignes);

        try {
            $this->assertSame(0, $code, $journal);
            $this->assertStringNotContainsString('[warning]', $journal, 'Spécification invalide : ' . $journal);
            $this->assertStringNotContainsString('[error]', $journal, 'Spécification invalide : ' . $journal);
            $this->assertEquals(
                json_decode((string) file_get_contents($sortie), true, 512, JSON_THROW_ON_ERROR),
                self::committee(),
                'app/api-spec.json est périmé : lancer `make openapi` et le commiter.'
            );
        } finally {
            @unlink($sortie);
        }
    }

    public function testLesEnumsDeLaSpecificationCorrespondentAuxEnumsPhp(): void
    {
        $schemas = self::committee()['components']['schemas'];

        $this->assertSame(TicketStatus::values(), $schemas['TicketStatus']['enum']);
        $this->assertSame(TicketPriority::values(), $schemas['TicketPriority']['enum']);
    }

    public function testLaVersionDeLaSpecificationEstCelleDuChangelog(): void
    {
        preg_match('/^## \[([0-9]+\.[0-9]+\.[0-9]+)\]/m', (string) file_get_contents(self::RACINE . '/CHANGELOG.md'), $m);

        $this->assertSame($m[1], self::committee()['info']['version'], 'Mettre à jour la version dans app/ApiDoc.php puis `make openapi`.');
    }

    public function testChaqueOperationADesReponsesEtUnIdentifiantUnique(): void
    {
        $ids = [];
        foreach (self::committee()['paths'] as $chemin => $operations) {
            foreach ($operations as $methode => $operation) {
                $this->assertNotEmpty($operation['responses'], "$methode $chemin sans réponses documentées");
                $this->assertNotEmpty($operation['summary'] ?? '', "$methode $chemin sans résumé");
                $ids[] = $operation['operationId'];
            }
        }

        $this->assertSame($ids, array_values(array_unique($ids)), 'operationId en double');
    }
}
