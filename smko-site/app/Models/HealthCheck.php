<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthCheck extends Model
{
    protected $primaryKey = 'name';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['name', 'ok', 'message', 'checked_at', 'changed_at'];

    protected function casts(): array
    {
        return ['ok' => 'boolean', 'checked_at' => 'datetime', 'changed_at' => 'datetime'];
    }
}
