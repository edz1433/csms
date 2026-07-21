<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = ['type', 'code', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    public function counter()
    {
        return $this->hasOne(LocationReleaseCounter::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->name.' ['.ucfirst($this->type).']';
    }
}
