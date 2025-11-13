<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleToken extends Model
{
    protected $fillable = ['user_id', 'access_token', 'refresh_token', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];
}
