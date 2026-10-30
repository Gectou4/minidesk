<?php

use PHPUnit\Framework\TestCase;

class TicketStatusTest extends TestCase
{
    public function testTryFromRetourneLeBonCase(): void
    {
        $this->assertSame(TicketStatus::Open, TicketStatus::tryFrom('open'));
        $this->assertSame(TicketStatus::InProgress, TicketStatus::tryFrom('in_progress'));
        $this->assertSame(TicketStatus::Closed, TicketStatus::tryFrom('closed'));
    }

    public function testTryFromRetourneNullPourUneValeurInvalide(): void
    {
        $this->assertNull(TicketStatus::tryFrom('nimporte_quoi'));
        $this->assertNull(TicketStatus::tryFrom(''));
        $this->assertNull(TicketStatus::tryFrom('OPEN'));
    }

    public function testValuesListeLesStatutsDansLOrdre(): void
    {
        $this->assertSame(['open', 'in_progress', 'closed'], TicketStatus::values());
    }

    public function testLesValeursCorrespondentAuSchemaSql(): void
    {
        $schema = file_get_contents(__DIR__ . '/../db/schema.sql');
        $this->assertMatchesRegularExpression('/status\s+ENUM\(([^)]*)\)/i', $schema);

        preg_match('/status\s+ENUM\(([^)]*)\)/i', $schema, $m);
        $valeurs = array_map(
            static fn (string $v): string => trim($v, " '\""),
            explode(',', $m[1])
        );

        $this->assertSame($valeurs, TicketStatus::values());
    }
}
