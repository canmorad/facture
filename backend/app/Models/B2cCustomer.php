<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class B2cCustomer extends Model
{
    protected $table = 'b2c_customers';

    protected $fillable = [
        'name',
        'cin',
    ];

    public function customer(): MorphOne
    {
        return $this->morphOne(Customer::class, 'customerable');
    }
}