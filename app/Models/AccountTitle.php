<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountTitle extends Model
{
    protected $fillable = ['rca_code', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->name.' — '.$this->rca_code;
    }
}
