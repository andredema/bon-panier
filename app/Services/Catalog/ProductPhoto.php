<?php

namespace App\Services\Catalog;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Photos de produits et images de catégories : réduites à 900 px de large au plus et converties en WebP
 * avec GD (disponible sur PlanetHoster), pour une page de commande légère.
 */
final class ProductPhoto
{
    private const MAX_WIDTH = 900;

    private const QUALITY = 80;

    public function store(UploadedFile $file, string $folder = 'products', int $maxWidth = self::MAX_WIDTH): string
    {
        $data = file_get_contents($file->getRealPath());
        $source = $data !== false ? @imagecreatefromstring($data) : false;

        if (! $source) {
            throw new RuntimeException('Image illisible.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1, $maxWidth / $width);
        $w = max(1, (int) round($width * $ratio));
        $h = max(1, (int) round($height * $ratio));

        $target = imagecreatetruecolor($w, $h);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $w, $h, $width, $height);

        ob_start();
        imagewebp($target, null, self::QUALITY);
        $webp = ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        $path = $folder.'/'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, $webp);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
