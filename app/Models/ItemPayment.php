<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ItemPayment extends Model {
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_id',
        'item_bid_id',
        'pg_id',
        'paymentdate',
        'amount',
        'accnum',
        'status',
        'shippingfee',
        'shippingetd',
        'shippingservice',
        'img',
        'imgtransfer',
        'istransfered'
    ];
    public function item(){
        return $this->belongsTo(Item::class);
    }
    public function item_bid(){
        return $this->belongsTo(ItemBid::class);
    }
    public function pg(){
        return $this->belongsTo(Pg::class);
    }
    public function getImgAttribute($img) {
        if (!empty($img)) {
            return url(Storage::url($img));
        }
        return $img;
    }
    public function getImgtransferAttribute($imgtransfer) {
        if (!empty($imgtransfer)) {
            return url(Storage::url($imgtransfer));
        }
        return $imgtransfer;
    }

    
}
