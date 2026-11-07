<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['merchant_id', 'phone', 'name', 'automation_opted_out'];

    protected function casts(): array
    {
        return ['automation_opted_out' => 'boolean'];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }
}
