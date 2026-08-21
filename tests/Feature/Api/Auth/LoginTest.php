<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $password = 'tajna-lozinka'): User
    {
        return User::factory()->create([
            'email' => 'uros.minic@fifthguild.com',
            'password' => $password,
        ]);
    }

    public function test_it_returns_the_user_and_a_token(): void
    {
        $user = $this->user();

        $response = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'tajna-lozinka',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Uspešno ste prijavljeni.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['message', 'data' => ['user' => ['id', 'name', 'surname', 'email'], 'token']]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_the_returned_token_identifies_the_user(): void
    {
        $user = $this->user();

        $token = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'tajna-lozinka',
        ])->json('data.token');

        $found = PersonalAccessToken::findToken($token);

        $this->assertNotNull($found, 'the returned token must resolve');
        $this->assertTrue($found->tokenable->is($user));
    }

    public function test_the_token_is_stored_hashed_never_in_plain_text(): void
    {
        $user = $this->user();

        $plain = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'tajna-lozinka',
        ])->json('data.token');

        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $plain]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_the_response_never_contains_the_password(): void
    {
        $user = $this->user();

        $data = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'tajna-lozinka',
        ])->json('data');

        $this->assertArrayNotHasKey('password', $data['user']);
        $this->assertArrayNotHasKey('remember_token', $data['user']);
    }

    public function test_a_wrong_password_is_rejected_without_issuing_a_token(): void
    {
        $user = $this->user();

        $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'pogresna',
        ])->assertUnauthorized()->assertJsonPath('message', 'Neispravni podaci za prijavu.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_an_unknown_email_is_rejected(): void
    {
        $this->postJson(route('auth.login'), [
            'email' => 'niko@nigde.com',
            'password' => 'bilo-sta',
        ])->assertUnauthorized()->assertJsonPath('message', 'Neispravni podaci za prijavu.');
    }

    public function test_a_wrong_password_and_an_unknown_email_are_indistinguishable(): void
    {
        config(['app.debug' => false]);
        $user = $this->user();

        $wrongPassword = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'pogresna',
        ]);

        $unknownEmail = $this->postJson(route('auth.login'), [
            'email' => 'niko@nigde.com',
            'password' => 'pogresna',
        ]);

        $this->assertSame($wrongPassword->status(), $unknownEmail->status());
        $this->assertSame(
            $wrongPassword->json(),
            $unknownEmail->json(),
            'the two failures must be byte-identical, or the endpoint reveals which emails have accounts',
        );
    }

    public function test_each_login_issues_its_own_token(): void
    {
        $user = $this->user();

        $first = $this->postJson(route('auth.login'), ['email' => $user->email, 'password' => 'tajna-lozinka'])->json('data.token');
        $second = $this->postJson(route('auth.login'), ['email' => $user->email, 'password' => 'tajna-lozinka'])->json('data.token');

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->assertNotNull(PersonalAccessToken::findToken($first), 'logging in elsewhere must not sign the first device out');
    }

    #[DataProvider('requiredFields')]
    public function test_email_and_password_are_required(string $field): void
    {
        $this->postJson(route('auth.login'), array_merge([
            'email' => 'uros.minic@fifthguild.com',
            'password' => 'tajna-lozinka',
        ], [$field => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    /** @return array<string, array<int, string>> */
    public static function requiredFields(): array
    {
        return ['email' => ['email'], 'password' => ['password']];
    }

    public function test_the_email_must_be_a_valid_address(): void
    {
        $this->postJson(route('auth.login'), ['email' => 'not-an-email', 'password' => 'tajna-lozinka'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_the_password_is_verified_against_the_hash(): void
    {
        $user = $this->user();

        $this->assertTrue(Hash::isHashed($user->password));
        $this->assertNotSame('tajna-lozinka', $user->password);
    }
}
