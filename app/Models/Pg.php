<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class Pg extends Model {
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'method',
        'bank',
        'accname',
        'accnum',
        'is_active'
    ];

    
}
