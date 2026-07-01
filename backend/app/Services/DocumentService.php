<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Auth;

class DocumentService
{
    public function create(array $data): Document
    {
        return Document::create($data);
    }

    public function findOrFail(int $id): Document
    {
        return Document::with('documentable')->findOrFail($id);
    }

    public function updateNumber(Document $document, string $number): void
    {
        $document->update(['number' => $number]);
    }
}