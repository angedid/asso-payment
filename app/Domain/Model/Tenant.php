<?php

namespace App\Domain\Model;

use Illuminate\Database\Eloquent\Model;
use Nanorocks\LicenseManager\Models\License;
use Nanorocks\LicenseManager\Services\LicenseService;

class Tenant extends Model
{
    protected $table = 'tenants';
    protected $primaryKey = 'id';
    protected $casts = ['id' => 'string'];
    public $incrementing = false;

    protected $keyType = 'string';
    protected $fillable = [
          'id'
        , 'name'
        , 'city'
        , 'location'
        , 'region'
        , 'country'
        , 'logo'
        , 'phone_number'
        , 'website'
        , 'address'
        , 'active'
        , 'taxpayer_number'
        , 'number_trade_register'
        , 'license_id'
        , 'owner'
        , 'network'
    ];

}
