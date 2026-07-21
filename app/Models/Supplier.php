<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'contact_person', 'contact_number', 'email', 'address', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
