---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Document fields with a PHPDoc above the rules key
Scramble turns a `/** ... */` placed directly above a key in `rules()` into that field's `description` in the OpenAPI schema:

    /** At least 8 characters, with an upper and lower case letter, a number and a symbol. */
    'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()],

It must sit above the key. A comment inside the rules array (between rules) is ignored.

This matters for constraints Scramble cannot read off the rule objects. It infers `required`, `maxLength`, `format: email` and `password_confirmation` (from `confirmed`) on its own, but a `Rules\Password` object documents as a bare string — the policy is invisible unless you write it out.

Describe anything a frontend developer would otherwise have to read the PHP to learn; skip the obvious ones.
