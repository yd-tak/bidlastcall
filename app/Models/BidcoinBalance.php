<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BidcoinBalance extends Model {
    use HasFactory;

    protected $dates = ['created_at', 'updated_at'];

    protected $fillable = [
        'user_id',
        'debit',
        'credit',
        'trx',
        'trx_id',
        'notes'
    ];

    public function debit($user_id,$amount,$notes,$trx,$trx_id) {
        return $this->insert([
            'user_id'=>$user_id,
            'debit'=>$amount,
            'credit'=>0,
            'trx'=>$trx,
            'trx_id'=>$trx_id
        ]);   
    }
    public function credit($user_id,$amount,$notes,$trx,$trx_id) {
        return $this->insert([
            'user_id'=>$user_id,
            'debit'=>0,
            'credit'=>$amount,
            'trx'=>$trx,
            'trx_id'=>$trx_id
        ]);   
    }
}
