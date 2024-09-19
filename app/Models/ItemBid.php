<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ItemBid extends Model {
    use HasFactory;

    protected $fillable = [
        'user_id',
        'item_id',
        'bid_amount',
        'bid_price',
        'tipe',
        'created_at',
        'updated_at'
    ];
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function item(){
        return $this->belongsTo(Item::class);
    }
}
