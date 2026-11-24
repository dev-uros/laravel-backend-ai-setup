<?php

namespace App\Data\MobileUser;

use App\Http\Requests\MobileUser\IndexMobileUsersRequest;

/**
 * The admin list's filters: a search over handle, names and address, and
 * blocked or not (null: both).
 */
final readonly class IndexMobileUsersData
{
    public function __construct(
        public ?string $search = null,
        public ?bool $blocked = null,
        public int $perPage = 25,
    ) {}

    public static function fromRequest(IndexMobileUsersRequest $request): self
    {
        $search = trim((string) $request->validated('search'));

        return new self(
            search: $search === '' ? null : $search,
            blocked: $request->has('blocked') ? $request->boolean('blocked') : null,
        );
    }
}
