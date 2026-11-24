<?php

namespace Tests\Feature\Api\MobileApp;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `GET /mobile/app-version/{platform}`: the minimum version the mobile app
 * checks itself against on every launch, before anyone signs in.
 */
class MobileAppVersionTest extends TestCase
{
    public function test_it_answers_each_platform_from_its_own_settings_without_a_token(): void
    {
        config([
            'mobile.minimum_version' => ['ios' => '1.4.0', 'android' => '1.3.2'],
            'mobile.store_url' => [
                'ios' => 'https://apps.apple.com/app/id123456789',
                'android' => 'https://play.google.com/store/apps/details?id=com.example.backoffice',
            ],
        ]);

        $this->getJson(route('mobile.app-version.show', 'ios'))
            ->assertOk()
            ->assertExactJson([
                'message' => 'Podaci o verziji aplikacije.',
                'data' => [
                    'minimum_version' => '1.4.0',
                    'store_url' => 'https://apps.apple.com/app/id123456789',
                ],
            ]);

        $this->getJson(route('mobile.app-version.show', 'android'))
            ->assertOk()
            ->assertJsonPath('data.minimum_version', '1.3.2')
            ->assertJsonPath('data.store_url', 'https://play.google.com/store/apps/details?id=com.example.backoffice');
    }

    /**
     * The `.env.example` lines ship empty, and an empty line must not become a
     * minimum of "" -- the app would compare against it.
     *
     * @return array<string, array{mixed}>
     */
    public static function unset(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'blank' => ['  '],
        ];
    }

    #[DataProvider('unset')]
    public function test_an_unset_value_is_null(mixed $value): void
    {
        config([
            'mobile.minimum_version' => ['ios' => $value, 'android' => $value],
            'mobile.store_url' => ['ios' => $value, 'android' => $value],
        ]);

        $this->getJson(route('mobile.app-version.show', 'ios'))
            ->assertOk()
            ->assertJsonPath('data.minimum_version', null)
            ->assertJsonPath('data.store_url', null);
    }

    public function test_surrounding_spaces_are_trimmed(): void
    {
        config(['mobile.minimum_version.ios' => ' 2.0.0 ']);

        $this->getJson(route('mobile.app-version.show', 'ios'))
            ->assertJsonPath('data.minimum_version', '2.0.0');
    }

    public function test_an_unknown_platform_is_not_found(): void
    {
        $this->getJson('/api/mobile/app-version/windows')->assertNotFound();
    }

    public function test_it_is_throttled(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson(route('mobile.app-version.show', 'ios'))->assertOk();
        }

        $this->getJson(route('mobile.app-version.show', 'ios'))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', 60);
    }
}
