<?php

namespace Tests\Feature\Api\User;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreUserTest extends TestCase
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
            'name' => 'Petar',
            'surname' => 'Petrović',
            'email' => 'petar@gmail.com',
        ], $overrides);
    }

    public function test_it_creates_the_user_and_returns_201(): void
    {
        Notification::fake();

        $response = $this->postJson(route('users.store'), $this->payload());

        $response->assertCreated();
        $this->assertSame(['message', 'data'], array_keys($response->json()));
        $response->assertJsonPath('data.name', 'Petar')
            ->assertJsonPath('data.surname', 'Petrović')
            ->assertJsonPath('data.email', 'petar@gmail.com');

        $this->assertDatabaseHas('users', ['email' => 'petar@gmail.com', 'name' => 'Petar']);
    }

    public function test_it_creates_a_plain_user_when_no_role_is_sent(): void
    {
        Notification::fake();

        $this->postJson(route('users.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.role', 'user');

        $this->assertSame(UserRole::User, User::query()->where('email', 'petar@gmail.com')->sole()->role);
    }

    public function test_an_admin_can_create_another_admin(): void
    {
        Notification::fake();

        $this->postJson(route('users.store'), $this->payload(['role' => 'admin']))
            ->assertCreated()
            ->assertJsonPath('data.role', 'admin');

        $this->assertSame(UserRole::Admin, User::query()->where('email', 'petar@gmail.com')->sole()->role);
    }

    public function test_it_rejects_a_role_that_does_not_exist(): void
    {
        Notification::fake();

        $this->postJson(route('users.store'), $this->payload(['role' => 'superadmin']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'petar@gmail.com']);
        Notification::assertNothingSent();
    }

    public function test_the_password_is_random_hashed_and_never_returned(): void
    {
        Notification::fake();

        $response = $this->postJson(route('users.store'), $this->payload());
        $this->postJson(route('users.store'), $this->payload(['email' => 'drugi@gmail.com']));

        $this->assertArrayNotHasKey('password', $response->json('data'));

        [$first, $second] = User::query()->orderBy('id')->get()->all();

        $this->assertNotEmpty($first->password);
        $this->assertTrue(Hash::isHashed($first->password));
        $this->assertNotSame($first->password, $second->password, 'passwords must not be shared between users');
    }

    public function test_it_creates_a_password_reset_token_stored_as_a_hash(): void
    {
        Notification::fake();

        $this->postJson(route('users.store'), $this->payload())->assertCreated();

        $row = DB::table('password_reset_tokens')->where('email', 'petar@gmail.com')->first();

        $this->assertNotNull($row, 'a reset token row should exist for the new user');
        $this->assertTrue(Hash::isHashed($row->token), 'the token must be stored hashed, never in plain text');
    }

    public function test_the_emailed_token_is_accepted_by_laravels_reset_flow(): void
    {
        Notification::fake();

        $this->postJson(route('users.store'), $this->payload())->assertCreated();

        $user = User::query()->firstOrFail();
        $token = $this->capturedToken($user);

        $status = Password::broker()->reset(
            ['email' => $user->email, 'password' => 'nova-lozinka-123', 'password_confirmation' => 'nova-lozinka-123', 'token' => $token],
            function (User $resetUser, string $password): void {
                $resetUser->forceFill(['password' => $password])->save();
            },
        );

        $this->assertSame(Password::PASSWORD_RESET, $status);
        $this->assertTrue(Hash::check('nova-lozinka-123', $user->fresh()->password));
    }

    public function test_the_notification_is_queued(): void
    {
        $this->assertTrue(
            is_subclass_of(AccountCreatedNotification::class, ShouldQueue::class),
            'account creation must not wait on the mail transport',
        );
    }

    public function test_it_notifies_the_new_user(): void
    {
        Notification::fake();

        $this->postJson(route('users.store'), $this->payload())->assertCreated();

        $user = User::query()->firstOrFail();

        Notification::assertSentTo($user, AccountCreatedNotification::class);
        Notification::assertCount(1);
    }

    public function test_the_activation_link_points_at_the_frontend_with_email_and_token(): void
    {
        config(['app.frontend_url' => 'https://app.fifthguild.com']);
        Notification::fake();

        $this->postJson(route('users.store'), $this->payload())->assertCreated();

        $user = User::query()->firstOrFail();
        $url = $this->capturedUrl($user);

        $parts = parse_url($url);
        parse_str($parts['query'], $query);

        $this->assertSame('https', $parts['scheme']);
        $this->assertSame('app.fifthguild.com', $parts['host']);
        $this->assertSame('/auth/activate-account', $parts['path'], 'activation must not share the forgotten-password path');
        $this->assertSame('petar@gmail.com', $query['email']);
        $this->assertNotEmpty($query['token']);

        $this->assertStringContainsString('email=petar%40gmail.com', $parts['query']);
    }

    #[DataProvider('requiredFields')]
    public function test_every_field_is_required(string $field): void
    {
        $this->postJson(route('users.store'), $this->payload([$field => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    /** @return array<string, array<int, string>> */
    public static function requiredFields(): array
    {
        return ['name' => ['name'], 'surname' => ['surname'], 'email' => ['email']];
    }

    public function test_the_email_must_be_valid_and_unique(): void
    {
        User::factory()->create(['email' => 'zauzet@fifthguild.com']);

        $this->postJson(route('users.store'), $this->payload(['email' => 'not-an-email']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson(route('users.store'), $this->payload(['email' => 'zauzet@fifthguild.com']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_name_and_surname_are_capped_at_255(): void
    {
        $this->postJson(route('users.store'), $this->payload(['name' => str_repeat('a', 256)]))
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->postJson(route('users.store'), $this->payload(['surname' => str_repeat('a', 256)]))
            ->assertUnprocessable()->assertJsonValidationErrors('surname');
    }

    public function test_nothing_is_persisted_when_validation_fails(): void
    {
        Notification::fake();

        $this->postJson(route('users.store'), $this->payload(['email' => 'not-an-email']))
            ->assertUnprocessable();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    /**
     * Pull the plain token back out of the notification that was sent.
     */
    private function capturedToken(User $user): string
    {
        $query = parse_url($this->capturedUrl($user), PHP_URL_QUERY);
        parse_str((string) $query, $parsed);

        return $parsed['token'];
    }

    private function capturedUrl(User $user): string
    {
        $url = null;

        Notification::assertSentTo($user, AccountCreatedNotification::class, function ($notification) use ($user, &$url): bool {
            $url = $notification->toMail($user)->actionUrl;

            return true;
        });

        return (string) $url;
    }
}
