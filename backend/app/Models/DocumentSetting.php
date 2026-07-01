<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'document_type',
        'hide_signature_block',
        'show_username_pdf',
        'intro_text',
        'conclusion_text',
        'footer_text',
        'terms',
        'notes',
    ];

    protected $casts = [
        'hide_signature_block' => 'boolean',
        'show_username_pdf' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}