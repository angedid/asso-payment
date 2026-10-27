<?php

namespace App\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class PaymentServiceProvider extends Model
{
    public const MOBILEMONEY          = 'MOBILEMONEY';
    public const LOFOMBO              = 'LOFOMBO';
    public const CREDIT_CARD          = 'CREDIT_CARD';

    protected $table = 'payment_service_providers';
    protected $primaryKey = 'id';
    protected $casts = ['id' => 'string'];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
          'id'
        , 'name'
        , 'payment_method'
        , 'api_url'
        , 'icon'
    ];

}

