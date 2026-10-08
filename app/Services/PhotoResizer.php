<?php

namespace App\Services;

class PhotoResizer
{
    /** Re-encode any image as JPEG, longest side at most $max px. Null if the bytes aren't a readable image. */
    public static function toJpeg(string $binary, int $max = 480): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $img = @imagecreatefromstring($binary);
        if (! $img) {
            return null;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        if (max($w, $h) > $max) {
            $scaled = imagescale($img, $w >= $h ? $max : (int) round($w * $max / $h));
            if ($scaled) {
                $img = $scaled;
            }
        }

        ob_start();
        imagejpeg($img, null, 85);

        return ob_get_clean();
    }
}