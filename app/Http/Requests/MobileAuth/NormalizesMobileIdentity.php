<?php

namespace App\Http\Requests\MobileAuth;

/**
 * Addresses and handles are compared lower-cased and trimmed, so they are
 * lower-cased and trimmed before validation -- `Ana@Example.com` and
 * `ana@example.com` are one account, and the unique rules see what will be
 * stored.
 */
trait NormalizesMobileIdentity
{
    protected function prepareForValidation(): void
    {
        foreach (['email', 'username'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => mb_strtolower(trim($value))]);
            }
        }
    }
}
