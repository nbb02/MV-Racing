<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addresses extends Model
{
    protected $fillable = [
        'user_id',
        'recipient',
        'phone',
        'region_city_district',
        'street_building',
        'unit_floor',
        'additional_info',
    ];

    public $timestamps = false;
}
