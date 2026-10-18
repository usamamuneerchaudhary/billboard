<?php

namespace Billboard\Rules;

use Billboard\Support\UrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeUrl implements ValidationRule
{
    /**
     * Reject values that are not a safe ad destination URL.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || ! UrlGuard::isSafe($value)) {
            $fail(__('Enter a full http:// or https:// address, or a path on this site that starts with /.'));
        }
    }
}
