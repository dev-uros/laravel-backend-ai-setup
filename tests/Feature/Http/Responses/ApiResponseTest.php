<?php

namespace Tests\Feature\Http\Responses;

use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Resources\Json\JsonResource;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_returns_message_and_data(): void
    {
        $response = ApiResponse::success('Korisnik uspešno učitan.', ['id' => 1]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'message' => 'Korisnik uspešno učitan.',
            'data' => ['id' => 1],
        ], $response->getData(true));
    }

    public function test_message_is_the_first_key(): void
    {
        $response = ApiResponse::success('Gotovo.', ['id' => 1]);

        $this->assertSame(['message', 'data'], array_keys($response->getData(true)));
    }

    public function test_created_returns_201(): void
    {
        $response = ApiResponse::created('Korisnik uspešno kreiran.', ['id' => 1]);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_data_is_always_present_even_when_omitted(): void
    {
        $response = ApiResponse::success('Korisnik uspešno obrisan.');

        $this->assertSame([
            'message' => 'Korisnik uspešno obrisan.',
            'data' => null,
        ], $response->getData(true));
    }

    public function test_a_resource_is_not_wrapped_twice(): void
    {
        $user = User::factory()->create(['name' => 'Uroš', 'surname' => 'Minić']);

        $response = ApiResponse::created('Korisnik uspešno kreiran.', $this->resourceFor($user));

        $body = $response->getData(true);

        $this->assertSame(['message', 'data'], array_keys($body));
        $this->assertArrayNotHasKey('data', $body['data'], 'the resource was nested under a second data key');
        $this->assertSame('Uroš', $body['data']['name']);
        $this->assertSame('Minić', $body['data']['surname']);
    }

    public function test_a_paginated_collection_keeps_its_meta_and_links(): void
    {
        User::factory()->count(3)->create();

        $response = ApiResponse::success(
            'Korisnici uspešno učitani.',
            JsonResource::collection(User::query()->paginate(2)),
        );

        $body = $response->getData(true);

        $this->assertSame('Korisnici uspešno učitani.', $body['message']);
        $this->assertCount(2, $body['data']);
        $this->assertArrayHasKey('meta', $body);
        $this->assertArrayHasKey('links', $body);
        $this->assertSame(3, $body['meta']['total']);
    }

    /**
     * A throwaway resource so the test does not depend on an application one.
     */
    private function resourceFor(mixed $value): JsonResource
    {
        return new class($value) extends JsonResource
        {
            /** @return array<string, mixed> */
            public function toArray($request): array
            {
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'surname' => $this->surname,
                ];
            }
        };
    }
}
