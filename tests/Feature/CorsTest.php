<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * CORS is what decides which page in a browser may call this API at all, so the
 * origin list is part of the contract rather than a detail of the config file.
 */
class CorsTest extends TestCase
{
    private const FRONTEND = 'http://localhost:5173';

    public function test_the_frontend_origin_is_allowed(): void
    {
        $response = $this->withHeader('Origin', self::FRONTEND)->getJson('/api/auth/me');

        $this->assertSame(self::FRONTEND, $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * The preflight is what a browser actually asks before a POST carrying a
     * bearer token or a request id, so it has to answer for itself.
     */
    public function test_the_frontend_origin_passes_the_preflight(): void
    {
        $response = $this->call('OPTIONS', '/api/auth/login', server: [
            'HTTP_ORIGIN' => self::FRONTEND,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-request-id,content-type,authorization',
        ]);

        $response->assertNoContent();

        $this->assertSame(self::FRONTEND, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertStringContainsString(
            'x-request-id',
            strtolower((string) $response->headers->get('Access-Control-Allow-Headers')),
        );
    }

    /**
     * Without this the list is decoration: under `*` the header would echo
     * whatever origin asked, and the assertions above would pass just the same.
     *
     * A refused origin is not answered with a missing header. With exactly one
     * allowed origin the header is a constant, so a foreign page is told
     * `http://localhost:5173` -- which is not its own origin, so the browser
     * refuses to hand it the response. What matters is that it never names the
     * caller.
     */
    public function test_another_origin_is_not_answered_with_its_own_origin(): void
    {
        $response = $this->withHeader('Origin', 'https://evil.example.com')->getJson('/api/auth/me');

        $allowed = $response->headers->get('Access-Control-Allow-Origin');

        $this->assertNotSame('https://evil.example.com', $allowed);
        $this->assertNotSame('*', $allowed);
    }
}
