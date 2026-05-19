<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AuthLog extends Model
{
    use HasUuids;

    protected $table = 'auth_logs';
    protected $fillable = ['user_id', 'description', 'login_ip'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
