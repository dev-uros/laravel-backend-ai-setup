<?php

namespace Tests\Feature\Api\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsUser();
    }

    public function test_it_returns_the_standard_envelope(): void
    {
        User::factory()->create(['name' => 'Uroš', 'surname' => 'Minić']);

        $response = $this->getJson(route('users.index'));

        $response->assertOk();
        $this->assertSame(['message', 'data'], array_keys($response->json()));
        $this->assertSame('Korisnici uspešno učitani.', $response->json('message'));
    }

    public function test_it_exposes_the_expected_user_fields(): void
    {
        User::factory()->create([
            'name' => 'Uroš',
            'surname' => 'Minić',
            'email' => 'uros.minic@fifthguild.com',
        ]);

        $this->getJson(route('users.index'))
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    ['id', 'name', 'surname', 'email', 'email_verified_at', 'created_at'],
                ],
            ])
            ->assertJsonPath('data.0.name', 'Uroš')
            ->assertJsonPath('data.0.surname', 'Minić')
            ->assertJsonPath('data.0.email', 'uros.minic@fifthguild.com');
    }

    public function test_it_never_exposes_the_password(): void
    {
        User::factory()->create();

        $body = $this->getJson(route('users.index'))->json('data.0');

        $this->assertArrayNotHasKey('password', $body);
        $this->assertArrayNotHasKey('remember_token', $body);
    }

    public function test_it_sorts_by_name_then_surname(): void
    {
        User::factory()->create(['name' => 'Marko', 'surname' => 'Petrović']);
        User::factory()->create(['name' => 'Ana', 'surname' => 'Jovanović']);
        User::factory()->create(['name' => 'Marko', 'surname' => 'Ilić']);

        $names = collect($this->getJson(route('users.index'))->json('data'))
            ->map(fn (array $user): string => $user['name'].' '.$user['surname'])
            ->all();

        $this->assertSame([
            'Ana Jovanović',
            'Marko Ilić',
            'Marko Petrović',
        ], $names);
    }

    public function test_it_returns_every_user_without_pagination(): void
    {
        User::factory()->count(30)->create();

        $response = $this->getJson(route('users.index'));

        $response->assertOk();
        $this->assertCount(30, $response->json('data'));
        $this->assertArrayNotHasKey('meta', $response->json());
        $this->assertArrayNotHasKey('links', $response->json());
    }

    public function test_it_returns_an_empty_array_when_there_are_no_users(): void
    {
        $this->getJson(route('users.index'))
            ->assertOk()
            ->assertExactJson([
                'message' => 'Korisnici uspešno učitani.',
                'data' => [],
            ]);
    }
}
