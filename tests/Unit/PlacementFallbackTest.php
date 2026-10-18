<?php

namespace Billboard\Tests\Unit;

use Billboard\Placements\Placement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PlacementFallbackTest extends TestCase
{
    /**
     * Read fallback HTML stored as a plain string.
     */
    #[Test]
    public function it_reads_a_string_fallback(): void
    {
        $placement = new Placement('sidebar', ['fallback' => '<p>Advertise here</p>']);

        $this->assertSame('<p>Advertise here</p>', $placement->fallback());
    }

    /**
     * Read fallback HTML from the control panel code field.
     */
    #[Test]
    public function it_reads_a_code_field_fallback(): void
    {
        $placement = new Placement('sidebar', [
            'fallback' => ['code' => '<p>House ad</p>', 'mode' => 'htmlmixed'],
        ]);

        $this->assertSame('<p>House ad</p>', $placement->fallback());
    }

    /**
     * Treat an empty code field as no fallback.
     */
    #[Test]
    public function it_treats_an_empty_code_field_as_missing(): void
    {
        $placement = new Placement('sidebar', [
            'fallback' => ['code' => null, 'mode' => 'htmlmixed'],
        ]);

        $this->assertNull($placement->fallback());
    }
}
