<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStocks extends Model
{
    protected $fillable = [
        'product_id',
        'stocks',
        'designs',
        'types',
        'colors',
        'motor_types',
    ];
}
