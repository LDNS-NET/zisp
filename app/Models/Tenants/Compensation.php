<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Models\User;

class Compensation extends Model
{
    protected $table = 'compensations';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'type',
        'duration_value',
        'duration_unit',
        'old_expires_at',
        'new_expires_at',
        'created_by',
        'reason',
    ];

    protected $casts = [
        'old_expires_at' => 'datetime',
        'new_expires_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(NetworkUser::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
