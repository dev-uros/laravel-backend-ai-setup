<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_account_the_token_belongs_to(): void
    {
        $user = User::factory()->create([
            'name' => 'Uroš',
            'surname' => 'Minić',
            'email' => 'uros.minic@fifthguild.com',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('spa')->plainTextToken)
            ->getJson(route('auth.me'))
            ->assertOk()
            ->assertJsonPath('message', 'Podaci o nalogu.')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Uroš')
            ->assertJsonPath('data.surname', 'Minić')
            ->assertJsonPath('data.email', 'uros.minic@fifthguild.com')
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'name', 'surname', 'email', 'email_verified_at', 'created_at', 'updated_at'],
            ]);
    }

    public function test_it_never_exposes_the_password(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson(route('auth.me'))->assertOk();

        $this->assertArrayNotHasKey('password', $response->json('data'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data'));
    }

    public function test_it_reads_the_account_as_it_is_now(): void
    {
        $user = User::factory()->create(['name' => 'Staro']);
        $token = $user->createToken('spa')->plainTextToken;

        $user->forceFill(['name' => 'Novo'])->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('auth.me'))
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo');
    }

    public function test_it_answers_for_an_account_that_never_activated(): void
    {
        $user = User::factory()->unverified()->create();

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('spa')->plainTextToken)
            ->getJson(route('auth.me'))
            ->assertOk()
            ->assertJsonPath('data.email_verified_at', null);
    }

    public function test_it_rejects_a_request_without_a_token(): void
    {
        $this->getJson(route('auth.me'))->assertUnauthorized();
    }

    public function test_it_rejects_an_unknown_token(): void
    {
        $this->withHeader('Authorization', 'Bearer 1|nepostojeci-token')
            ->getJson(route('auth.me'))
            ->assertUnauthorized();
    }
}
