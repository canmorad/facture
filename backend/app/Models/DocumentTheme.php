<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTheme extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'font_family',
        'primary_color',
        'background_pattern',
        'table_border_style',
        'table_line_style',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}