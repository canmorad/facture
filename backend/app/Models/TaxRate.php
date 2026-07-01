<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'libelle',
        'rate',
        'motif_exoneration',
        'is_actif',
        'is_default',
    ];

    protected $casts = [
        'rate' => 'float',
        'is_actif' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}