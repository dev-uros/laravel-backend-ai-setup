<?php

namespace Tests\Feature\Api\User;

use App\Enums\UserLogEvent;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteUserAvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(User::AVATAR_DISK);

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * Through the endpoint rather than by writing `avatar_path` by hand, so the
     * file on the disk and the row always agree -- which is the pair this test
     * then takes apart.
     */
    private function withAvatar(): User
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('users.avatar.update', $user), [
                'avatar' => UploadedFile::fake()->image('profilna.jpg', 400, 400),
            ])
            ->assertOk();

        return $user->refresh();
    }

    public function test_an_admin_removes_the_avatar_of_any_user(): void
    {
        $user = $this->withAvatar();
        $path = $user->avatar_path;

        $this->actingAsAdmin($this->admin)
            ->deleteJson(route('users.avatar.destroy', $user))
            ->assertOk()
            ->assertJsonPath('message', 'Profilna slika je uspešno uklonjena.')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.avatar_url', null);

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk(User::AVATAR_DISK)->assertMissing($path);
    }

    public function test_a_user_removes_their_own_avatar(): void
    {
        $user = $this->withAvatar();
        $path = $user->avatar_path;

        $this->actingAsUser($user)
            ->deleteJson(route('users.avatar.destroy', $user))
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk(User::AVATAR_DISK)->assertMissing($path);
    }

    public function test_a_user_may_not_remove_somebody_elses_avatar(): void
    {
        $other = $this->withAvatar();
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->deleteJson(route('users.avatar.destroy', $other))
            ->assertForbidden()
            ->assertJsonPath('message', 'Možete menjati samo sopstvenu profilnu sliku.');

        $this->assertSame($other->avatar_path, $other->refresh()->avatar_path);
        Storage::disk(User::AVATAR_DISK)->assertExists($other->avatar_path);
    }

    /**
     * The request asked for a state the account is already in. Answering 404 or
     * 422 would make a frontend that just wants "no avatar here" handle a
     * failure that is not one.
     */
    public function test_removing_an_avatar_that_is_not_there_is_not_an_error(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin)
            ->deleteJson(route('users.avatar.destroy', $user))
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);

        /** Nothing moved, so the trail stays quiet about it. */
        $this->assertDatabaseCount('user_logs', 0);
    }

    public function test_it_answers_404_for_a_user_that_does_not_exist(): void
    {
        $this->actingAsAdmin($this->admin)
            ->deleteJson(route('users.avatar.destroy', ['id' => 999]))
            ->assertNotFound()
            ->assertJsonPath('message', 'Korisnik nije pronađen.');
    }

    public function test_it_records_who_removed_the_avatar_and_which_file_went(): void
    {
        $user = $this->withAvatar();
        $path = $user->avatar_path;

        $this->actingAsAdmin($this->admin)
            ->deleteJson(route('users.avatar.destroy', $user))
            ->assertOk();

        $entry = UserLog::query()
            ->where('user_id', $user->id)
            ->where('event', UserLogEvent::AvatarRemoved)
            ->sole();

        $this->assertSame($this->admin->id, $entry->actor_id);
        /** assertEquals, not assertSame: jsonb does not keep key order. */
        $this->assertEquals(['avatar_path' => $path], $entry->old_values);
        $this->assertEquals(['avatar_path' => null], $entry->new_values);
    }
}
