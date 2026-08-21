<?php

namespace App\Http\Resources;

use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserLog
 */
class UserLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `actor` is read directly rather than through `whenLoaded()`: the key was
     * documented as optional while it always arrives, because the only query
     * behind this resource -- UserLogRepository::paginateForUser() -- eager
     * loads the relation. A new query that reads this resource has to do the
     * same, or it pays an extra select per row.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            /**
             * The fields as they were, keyed by column name -- an object, not a
             * list. Null when the event has nothing to diff.
             *
             * @var array<string, mixed>|null
             */
            'old_values' => $this->old_values,
            /**
             * The fields as they became, keyed by column name -- an object, not
             * a list. Null when the event has nothing to diff.
             *
             * @var array<string, mixed>|null
             */
            'new_values' => $this->new_values,
            /**
             * Null means the system did it -- a seeder, a console command, the
             * scheduler -- not that the actor is unknown.
             */
            'actor' => $this->actor === null ? null : [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
                'surname' => $this->actor->surname,
                'email' => $this->actor->email,
            ],
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
