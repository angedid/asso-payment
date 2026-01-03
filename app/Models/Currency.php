<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'currencies';
    protected $primaryKey = 'id';
    protected $casts = ['id' => 'string'];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id'
        , 'name'
        , 'symbol'
        , 'is_fund'
        , 'is_complimentary'
        , 'is_metal'
        , 'sk_currency'
    ];
}
