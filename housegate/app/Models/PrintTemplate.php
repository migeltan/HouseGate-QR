<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintTemplate extends Model
{
    protected $fillable = ['kind', 'name', 'settings', 'created_by'];

    protected $casts = ['settings' => 'array'];
}