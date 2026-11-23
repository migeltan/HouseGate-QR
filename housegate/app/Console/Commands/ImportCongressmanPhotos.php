<?php

namespace App\Console\Commands;

use App\Models\Congressman;
use App\Services\PhotoResizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportCongressmanPhotos extends Command
{
    /**
     * php artisan congressmen:import-photos
     * php artisan congressmen:import-photos "C:\path\to\photos"
     */
    protected $signature = 'congressmen:import-photos {path? : Photo folder (default: database/data/photos)}';

    protected $description = 'Match scraped photos to congressmen by name, resize them, and store them on the public disk.';

    public function handle(): int
    {
        if (! function_exists('imagecreatefromstring')) {
            $this->error('The PHP GD extension is required to resize photos. Enable ext-gd and try again.');
            return self::FAILURE;
        }

        $dir = $this->argument('path') ?: database_path('data/photos');
        if (! is_dir($dir)) {
            $this->error("Folder not found: {$dir}");
            return self::FAILURE;
        }

        // name key => member. Each name gets two keys so "Bañas" matches a file
        // saved as "Banas" or as "Ba_as".
        $index = [];
        foreach (Congressman::all() as $member) {
            foreach ($this->keys($member->name) as $key) {
                $index[$key] = $member;
            }
        }

        $matched = 0;
        $unmatched = [];

        foreach (File::files($dir) as $file) {
            $name = $file->getFilename();
            if (! in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }

            $member = $index[$this->key(pathinfo($name, PATHINFO_FILENAME))] ?? null;
            $jpeg = $member ? PhotoResizer::toJpeg(File::get($file->getPathname())) : null;

            if (! $member || ! $jpeg) {
                $unmatched[] = $name;
                continue;
            }

            $path = "congressmen/{$member->member_id}.jpg";
            Storage::disk('public')->put($path, $jpeg);
            $member->update(['photo_path' => $path]);
            $matched++;
        }

        $this->info("Imported {$matched} photo(s).");

        if ($unmatched) {
            $this->warn(count($unmatched) . ' file(s) could not be matched:');
            collect($unmatched)->take(25)->each(fn ($n) => $this->line('  - ' . $n));
        }

        $missing = Congressman::whereNull('photo_path')->count();
        $this->line("{$missing} member(s) still have no photo.");

        return self::SUCCESS;
    }

    /** Lower-case letters and digits only, accents folded: "Abalos__JC_M_" => "abalosjcm". */
    private function key(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($s)));
    }

    private function keys(string $name): array
    {
        $dropped = preg_replace('/[^a-z0-9]/', '', strtolower(preg_replace('/[^\x20-\x7E]/u', '', $name)));

        return array_values(array_unique([$this->key($name), $dropped]));
    }
}