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
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function bidcoinpackage(){
        return $this->belongsTo(BidcoinPackage::class,'bidcoin_package_id');
    }
    public function getImgAttribute($img) {
        if (!empty($img)) {
            return url(Storage::url($img));
        }
        return $img;
    }

    
}
