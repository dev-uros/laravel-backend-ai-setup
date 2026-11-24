<?php

namespace Tests\Feature\Api\Mobile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The external app's API answers in Serbian or English after
 * `Accept-Language`, Serbian by default -- validation and field names
 * included.
 */
class MobileLocaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function headers(): array
    {
        return [
            'serbian' => ['sr', 'Polje email adresa je obavezno.'],
            'serbian latin with region' => ['sr-Latn-RS,sr;q=0.9', 'Polje email adresa je obavezno.'],
            'english' => ['en', 'The email address field is required.'],
            'british english' => ['en-GB,en;q=0.9', 'The email address field is required.'],
            'unsupported falls back to serbian' => ['de-DE', 'Polje email adresa je obavezno.'],
            'empty falls back to serbian' => ['', 'Polje email adresa je obavezno.'],
        ];
    }

    #[DataProvider('headers')]
    public function test_the_answer_follows_the_header(string $header, string $message): void
    {
        $this->postJson(route('mobile.auth.email.sign-in-code'), [], ['Accept-Language' => $header])
            ->assertJsonValidationErrors(['email' => $message]);
    }

    public function test_the_internal_api_is_not_touched(): void
    {
        $this->postJson(route('auth.login'), [], ['Accept-Language' => 'sr'])
            ->assertJsonValidationErrors(['email' => 'The email address field is required.']);
    }

    public function test_both_languages_have_the_same_keys(): void
    {
        foreach (['mobile', 'validation'] as $file) {
            $sr = Arr::dot(require lang_path("sr/{$file}.php"));
            $en = Arr::dot(require lang_path("en/{$file}.php"));

            $missing = $file === 'validation'
                ? array_diff_key(Arr::only($sr, array_keys(Arr::where($sr, fn ($v, string $key): bool => str_starts_with($key, 'attributes.') || str_starts_with($key, 'custom.')))), $en)
                : array_diff_key($sr, $en) + array_diff_key($en, $sr);

            $this->assertSame([], array_keys($missing), "lang/*/{$file}.php keys differ");
        }
    }
}
