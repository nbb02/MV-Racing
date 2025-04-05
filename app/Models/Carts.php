<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carts extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'designs',
        'types',
        'colors',
        'motor_types',
    ];

    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Products::class, 'product_id');
    }
}
