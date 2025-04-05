<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QR extends Model
{
    protected $table = 'qr';

    protected $fillable = [
        'description',
        'image',
    ];
}
