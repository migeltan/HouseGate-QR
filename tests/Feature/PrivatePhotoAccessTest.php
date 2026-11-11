<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivatePhotoAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_for_every_photo_route(): void
    {
        $this->get('/photos/pass/1/visitor')->assertRedirect('/login');
        $this->get('/photos/pass/1/id')->assertRedirect('/login');
        $this->get('/photos/registration/1/visitor')->assertRedirect('/login');
        $this->get('/photos/scan/1')->assertRedirect('/login');
    }

    public function test_photos_disk_has_no_public_url(): void
    {
        $this->assertNull(config('filesystems.disks.photos.url'));
        $this->assertFalse(config('filesystems.disks.photos.serve'));
    }

    public function test_direct_storage_urls_for_photo_folders_are_never_served(): void
    {
        // 404 (nothing there) or 403 (Laravel's local-disk route rejects unsigned URLs); never 200.
        foreach (['/storage/id-photos/anything.jpg', '/storage/photos/id-photos/anything.jpg'] as $url) {
            $this->assertContains($this->get($url)->getStatusCode(), [403, 404]);
        }
    }
}