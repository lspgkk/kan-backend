<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SikiAuth extends Model
{
    protected $table = 'siki_auths';

    protected $fillable = [
        'name',
        'base_url',
        'token',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
