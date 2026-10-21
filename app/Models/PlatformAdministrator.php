<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAdministrator extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
