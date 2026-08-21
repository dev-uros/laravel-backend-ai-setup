<?php

namespace App\Support\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;

/**
 * Documents the 429 a throttled route answers with.
 *
 * Scramble derives error responses from the exceptions a service throws, and
 * nothing in the application throws this one -- the throttle middleware does,
 * before the controller is reached. Without this the login, activation and
 * password endpoints document 200/401/422 and stay silent about the status a
 * client is most likely to meet while retrying.
 *
 * Reading it off the route rather than annotating each action means a new
 * `throttle:` on a route documents itself, and a removed one stops being
 * promised.
 */
class ThrottledResponseExtension extends OperationExtension
{
    /**
     * What Laravel's handler renders for a ThrottleRequestsException.
     */
    private const MESSAGE = 'Too Many Attempts.';

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! $limit = $this->limit($routeInfo)) {
            return;
        }

        if ($this->alreadyDocumented($operation)) {
            return;
        }

        $body = (new OpenApiTypes\ObjectType)
            ->addProperty(
                'message',
                (new OpenApiTypes\StringType)
                    ->setDescription('Error overview.')
                    ->examples([self::MESSAGE]),
            )
            ->setRequired(['message']);

        $operation->addResponse(
            Response::make(429)
                ->setDescription($this->describe($limit))
                ->setContent('application/json', Schema::fromType($body))
                ->setHeaders([
                    'Retry-After' => (new Header)
                        ->setDescription('Seconds to wait before retrying.')
                        ->setSchema(Schema::fromType(new OpenApiTypes\IntegerType)),
                    'X-RateLimit-Limit' => (new Header)
                        ->setDescription('Attempts allowed in the window.')
                        ->setSchema(Schema::fromType(new OpenApiTypes\IntegerType)),
                    'X-RateLimit-Remaining' => (new Header)
                        ->setDescription('Attempts left in the window.')
                        ->setSchema(Schema::fromType(new OpenApiTypes\IntegerType)),
                ]),
        );
    }

    /**
     * The route's `throttle:{attempts},{minutes}` arguments, if it carries one.
     *
     * A named limiter (`throttle:api`) defines its allowance in code rather
     * than on the route, so there is nothing to read: it still documents the
     * status, just without the numbers.
     *
     * @return array{int|null, int|null}|null
     */
    private function limit(RouteInfo $routeInfo): ?array
    {
        $middleware = collect($routeInfo->route->gatherMiddleware())
            ->first(fn ($m) => is_string($m) && Str::startsWith($m, ['throttle:', ThrottleRequests::class.':']));

        if (! is_string($middleware)) {
            return null;
        }

        [$attempts, $minutes] = array_pad(explode(',', Str::after($middleware, ':')), 2, null);

        return [
            is_numeric($attempts) ? (int) $attempts : null,
            is_numeric($minutes) ? (int) $minutes : null,
        ];
    }

    /**
     * @param  array{int|null, int|null}  $limit
     */
    private function describe(array $limit): string
    {
        [$attempts, $minutes] = $limit;

        if ($attempts === null || $minutes === null) {
            return 'Too many requests';
        }

        $window = $minutes === 1 ? 'minute' : "{$minutes} minutes";

        return "Too many requests -- the route allows {$attempts} per {$window}";
    }

    /**
     * Never add a second 429: an action that throws its own is already right.
     */
    private function alreadyDocumented(Operation $operation): bool
    {
        return collect($operation->responses)
            ->contains(fn ($response) => ! $response instanceof Reference && (int) $response->code === 429);
    }
}
