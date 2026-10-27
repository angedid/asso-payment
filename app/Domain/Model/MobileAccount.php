<?php

namespace App\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class MobileAccount extends Model
{
    protected $table = 'mobile_accounts';
    protected $primaryKey = 'id';
    protected $casts = ['id' => 'string'];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
          'id'
        , 'tenant_id'
        , 'holder_id'
        , 'phone_number'
        , 'holder_name'
        , 'issuer'
        , 'active'
    ];
}


