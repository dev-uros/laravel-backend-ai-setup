<?php

namespace Tests\Feature\Api\MobileApp;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `GET /mobile/app-version/{app}/{platform}`: the minimum version each mobile
 * app checks itself against on every launch, before anyone signs in.
 */
class MobileAppVersionTest extends TestCase
{
    public function test_it_answers_each_app_and_platform_from_its_own_settings_without_a_token(): void
    {
        config([
            'mobile.apps.internal' => [
                'minimum_version' => ['ios' => '1.4.0', 'android' => '1.3.2'],
                'store_url' => [
                    'ios' => 'https://apps.apple.com/app/id123456789',
                    'android' => 'https://play.google.com/store/apps/details?id=com.example.backoffice',
                ],
            ],
            'mobile.apps.external' => [
                'minimum_version' => ['ios' => '2.0.0', 'android' => '2.1.0'],
                'store_url' => [
                    'ios' => 'https://apps.apple.com/app/id987654321',
                    'android' => 'https://play.google.com/store/apps/details?id=com.example.customers',
                ],
            ],
        ]);

        $this->getJson(route('mobile.app-version.show', ['internal', 'ios']))
            ->assertOk()
            ->assertExactJson([
                'message' => 'Podaci o verziji aplikacije.',
                'data' => [
                    'minimum_version' => '1.4.0',
                    'store_url' => 'https://apps.apple.com/app/id123456789',
                ],
            ]);

        $this->getJson(route('mobile.app-version.show', ['internal', 'android']))
            ->assertJsonPath('data.minimum_version', '1.3.2')
            ->assertJsonPath('data.store_url', 'https://play.google.com/store/apps/details?id=com.example.backoffice');

        $this->getJson(route('mobile.app-version.show', ['external', 'ios']))
            ->assertJsonPath('data.minimum_version', '2.0.0')
            ->assertJsonPath('data.store_url', 'https://apps.apple.com/app/id987654321');

        $this->getJson(route('mobile.app-version.show', ['external', 'android']))
            ->assertJsonPath('data.minimum_version', '2.1.0')
            ->assertJsonPath('data.store_url', 'https://play.google.com/store/apps/details?id=com.example.customers');
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
            'mobile.apps.external.minimum_version' => ['ios' => $value, 'android' => $value],
            'mobile.apps.external.store_url' => ['ios' => $value, 'android' => $value],
        ]);

        $this->getJson(route('mobile.app-version.show', ['external', 'ios']))
            ->assertOk()
            ->assertJsonPath('data.minimum_version', null)
            ->assertJsonPath('data.store_url', null);
    }

    public function test_surrounding_spaces_are_trimmed(): void
    {
        config(['mobile.apps.internal.minimum_version.ios' => ' 2.0.0 ']);

        $this->getJson(route('mobile.app-version.show', ['internal', 'ios']))
            ->assertJsonPath('data.minimum_version', '2.0.0');
    }

    public function test_an_unknown_app_or_platform_is_not_found(): void
    {
        $this->getJson('/api/mobile/app-version/internal/windows')->assertNotFound();
        $this->getJson('/api/mobile/app-version/partner/ios')->assertNotFound();

        /** The old, app-less path is gone: an app still calling it must not be told "no minimum". */
        $this->getJson('/api/mobile/app-version/ios')->assertNotFound();
    }

    public function test_it_is_throttled(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson(route('mobile.app-version.show', ['internal', 'ios']))->assertOk();
        }

        $this->getJson(route('mobile.app-version.show', ['external', 'ios']))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', 60);
    }
}
