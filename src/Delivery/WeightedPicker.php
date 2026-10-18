<?php

namespace Billboard\Delivery;

final class WeightedPicker
{
    /**
     * Choose identifiers by weighted random sampling without replacement.
     *
     * @param  array<string, int>  $weights  Keyed by item identifier.
     * @param  callable|null  $random  Returns a float in [0, 1). Injected for tests.
     * @return array<int, string> The chosen identifiers, in pick order.
     */
    public static function pick(array $weights, int $count, ?callable $random = null): array
    {
        $random ??= static fn (): float => mt_rand() / (mt_getrandmax() + 1);

        $pool = [];

        foreach ($weights as $key => $weight) {
            $pool[(string) $key] = max(1, (int) $weight);
        }

        $chosen = [];
        $count = max(0, $count);

        while ($count-- > 0 && $pool !== []) {
            $target = $random() * array_sum($pool);

            foreach ($pool as $key => $weight) {
                $target -= $weight;

                if ($target < 0) {
                    break;
                }
            }

            $chosen[] = (string) $key;
            unset($pool[$key]);
        }

        return $chosen;
    }
}
