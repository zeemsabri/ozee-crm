<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait HandlesImageUploads
{
    /**
        * Upload an array of uploaded files to the given disk/path and generate thumbnails for images.
        *
        * @param array $files Array of Illuminate\\Http\\UploadedFile
        * @param string $basePath Base folder within the disk to store files
        * @param string $disk Storage disk name
        * @return array[] Each entry contains: filename, mime_type, file_size, path, and optional thumbnail
        * @throws \Exception on upload error
        */
    protected function uploadFilesToGcsWithThumbnails(array $files, string $basePath = 'task', string $disk = 'gcs'): array
    {
        $paths = [];

        foreach ($files as $file) {
            // Upload original file
            $objectPath = Storage::disk($disk)->putFile($basePath, $file);

            $entry = [
                'filename'   => $file->getClientOriginalName(),
                'mime_type'  => $file->getMimeType(),
                'file_size'  => $file->getSize(),
                'path'       => $objectPath,
            ];

            // If it's an image, generate and upload a thumbnail
            if ($this->isImageMime($entry['mime_type'])) {
//                try {
                    $thumbBinary = $this->makeThumbnailFromFile($file->getRealPath());
                    $dir = trim(dirname($objectPath), '.\\/');
                    $base = pathinfo($objectPath, PATHINFO_FILENAME);
                    $thumbPath = ($dir ? $dir.'/' : '') . 'thumbnails/' . $base . '-thumb.jpg';
                    Storage::disk($disk)->put($thumbPath, $thumbBinary);
                    $entry['thumbnail'] = $thumbPath;
//                } catch (\Throwable $thumbEx) {
//                    Log::warning('Thumbnail generation failed', [
//                        'file' => $entry['filename'],
//                        'error' => $thumbEx->getMessage(),
//                    ]);
//                }
            }

            $paths[] = $entry;
        }

        return $paths;
    }

    protected function isImageMime(?string $mime): bool
    {
        if (!$mime) return false;
        return str_starts_with($mime, 'image/');
    }

    /**
     * Create a simple JPEG thumbnail binary from the original file path.
     * Uses GD which is commonly available in PHP environments.
     *
     * @param string $path
     * @param int $maxDim
     * @return string JPEG binary
     */
    protected function makeThumbnailFromFile(string $path, int $maxDim = 300): string
    {
        // Determine image type and create image resource
        [$width, $height, $type] = getimagesize($path);

        switch ($type) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($path);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($path);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromgif($path);
                break;
            default:
                throw new \RuntimeException('Unsupported image type for thumbnail.');
        }

        // Calculate new dimensions preserving aspect ratio
        $ratio = $width > 0 ? ($height / $width) : 1;
        if ($width >= $height) {
            $newW = $maxDim;
            $newH = (int) round($maxDim * $ratio);
        } else {
            $newH = $maxDim;
            $newW = (int) round($maxDim / ($ratio > 0 ? $ratio : 1));
        }

        $thumb = imagecreatetruecolor($newW, $newH);

        // For PNG/GIF maintain transparency background as white
        $white = imagecolorallocate($thumb, 255, 255, 255);
        imagefill($thumb, 0, 0, $white);

        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

        // Capture JPEG binary
        ob_start();
        imagejpeg($thumb, null, 80);
        $binary = ob_get_clean();

        imagedestroy($src);
        imagedestroy($thumb);

        return $binary;
    }
}
