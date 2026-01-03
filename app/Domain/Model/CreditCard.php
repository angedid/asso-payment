<?php

namespace App\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class CreditCard extends Model
{
    protected $table = 'credit_cards';
    protected $primaryKey = 'id';
    protected $casts = ['id' => 'string'];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id'
        , 'tenant_id'
        , 'holder_id'
        , 'card_number'
        , 'holder_name'
        , 'expiration_date'
        , 'issuer_name'
        , 'active'
    ];

}
