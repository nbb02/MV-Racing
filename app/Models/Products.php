<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category',
        'price',
        'designs',
        'types',
        'colors',
        'motor_types',
    ];

    protected $casts = [
        'designs' => 'array',
        'types' => 'array',
        'colors' => 'array',
        'motor_types' => 'array',
    ];

    public function images()
    {
        return $this->hasMany(ProductImages::class, 'product_id');
    }

    public function stocks()
    {
        return $this->hasMany(ProductStocks::class, 'product_id');
    }
}
