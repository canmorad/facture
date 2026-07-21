<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use LogsActivityTrait;
    protected $fillable = [
        'user_id',
        'client_id',
        'number',
        'date',
        'transporteur',
        'tracking_number',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function items()
    {
        return $this->hasMany(DeliveryItem::class);
    }
}