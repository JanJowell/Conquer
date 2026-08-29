<?php

use App\Http\Resources\Api\AnnouncementResource;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function announcementPayload(array $overrides = []): array
{
    return array_merge([
        'event_id' => null,
        'title' => 'Race Day Advisory',
        'content' => 'Please review the updated race-day instructions.',
        'is_published' => '1',
        'expires_at' => null,
    ], $overrides);
}

test('admin uploads an announcement image and the mobile API exposes its absolute URL', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this
        ->actingAs($admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'image' => UploadedFile::fake()->image('advisory.webp', 1200, 800),
        ]))
        ->assertSessionHasNoErrors();

    $announcement = Announcement::where('title', 'Race Day Advisory')->firstOrFail();
    $resource = (new AnnouncementResource($announcement))->toArray(Request::create('/api/announcements'));

    expect($announcement->image_path)->toStartWith('announcements/')
        ->and($resource['image_url'])->toBe(url('storage/'.$announcement->image_path));
    Storage::disk('public')->assertExists($announcement->image_path);
});

test('editing announcement text preserves its existing image', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    Storage::disk('public')->put('announcements/existing.jpg', 'image');
    $announcement = Announcement::create([
        ...announcementPayload(),
        'image_path' => 'announcements/existing.jpg',
        'published_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.announcements.update', $announcement), announcementPayload([
            'title' => 'Updated Advisory',
        ]))
        ->assertSessionHasNoErrors();

    expect($announcement->fresh()->image_path)->toBe('announcements/existing.jpg');
    Storage::disk('public')->assertExists('announcements/existing.jpg');
});

test('admin replaces and removes an announcement image without leaving old files', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    Storage::disk('public')->put('announcements/old.png', 'old');
    $announcement = Announcement::create([
        ...announcementPayload(),
        'image_path' => 'announcements/old.png',
        'published_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.announcements.update', $announcement), announcementPayload([
            'image' => UploadedFile::fake()->image('replacement.jpg', 1200, 800),
        ]))
        ->assertSessionHasNoErrors();

    $replacementPath = $announcement->fresh()->image_path;
    expect($replacementPath)->not->toBe('announcements/old.png');
    Storage::disk('public')->assertMissing('announcements/old.png');
    Storage::disk('public')->assertExists($replacementPath);

    $this
        ->actingAs($admin)
        ->put(route('admin.announcements.update', $announcement), announcementPayload([
            'remove_image' => '1',
        ]))
        ->assertSessionHasNoErrors();

    expect($announcement->fresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($replacementPath);
});

test('deleting an announcement also deletes its image', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    Storage::disk('public')->put('announcements/delete-me.webp', 'image');
    $announcement = Announcement::create([
        ...announcementPayload(),
        'image_path' => 'announcements/delete-me.webp',
        'published_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.announcements.destroy', $announcement))
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing('announcements/delete-me.webp');
    expect(Announcement::find($announcement->id))->toBeNull();
});

test('announcement image validation rejects unsupported files and oversized images', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this
        ->actingAs($admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'image' => UploadedFile::fake()->create('instructions.pdf', 100, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('image');

    $this
        ->actingAs($admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'image' => UploadedFile::fake()->image('too-large.jpg')->size(5121),
        ]))
        ->assertSessionHasErrors('image');
});

test('announcement forms accept images and show the existing image controls', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $announcement = Announcement::create([
        ...announcementPayload(),
        'image_path' => 'announcements/existing.jpg',
        'published_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.announcements.index'))
        ->assertOk()
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('name="image"', false)
        ->assertSee('Remove current image');

    $this->actingAs($admin)
        ->get(route('admin.announcements.edit', $announcement))
        ->assertOk()
        ->assertSee('name="image"', false)
        ->assertSee('Remove current image');
});
