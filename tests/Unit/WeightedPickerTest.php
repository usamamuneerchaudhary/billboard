<?php

namespace Billboard\Tests\Unit;

use Billboard\Delivery\WeightedPicker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WeightedPickerTest extends TestCase
{
    #[Test]
    public function it_uses_weights_to_choose(): void
    {
        $this->assertSame(['a'], WeightedPicker::pick(['a' => 1, 'b' => 99], 1, fn () => 0.0));
        $this->assertSame(['b'], WeightedPicker::pick(['a' => 1, 'b' => 99], 1, fn () => 0.5));
        $this->assertSame(['b'], WeightedPicker::pick(['a' => 1, 'b' => 1], 1, fn () => 0.9999999));
    }

    #[Test]
    public function it_never_repeats_an_item(): void
    {
        $picked = WeightedPicker::pick(['a' => 5, 'b' => 5, 'c' => 5], 3);

        $this->assertCount(3, array_unique($picked));
        $this->assertCount(2, WeightedPicker::pick(['a' => 1, 'b' => 1], 5));
    }

    #[Test]
    public function it_handles_empty_input(): void
    {
        $this->assertSame([], WeightedPicker::pick([], 2));
        $this->assertSame([], WeightedPicker::pick(['a' => 1], 0));
    }

    #[Test]
    public function it_roughly_follows_the_weights_over_many_picks(): void
    {
        mt_srand(42);
        $hits = ['a' => 0, 'b' => 0];

        for ($i = 0; $i < 40000; $i++) {
            $hits[WeightedPicker::pick(['a' => 30, 'b' => 10], 1)[0]]++;
        }

        $this->assertEqualsWithDelta(0.75, $hits['a'] / 40000, 0.015);
    }
}
