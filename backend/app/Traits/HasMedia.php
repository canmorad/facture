<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Http\UploadedFile;

trait HasMedia
{
    use MediaUpload;

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function attachMedia(UploadedFile $file, string $folder): Media
    {
        $path = $this->upload($file, $folder);

        return $this->media()->create([
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);
    }
}