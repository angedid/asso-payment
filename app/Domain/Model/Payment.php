<?php

namespace App\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public const PENDING  = 'PENDING';
    public const SUCCESS  = 'SUCCESS';
    public const ACCEPTED = 'ACCEPTED';
    public const FAILED   = 'FAILED';

    protected $table = 'payments';
    protected $primaryKey = 'id';
    protected $casts = ['id' => 'string'];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
          'id'
        , 'tenant_id'
        , 'holder_id'
        , 'payment_service_provider_id'
        , 'account_used'
        , 'amount'
        , 'currency_id'
        , 'order_number'
        , 'start_date'
        , 'end_date'
        , 'status'
        , 'details'
    ];

}


