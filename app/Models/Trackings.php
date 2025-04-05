<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trackings extends Model
{
    protected $fillable = [
        'order_id',
        'title',
        'description',
    ];
}
