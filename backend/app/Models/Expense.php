<?php

namespace App\Models;

use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasMedia;

    protected $fillable = [
        'company_id',
        'supplier_id',
        'reference',
        'issue_date',
        'due_date',
        'total_ht',
        'total_tva',
        'total_ttc',
        'status',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'total_ht' => 'float',
        'total_tva' => 'float',
        'total_ttc' => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}