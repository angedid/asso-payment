<?php

namespace App\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class Holder extends Model
{
    protected $table = 'holders';
    protected $primaryKey = 'id';
    protected $casts = ['id' => 'string'];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
          'id'
        , 'tenant_id'
        , 'type'
        , 'account_id'
        , 'full_name'
        , 'username'
        , 'email'
        , 'phone_numbers'
        , 'active'
    ];
}
