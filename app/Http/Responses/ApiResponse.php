<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds the standard envelope for responses this application returns from its
 * controllers: a user-facing `message`, followed by the `data` payload.
 *
 * This deliberately does not touch Laravel's own error responses. Validation
 * failures, 401, 403, 404 and uncaught exceptions keep the shape the framework
 * gives them.
 */
final class ApiResponse
{
    /**
     * A successful response.
     */
    public static function success(string $message, mixed $data = null, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json(self::payload($message, $data), $status);
    }

    /**
     * A resource was created.
     */
    public static function created(string $message, mixed $data = null): JsonResponse
    {
        return self::success($message, $data, Response::HTTP_CREATED);
    }

    /**
     * Build the envelope, keeping `message` as the first key.
     *
     * @return array<string, mixed>
     */
    private static function payload(string $message, mixed $data): array
    {
        $payload = ['message' => $message];

        if ($data instanceof JsonResource) {
            /**
             * Let the resource render itself so a paginated collection keeps its
             * `links` and `meta` alongside `data`, then merge the result in.
             * Union preserves `message` first and avoids nesting `data.data`.
             */
            return $payload + $data->response()->getData(true);
        }

        $payload['data'] = $data;

        return $payload;
    }
}
