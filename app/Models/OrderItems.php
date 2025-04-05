<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItems extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'total',
        'designs',
        'types',
        'colors',
        'motor_types',
    ];

    public $timestamps = false;
}
