<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The store reviewers' accounts -- and Maestro's -- which sign in with a fixed
 * code and are never mailed.
 */
class DemoAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inSerbian();
        Notification::fake();
        config(['mobile.demo' => ['emails' => ['review@example.com', 'maestro@example.com'], 'code' => '424242']]);
    }

    public function test_the_command_creates_and_resets_the_accounts(): void
    {
        $this->artisan('mobile:demo-accounts')->assertSuccessful();
        MobileUser::query()->where('email', 'review@example.com')->update(['blocked_at' => now()]);
        $this->artisan('mobile:demo-accounts')->assertSuccessful();

        $this->assertSame(2, MobileUser::query()->where('is_demo', true)->whereNull('blocked_at')->count());
        $this->assertSame('review_demo', MobileUser::query()->where('email', 'review@example.com')->value('username'));
    }

    public function test_without_emails_the_command_fails(): void
    {
        config(['mobile.demo.emails' => []]);

        $this->artisan('mobile:demo-accounts')->assertFailed();
    }

    public function test_a_demo_account_signs_in_with_the_fixed_code_and_is_mailed_nothing(): void
    {
        $this->artisan('mobile:demo-accounts');

        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'review@example.com'])->assertOk();
        $this->postJson(route('mobile.auth.email.sign-in'), ['email' => 'review@example.com', 'code' => '424242'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_a_real_account_can_never_use_the_fixed_code(): void
    {
        MobileUser::factory()->create(['email' => 'ana@example.com']);

        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'ana@example.com'])->assertOk();
        $this->postJson(route('mobile.auth.email.sign-in'), ['email' => 'ana@example.com', 'code' => '424242'])->assertUnprocessable();

        Notification::assertCount(1);
    }

    public function test_without_a_fixed_code_a_demo_account_is_mailed_like_anybody(): void
    {
        $this->artisan('mobile:demo-accounts');
        config(['mobile.demo.code' => null]);

        $this->postJson(route('mobile.auth.email.sign-in-code'), ['email' => 'review@example.com'])->assertOk();

        Notification::assertCount(1);
    }

    public function test_a_review_address_without_an_account_registers_with_the_fixed_code_and_can_do_so_again_after_deleting(): void
    {
        for ($round = 0; $round < 2; $round++) {
            $this->postJson(route('mobile.auth.email.registration-code'), ['email' => 'maestro@example.com', 'username' => 'maestro'])
                ->assertOk();

            $token = $this->postJson(route('mobile.auth.email.register'), [
                'email' => 'maestro@example.com',
                'code' => '424242',
                'username' => 'maestro',
            ])->assertCreated()->json('data.token');

            $this->assertTrue(MobileUser::query()->where('email', 'maestro@example.com')->sole()->is_demo);

            $this->deleteJson(route('mobile.me.destroy'), [], ['Authorization' => "Bearer {$token}"])->assertOk();
            $this->app['auth']->forgetGuards();
            $this->travel(61)->seconds();
        }

        Notification::assertNothingSent();
    }

    public function test_an_address_outside_the_list_never_gets_the_fixed_code_for_registration(): void
    {
        $this->postJson(route('mobile.auth.email.registration-code'), ['email' => 'stranger@example.com', 'username' => 'stranger'])->assertOk();

        $this->postJson(route('mobile.auth.email.register'), ['email' => 'stranger@example.com', 'code' => '424242', 'username' => 'stranger'])
            ->assertUnprocessable();

        Notification::assertCount(1);
    }
}
