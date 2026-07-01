<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class B2bCustomer extends Model
{
    protected $table = 'b2b_customers';

    protected $fillable = [
        'legal_name',
        'ice',
        'rc',
        'if',
        'patente',
    ];

    public function customer(): MorphOne
    {
        return $this->morphOne(Customer::class, 'customerable');
    }
}