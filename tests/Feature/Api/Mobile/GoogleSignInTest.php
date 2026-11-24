<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\IssuesIdTokens;
use Tests\TestCase;

/**
 * Signing in and registering with Google: real verifier and decoder, a fake
 * key server and tokens signed with a key the test owns.
 */
class GoogleSignInTest extends TestCase
{
    use IssuesIdTokens, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inSerbian();
        Cache::flush();
        $this->fakeIdTokenKeys();
        config(['services.google_oauth.client_ids' => [
            'web-client.apps.googleusercontent.com',
            'ios-client.apps.googleusercontent.com',
        ]]);
    }

    public function test_the_google_account_signs_in(): void
    {
        $user = MobileUser::factory()->withGoogle('110000000000000000001')->create(['email' => 'someone-else@example.com']);

        $this->postJson(route('mobile.auth.google.sign-in'), ['id_token' => $this->googleIdToken()])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_any_configured_client_id_is_an_accepted_audience(): void
    {
        MobileUser::factory()->withGoogle('110000000000000000001')->create();

        $this->postJson(route('mobile.auth.google.sign-in'), [
            'id_token' => $this->googleIdToken(['aud' => 'ios-client.apps.googleusercontent.com']),
        ])->assertOk();

        $this->postJson(route('mobile.auth.google.sign-in'), [
            'id_token' => $this->googleIdToken(['aud' => 'somebody-elses-app.apps.googleusercontent.com']),
        ])->assertUnprocessable()->assertJsonPath('message', 'Prijava preko naloga Google nije uspela. Pokušajte ponovo.');
    }

    public function test_a_gmail_account_registered_with_a_code_is_linked(): void
    {
        $user = MobileUser::factory()->create(['email' => 'ana@gmail.com']);

        $this->postJson(route('mobile.auth.google.sign-in'), ['id_token' => $this->googleIdToken()])
            ->assertOk()
            ->assertJsonPath('data.user.has_google', true);

        $this->assertSame('110000000000000000001', $user->fresh()->google_id);
    }

    public function test_a_workspace_address_is_linked_too(): void
    {
        MobileUser::factory()->create(['email' => 'ana@firma.rs']);

        $this->postJson(route('mobile.auth.google.sign-in'), [
            'id_token' => $this->googleIdToken(['email' => 'Ana@Firma.rs', 'hd' => 'firma.rs']),
        ])->assertOk();
    }

    public function test_an_address_google_does_not_run_is_not_linked(): void
    {
        $user = MobileUser::factory()->create(['email' => 'ana@yahoo.com']);

        $this->postJson(route('mobile.auth.google.sign-in'), ['id_token' => $this->googleIdToken(['email' => 'ana@yahoo.com'])])
            ->assertConflict()
            ->assertJsonPath('message', 'Za ovu email adresu već postoji nalog. Prijavite se kodom na email.');

        $this->assertNull($user->fresh()->google_id);
    }

    public function test_an_account_linked_to_another_google_account_is_not_relinked(): void
    {
        MobileUser::factory()->withGoogle('999')->create(['email' => 'ana@gmail.com']);

        $this->postJson(route('mobile.auth.google.sign-in'), ['id_token' => $this->googleIdToken()])->assertConflict();
    }

    public function test_no_account_is_a_404_that_leads_to_registration(): void
    {
        $this->postJson(route('mobile.auth.google.sign-in'), ['id_token' => $this->googleIdToken()])
            ->assertNotFound()
            ->assertJsonPath('message', 'Ne postoji nalog za ovaj Google nalog. Napravite novi.');

        $this->postJson(route('mobile.auth.google.register'), [
            'id_token' => $this->googleIdToken(),
            'username' => 'ana',
            'first_name' => 'Ana',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'ana@gmail.com')
            ->assertJsonPath('data.user.has_google', true);
    }

    public function test_registering_an_existing_account_is_a_conflict(): void
    {
        MobileUser::factory()->create(['email' => 'ana@gmail.com']);

        $this->postJson(route('mobile.auth.google.register'), ['id_token' => $this->googleIdToken(), 'username' => 'ana2'])
            ->assertConflict();

        $this->assertDatabaseCount('mobile_users', 1);
    }

    public function test_untrustworthy_tokens_are_refused(): void
    {
        MobileUser::factory()->withGoogle('110000000000000000001')->create();

        foreach ([
            'expired' => ['exp' => time() - 3600],
            'wrong issuer' => ['iss' => 'https://evil.example'],
            'unverified address' => ['email_verified' => false],
        ] as $case => $claims) {
            $this->postJson(route('mobile.auth.google.sign-in'), ['id_token' => $this->googleIdToken($claims)])
                ->assertUnprocessable();
        }

        $this->postJson(route('mobile.auth.google.sign-in'), ['id_token' => 'not.a.jwt'])->assertUnprocessable();
    }

    public function test_an_unknown_key_refetches_the_keys_at_most_once_a_minute(): void
    {
        $token = $this->signIdToken(['iss' => 'https://accounts.google.com', 'aud' => 'web-client.apps.googleusercontent.com', 'sub' => '1', 'exp' => time() + 60], kid: 'rotated');

        $this->postJson(route('mobile.auth.google.sign-in'), ['id_token' => $token])->assertUnprocessable();
        $this->postJson(route('mobile.auth.google.sign-in'), ['id_token' => $token])->assertUnprocessable();

        /** First call fills the cache, the unknown kid refetches once, the second call is held by the cooldown. */
        Http::assertSentCount(2);
    }

    public function test_without_a_client_id_it_is_unavailable(): void
    {
        config(['services.google_oauth.client_ids' => []]);

        $this->postJson(route('mobile.auth.google.sign-in'), ['id_token' => $this->googleIdToken()])
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Prijava preko naloga Google trenutno nije dostupna.');
    }
}
