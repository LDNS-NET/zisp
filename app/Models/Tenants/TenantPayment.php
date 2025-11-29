<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;

class TenantPayment extends Model
{
    protected $table = "tenant_payments";

    protected $fillable = [
        "user_id",
        "phone",
        "receipt_number",
        "amount",
        "checked",
        "paid_at",
        "created_by",
        "disbursement_type",
        "package_id",
        "status",
        "intasend_reference",
        "intasend_checkout_id",
        "transaction_id",
        "response",
    ];
  protected $casts = [
        'checked' => 'boolean',
        'paid_at' => 'datetime',
        'response' => 'array',
    ];
    public function user()
    {
        return $this->belongsTo(NetworkUser::class, 'user_id');
    }


    protected static function booted()
    {
        static::addGlobalScope('created_by', function ($query) {
            if (auth()->check()) {
                $query->where('created_by', auth()->id());
            }
        });
        static::creating(function ($model) {
            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }
}
