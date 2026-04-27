<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ak6DimensiKompetensi extends Model
{
    protected $table = 'ak6_dimensi_kompetensi';

    protected $fillable = [
        'id_izin',
        'task_skills',
        'task_management_skills',
        'contingency_management_skills',
        'job_role_environment_skills',
        'transfer_skills',
    ];

    public static function getByIzinId($id)
    {
        return self::where('id_izin', $id)->first();
    }
}
