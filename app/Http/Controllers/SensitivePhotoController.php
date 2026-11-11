<?php

namespace App\Http\Controllers;

use App\Models\PassRegistration;
use App\Models\ScanLog;
use App\Models\VisitorPass;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves visitor, ID and scan-verification photos from the private "photos" disk.
 * Routes sit behind auth + building.selected, so any signed-in admin or guard may view them;
 * nobody else can. Storage paths and file names are never exposed.
 */
class SensitivePhotoController extends Controller
{
    public function pass(VisitorPass $pass, string $kind): Response
    {
        return $this->serve($kind === 'id' ? $pass->id_photo_path : $pass->photo_path, $kind === 'id' ? 'id-photos/' : 'visitor-photos/');
    }

    public function registration(PassRegistration $registration, string $kind): Response
    {
        return $this->serve($kind === 'id' ? $registration->id_photo_path : $registration->photo_path, $kind === 'id' ? 'id-photos/' : 'visitor-photos/');
    }

    public function scan(ScanLog $scanLog): Response
    {
        return $this->serve($scanLog->verificationPhoto?->photo_path, 'verification-photos/');
    }

    private function serve(?string $path, string $folder): Response
    {
        // Only paths this app wrote itself, inside the expected folder, are ever read.
        abort_if(! $path || ! str_starts_with($path, $folder) || str_contains($path, '..'), 404);

        $disk = Storage::disk('photos');
        abort_unless($disk->exists($path), 404);

        // Bytes are returned directly (not via ->response()) so the stored file name is not sent to the browser.
        return response($disk->get($path), 200, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}