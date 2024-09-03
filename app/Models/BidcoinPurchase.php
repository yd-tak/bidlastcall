<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BidcoinPurchase extends Model {
    use HasFactory;

    protected $dates = ['created_at', 'updated_at'];

    protected $fillable = [
        'user_id',
        'bidcoin_package_id',
        'price',
        'bidcoin',
        'img',
        'status'
    ];

    
}
