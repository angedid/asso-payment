<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';
    protected $primaryKey = 'id';
    //protected $casts = ['id' => 'string'];
    //public $incrementing = false;

    //protected $keyType = 'string';
    protected $fillable = [
        'id'
        , 'code'
        , 'name'
    ];
}
