<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the Directory's service worker. The asset list and version stamp are built from the
 * files on disk, so a rebuilt Tailwind file or a new font refreshes every client's offline copy.
 */
class DirectoryServiceWorkerController extends Controller
{
    public function __invoke()
    {
        // Only what the Directory page loads: layout CSS, local fonts, Font Awesome, the layout's images.
        $files = array_merge(
            File::glob(public_path('css/*.css')),
            File::glob(public_path('fonts/*')),
            [public_path('vendor/fontawesome/css/all.min.css')],
            File::glob(public_path('vendor/fontawesome/webfonts/*.woff2')),
            [public_path('images/lsb-seal.png'), public_path('images/inspire-logo.png'), public_path('images/rattan-pattern-raw.svg')],
        );

        $relative = collect($files)
            ->filter(fn ($f) => is_file($f))
            ->map(fn ($f) => ltrim(str_replace('\\', '/', substr($f, strlen(public_path()))), '/'))
            ->unique()->sort()->values();

        $version = substr(md5($relative->map(fn ($p) => $p . '@' . filemtime(public_path($p)))->implode('|')), 0, 10);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */   // type hint only: silences Intelephense P1013 on ->url()
        $disk = Storage::disk('public');

        return response()->view('directory-sw', [
            'version' => $version,
            'assets' => $relative->map(fn ($p) => parse_url(asset($p), PHP_URL_PATH))->all(),
            'dirPath' => parse_url(route('congressmen.index'), PHP_URL_PATH),
            'photoPrefix' => rtrim(parse_url($disk->url('congressmen'), PHP_URL_PATH), '/') . '/',   // was Storage::disk('public')->url()
        ])->header('Content-Type', 'application/javascript; charset=utf-8')
          ->header('Cache-Control', 'no-cache');
    }
}