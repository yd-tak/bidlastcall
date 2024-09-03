<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BidcoinPackage extends Model {
    use HasFactory;

    protected $dates = ['created_at', 'updated_at'];

    protected $fillable = [
        'name',
        'price',
        'bidcoin',
        'normalbidcoin',
        'bonusbidcoin',
        'description',
        'status'
    ];

}
