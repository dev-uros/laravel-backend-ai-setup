<?php

namespace Tests\Feature\Api\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdateUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Uroš',
            'surname' => 'Minić',
            'email' => 'uros.minic@fifthguild.com',
        ], $overrides);
    }

    public function test_it_updates_the_user_and_returns_the_envelope(): void
    {
        $user = User::factory()->create(['name' => 'Staro', 'surname' => 'Prezime']);

        $response = $this->putJson(route('users.update', $user), $this->payload());

        $response->assertOk();
        $this->assertSame(['message', 'data'], array_keys($response->json()));
        $this->assertSame('Korisnik uspešno ažuriran.', $response->json('message'));
        $response->assertJsonPath('data.name', 'Uroš')
            ->assertJsonPath('data.surname', 'Minić')
            ->assertJsonPath('data.email', 'uros.minic@fifthguild.com');
    }

    public function test_it_persists_the_change(): void
    {
        $user = User::factory()->create();

        $this->putJson(route('users.update', $user), $this->payload())->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Uroš',
            'surname' => 'Minić',
            'email' => 'uros.minic@fifthguild.com',
        ]);
    }

    #[DataProvider('requiredFields')]
    public function test_every_field_is_required(string $field): void
    {
        $user = User::factory()->create();

        $this->putJson(route('users.update', $user), $this->payload([$field => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    /** @return array<string, array<int, string>> */
    public static function requiredFields(): array
    {
        return ['name' => ['name'], 'surname' => ['surname'], 'email' => ['email']];
    }

    #[DataProvider('lengthLimitedFields')]
    public function test_name_and_surname_are_capped_at_255(string $field): void
    {
        $user = User::factory()->create();

        $this->putJson(route('users.update', $user), $this->payload([$field => str_repeat('a', 256)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->putJson(route('users.update', $user), $this->payload([$field => str_repeat('a', 255)]))
            ->assertOk();
    }

    /** @return array<string, array<int, string>> */
    public static function lengthLimitedFields(): array
    {
        return ['name' => ['name'], 'surname' => ['surname']];
    }

    public function test_the_email_must_be_a_valid_address(): void
    {
        $user = User::factory()->create();

        $this->putJson(route('users.update', $user), $this->payload(['email' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_the_email_must_be_unique_across_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'zauzet@fifthguild.com']);

        $this->putJson(route('users.update', $user), $this->payload(['email' => $other->email]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_the_user_being_updated_does_not_clash_with_itself(): void
    {
        $user = User::factory()->create(['email' => 'uros.minic@fifthguild.com']);

        $this->putJson(route('users.update', $user), $this->payload(['name' => 'Novo']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo')
            ->assertJsonPath('data.email', 'uros.minic@fifthguild.com');
    }

    public function test_it_ignores_fields_that_are_not_updatable(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->putJson(route('users.update', $user), $this->payload([
            'password' => 'hakovano',
            'id' => 9999,
        ]))->assertOk();

        $user->refresh();

        $this->assertSame($originalPassword, $user->password);
        $this->assertFalse(Hash::check('hakovano', $user->password));
        $this->assertDatabaseMissing('users', ['id' => 9999]);
    }

    public function test_it_404s_for_an_unknown_user(): void
    {
        $this->putJson(route('users.update', ['id' => 999]), $this->payload())
            ->assertNotFound()
            ->assertJsonPath('message', 'Korisnik nije pronađen.');
    }

    public function test_a_non_numeric_id_does_not_reach_the_application(): void
    {
        $this->putJson('/api/users/abc', $this->payload())->assertNotFound();
    }
}
