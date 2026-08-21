<?php

namespace App\Services\UserLog;

use App\Data\ActorData;
use App\Enums\UserLogEvent;
use App\Models\User;
use App\Models\UserLog;
use App\Repositories\Contracts\UserLogRepositoryInterface;
use BackedEnum;
use DateTimeInterface;

/**
 * The one way an entry gets into the audit trail.
 *
 * Callers hand over what changed; this decides what is safe to keep. The caller
 * runs it inside the same transaction as the change itself -- a trail that can
 * fail while the change succeeds is a trail nobody trusts.
 */
class RecordUserLogService
{
    /**
     * Never written to the trail, in either the old or the new values.
     *
     * The password is stored hashed, but a hash is still the secret's shadow --
     * it is crackable offline, and an audit table is read by far more people
     * than the users table is.
     *
     * @var array<int, string>
     */
    private const NEVER_RECORDED = ['password', 'remember_token'];

    public function __construct(private readonly UserLogRepositoryInterface $logs) {}

    /**
     * Append an entry.
     *
     * `$old` and `$new` carry the fields that changed, not a snapshot of the
     * row. Leave them empty for an event with nothing worth diffing, such as an
     * activation email being sent.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function handle(
        User $user,
        UserLogEvent $event,
        ActorData $actor,
        array $old = [],
        array $new = [],
    ): UserLog {
        return $this->logs->record(
            $user,
            $event,
            $actor,
            $this->safe($old),
            $this->safe($new),
        );
    }

    /**
     * Drop anything that must never reach the trail, and reduce the rest to
     * values json can hold.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function safe(array $values): array
    {
        $safe = [];

        foreach ($values as $key => $value) {
            if (in_array($key, self::NEVER_RECORDED, true)) {
                continue;
            }

            $safe[$key] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
                default => $value,
            };
        }

        return $safe;
    }
}
