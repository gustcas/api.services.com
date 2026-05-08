<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'service_request_id',
        'client_id',
        'reference',
        'amount_in_cents',
        'currency',
        'wompi_transaction_id',
        'wompi_status',
        'status',
        'wompi_data',
        'paid_at',
    ];

    protected $casts = [
        'wompi_data' => 'array',
        'paid_at'    => 'datetime',
    ];

    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
