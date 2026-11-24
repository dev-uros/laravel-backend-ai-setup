<?php

namespace App\Data\MobileAuth;

use App\Enums\AppLocale;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Asking for a code: the address (lower-cased and trimmed by the request) and
 * the language the mail should be in.
 */
final readonly class EmailCodeData
{
    public function __construct(
        public string $email,
        public AppLocale $locale,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            email: (string) $request->validated('email'),
            locale: AppLocale::tryFrom(app()->getLocale()) ?? AppLocale::DEFAULT,
        );
    }
}
