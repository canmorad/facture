<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile; 
use Illuminate\Support\Facades\Storage;

trait MediaUpload
{
    public function upload(UploadedFile $file, string $folder): string
    {
        $path = Storage::disk('public')->putFile($folder, $file);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}