<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Orders extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'payment_method',
        'total',
        'address_id',
        'delivery_partner',
        'tracking_no',
        'decline_reason'
    ];

    public function items()
    {
        return $this->hasMany(OrderItems::class, 'order_id');
    }

    public function payments()
    {
        return $this->hasMany(ProofOfPayments::class, 'order_id');
    }

    public function trackings()
    {
        return $this->hasMany(Trackings::class, 'order_id');
    }

    public function address()
    {
        return $this->belongsTo(Addresses::class, 'id', 'address_id');
    }
}
