<?php

namespace App\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class EWallet extends Model
{
    protected $table = 'e_wallets';
    protected $primaryKey = 'id';
    protected $casts = ['id' => 'string'];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id'
        , 'holder_id'
        , 'accounts'
        , 'active'
    ];
}

