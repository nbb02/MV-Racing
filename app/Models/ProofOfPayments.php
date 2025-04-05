<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProofOfPayments extends Model
{
    protected $fillable = [
        'order_id',
        'image',
        'reference',
        'amount',
    ];
}
