<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role as RoleModel;

class Role extends RoleModel implements RoleContract
{
    use HasFactory;
}
