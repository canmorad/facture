<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class RecurringInvoice extends Model
{
    use LogsActivityTrait;
    protected $fillable = [
        'frequency',
        'start_date',
        'end_date',
        'next_run_date',
        'status',
        'last_generated_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_run_date' => 'date',
        'last_generated_at' => 'datetime',
    ];

    public function templateDocument(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }
}