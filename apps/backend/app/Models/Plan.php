<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['user_id', 'nombre', 'fecha_limite', 'estado'];

    public function participantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'plan_participants')->withTimestamps();
    }

    public function propuestas(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }
}
