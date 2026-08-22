<?php

namespace Tests\Feature\Http;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * What the application believes about a request that arrived through a proxy.
 *
 * In production every request passes through Traefik and then through the
 * application's own nginx, so the socket peer is always a container address.
 * `bootstrap/app.php` tells Laravel to read the forwarded headers instead;
 * without that, three separate features quietly record the wrong thing:
 * `login_attempts.ip_address`, `user_logs` via ActorData, and the request log.
 *
 * None of them would fail -- they would all agree on one meaningless address --
 * which is exactly why this is worth a test.
 */
class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    private const FORWARDED_IP = '203.0.113.9';

    public function test_a_forwarded_address_is_what_gets_recorded(): void
    {
        $user = User::factory()->create(['password' => 'tajna-lozinka']);

        $this->withHeaders(['X-Forwarded-For' => self::FORWARDED_IP])
            ->postJson(route('auth.login'), [
                'email' => $user->email,
                'password' => 'tajna-lozinka',
            ])
            ->assertOk();

        $this->assertSame(self::FORWARDED_IP, LoginAttempt::query()->sole()->ip_address);
    }

    /**
     * The chain a real proxy sends: the caller first, every hop after it. Only
     * the leftmost entry is the person; taking the last one would record the
     * proxy on every single request.
     */
    public function test_the_leftmost_address_in_the_chain_is_the_caller(): void
    {
        $user = User::factory()->create(['password' => 'tajna-lozinka']);

        $this->withHeaders(['X-Forwarded-For' => self::FORWARDED_IP.', 172.18.0.4, 172.18.0.7'])
            ->postJson(route('auth.login'), [
                'email' => $user->email,
                'password' => 'tajna-lozinka',
            ])
            ->assertOk();

        $this->assertSame(self::FORWARDED_IP, LoginAttempt::query()->sole()->ip_address);
    }

    /**
     * Traefik terminates TLS and forwards plain HTTP. Without the proto header
     * being trusted, every link this API emails -- activation, password reset --
     * would be an http:// URL for an https-only frontend.
     */
    public function test_the_forwarded_protocol_is_what_urls_are_built_from(): void
    {
        Route::get('/_test/scheme', fn (Request $request): array => [
            'secure' => $request->isSecure(),
            'url' => $request->url(),
        ]);

        $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'api.example.com',
        ])
            ->getJson('/_test/scheme')
            ->assertOk()
            ->assertJson([
                'secure' => true,
                'url' => 'https://api.example.com/_test/scheme',
            ]);
    }
}
