<?php

namespace App\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class AccessToken extends Model
{
    protected $table = 'access_tokens';
    protected $primaryKey = 'id';
    protected $casts = ['id' => 'string'];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
          'id'
        , 'user_id'
        , 'client_id'
        , 'name'
        , 'scopes'
        , 'revoked'
        , 'token_type'
        , 'expires_in'
        , 'expires_at'
    ];

}

