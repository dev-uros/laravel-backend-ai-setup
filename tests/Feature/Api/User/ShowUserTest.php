<?php

namespace Tests\Feature\Api\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsUser();
    }

    public function test_it_returns_the_standard_envelope(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson(route('users.show', $user));

        $response->assertOk();
        $this->assertSame(['message', 'data'], array_keys($response->json()));
        $this->assertSame('Korisnik uspešno učitan.', $response->json('message'));
    }

    public function test_it_returns_every_field_of_the_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Uroš',
            'surname' => 'Minić',
            'email' => 'uros.minic@fifthguild.com',
        ]);

        $this->getJson(route('users.show', $user))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Uroš')
            ->assertJsonPath('data.surname', 'Minić')
            ->assertJsonPath('data.email', 'uros.minic@fifthguild.com')
            ->assertJsonStructure([
                'data' => ['id', 'name', 'surname', 'email', 'email_verified_at', 'created_at', 'updated_at'],
            ]);
    }

    public function test_it_returns_a_single_object_not_a_list(): void
    {
        $user = User::factory()->create();

        $data = $this->getJson(route('users.show', $user))->json('data');

        $this->assertArrayHasKey('id', $data, 'data should be the user object itself');
        $this->assertArrayNotHasKey(0, $data);
    }

    public function test_it_never_exposes_the_password(): void
    {
        $user = User::factory()->create();

        $data = $this->getJson(route('users.show', $user))->json('data');

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }

    public function test_it_404s_for_an_unknown_user(): void
    {
        $this->getJson(route('users.show', ['id' => 999]))
            ->assertNotFound()
            ->assertJsonPath('message', 'Korisnik nije pronađen.');
    }

    public function test_the_404_leaks_nothing_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $body = $this->getJson(route('users.show', ['id' => 999]))
            ->assertNotFound()
            ->json();

        $this->assertSame(['message' => 'Korisnik nije pronađen.'], $body);
    }

    public function test_a_non_numeric_id_does_not_reach_the_application(): void
    {
        $this->getJson('/api/users/abc')->assertNotFound();
    }

    public function test_it_only_returns_the_requested_user(): void
    {
        $wanted = User::factory()->create(['name' => 'Uroš']);
        User::factory()->create(['name' => 'Neko']);

        $this->getJson(route('users.show', $wanted))
            ->assertOk()
            ->assertJsonPath('data.name', 'Uroš');
    }
}
