<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNote extends Model
{
    protected $fillable = ['merchant_id', 'customer_id', 'user_id', 'body'];

    protected $hidden = ['body'];

    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
