<?php

namespace Tests\Feature\Api\User;

use App\Enums\UserLogEvent;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdateUserAvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /**
     * A persisted administrator: this endpoint compares the caller's id with
     * the target's, and the bare helper signs in an unsaved user whose id is
     * null.
     *
     * The disk is faked in setUp rather than per test -- a test that forgot it
     * would write a real file into storage/app/public and leave it there.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(User::AVATAR_DISK);

        $this->admin = User::factory()->admin()->create();
    }

    private function upload(User $user, ?UploadedFile $file = null): TestResponse
    {
        return $this->withHeader('Accept', 'application/json')
            ->post(route('users.avatar.update', $user), [
                'avatar' => $file ?? UploadedFile::fake()->image('profilna.jpg', 900, 700),
            ]);
    }

    public function test_an_admin_sets_the_avatar_of_any_user(): void
    {
        $user = User::factory()->create();

        $url = $this->actingAsAdmin($this->admin)
            ->upload($user)
            ->assertOk()
            ->assertJsonPath('message', 'Profilna slika je uspešno postavljena.')
            ->assertJsonPath('data.id', $user->id)
            ->json('data.avatar_url');

        $path = $user->refresh()->avatar_path;

        $this->assertNotNull($path);
        Storage::disk(User::AVATAR_DISK)->assertExists($path);
        $this->assertStringEndsWith($path, (string) $url);
    }

    /**
     * The whole point of re-encoding: whatever was uploaded, what is kept is a
     * small square WebP. A 900x700 JPEG going in and coming back out unchanged
     * would be the failure this test exists to catch.
     */
    public function test_the_stored_file_is_a_square_webp_whatever_was_uploaded(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin)
            ->upload($user, UploadedFile::fake()->image('ogromna.png', 2000, 1200))
            ->assertOk();

        $path = $user->refresh()->avatar_path;

        $this->assertStringEndsWith('.webp', $path);

        $stored = getimagesizefromstring(Storage::disk(User::AVATAR_DISK)->get($path));

        $this->assertSame(512, $stored[0]);
        $this->assertSame(512, $stored[1]);
        $this->assertSame('image/webp', $stored['mime']);
    }

    /**
     * Every upload writes a new file under a fresh name, so a replaced avatar
     * would sit on the disk forever if it were not deleted -- and a cache in
     * front of the old URL would keep serving it if the name were reused.
     */
    public function test_replacing_an_avatar_deletes_the_previous_file(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin)->upload($user)->assertOk();
        $first = $user->refresh()->avatar_path;

        $this->upload($user)->assertOk();
        $second = $user->refresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk(User::AVATAR_DISK)->assertMissing($first);
        Storage::disk(User::AVATAR_DISK)->assertExists($second);
    }

    public function test_a_user_sets_their_own_avatar(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)->upload($user)->assertOk();

        $this->assertNotNull($user->refresh()->avatar_path);
    }

    /**
     * The one write a plain user may make is their own avatar. UserPolicy lets
     * them through the door -- it never sees the target row -- so this is the
     * test that pins down the rule the service enforces.
     */
    public function test_a_user_may_not_touch_somebody_elses_avatar(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAsUser($user)
            ->upload($other)
            ->assertForbidden()
            ->assertJsonPath('message', 'Možete menjati samo sopstvenu profilnu sliku.');

        $this->assertNull($other->refresh()->avatar_path);
        $this->assertSame([], Storage::disk(User::AVATAR_DISK)->allFiles());
        $this->assertDatabaseCount('user_logs', 0);
    }

    public function test_it_answers_404_for_a_user_that_does_not_exist(): void
    {
        $this->actingAsAdmin($this->admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('users.avatar.update', ['id' => 999]), ['avatar' => UploadedFile::fake()->image('a.jpg', 200, 200)])
            ->assertNotFound()
            ->assertJsonPath('message', 'Korisnik nije pronađen.');

        $this->assertSame([], Storage::disk(User::AVATAR_DISK)->allFiles());
    }

    /**
     * @return array<string, array{UploadedFile|null}>
     */
    public static function rejectedUploads(): array
    {
        return [
            'nothing at all' => [null],
            'a pdf' => [UploadedFile::fake()->create('ugovor.pdf', 40, 'application/pdf')],
            /** SVG is a document that can carry script, not a picture. */
            'an svg' => [UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml')],
            'smaller than 64x64' => [UploadedFile::fake()->image('sicusna.png', 32, 32)],
            'larger than 5 MB' => [UploadedFile::fake()->image('ogromna.jpg', 600, 600)->size(6000)],
        ];
    }

    #[DataProvider('rejectedUploads')]
    public function test_it_refuses_what_it_cannot_or_should_not_store(?UploadedFile $file): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('users.avatar.update', $user), $file === null ? [] : ['avatar' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->assertNull($user->refresh()->avatar_path);
        $this->assertSame([], Storage::disk(User::AVATAR_DISK)->allFiles());
    }

    public function test_it_records_who_changed_the_avatar_and_from_what(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin)->upload($user)->assertOk();
        $first = $user->refresh()->avatar_path;

        $this->upload($user)->assertOk();
        $second = $user->refresh()->avatar_path;

        $entries = UserLog::query()
            ->where('user_id', $user->id)
            ->where('event', UserLogEvent::AvatarUpdated)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertSame($this->admin->id, $entries[0]->actor_id);
        /** assertEquals, not assertSame: jsonb does not keep key order. */
        $this->assertEquals(['avatar_path' => null], $entries[0]->old_values);
        $this->assertEquals(['avatar_path' => $first], $entries[0]->new_values);
        $this->assertEquals(['avatar_path' => $first], $entries[1]->old_values);
        $this->assertEquals(['avatar_path' => $second], $entries[1]->new_values);
    }

    /**
     * The trail names the account whose picture changed, and the caller who
     * changed it -- which, here, are the same person acting on themselves.
     */
    public function test_a_user_changing_their_own_avatar_is_recorded_as_the_actor(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)->upload($user)->assertOk();

        $entry = UserLog::query()->where('user_id', $user->id)->sole();

        $this->assertSame(UserLogEvent::AvatarUpdated, $entry->event);
        $this->assertSame($user->id, $entry->actor_id);
    }

    /**
     * The URL is what the frontend renders, so it has to survive the round trip
     * through the list and the detail endpoint, not just the upload response.
     */
    public function test_the_avatar_url_comes_back_from_the_list_and_the_detail_endpoint(): void
    {
        $user = User::factory()->create();

        $uploaded = $this->actingAsAdmin($this->admin)->upload($user)->assertOk()->json('data.avatar_url');

        $this->assertSame($uploaded, $this->getJson(route('users.show', $user))->assertOk()->json('data.avatar_url'));

        $listed = collect($this->getJson(route('users.index'))->assertOk()->json('data'))
            ->firstWhere('id', $user->id);

        $this->assertSame($uploaded, $listed['avatar_url']);
    }

    public function test_an_account_without_an_avatar_reports_null(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin)
            ->getJson(route('users.show', $user))
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);
    }
}
