<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    protected $fillable = ['plan_id', 'proposal_id', 'user_id', 'tipo'];
}
