<?php

namespace App\Data;

use Illuminate\Http\Request;

/**
 * Who is behind a change, and from where.
 *
 * Built by the controller and passed down, so services keep their promise never
 * to touch auth() or request() -- and so the same use case records an honest
 * actor when it runs from a job, a console command or a seeder.
 */
final readonly class ActorData
{
    public function __construct(
        public ?int $id = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}

    /**
     * The authenticated caller behind a request.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            id: $request->user()?->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }

    /**
     * A request with nobody signed in yet -- activating an account, resetting a
     * forgotten password. The address is worth keeping even when the caller is
     * still anonymous; the service fills the id in once it knows whose account
     * this is.
     */
    public static function guest(Request $request): self
    {
        return new self(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }

    /**
     * No person at all: a seeder, a console command, the scheduler.
     */
    public static function system(): self
    {
        return new self;
    }

    /**
     * The same context, attributed to a user. Used where the account acts on
     * itself and its id is only known once the token has been verified.
     */
    public function actingAs(int $id): self
    {
        return new self($id, $this->ipAddress, $this->userAgent);
    }
}
