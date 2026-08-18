<?php

namespace App\Models;

use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role;

class ExamenUserRoles extends BaseCacheableModel
{
    use ActivityLogsTrait, HasFactory;

    protected $table = 'examen_user_roles';

    protected $fillable = [
        'examen_id',
        'user_id',
        'role_id',
    ];

    protected $casts = [
        'examen_id' => 'integer',
        'user_id' => 'integer',
        'role_id' => 'integer',
    ];

    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
