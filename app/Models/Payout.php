<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    protected $fillable = [
        'service_request_id',
        'professional_id',
        'reference',
        'amount',
        'payment_method',
        'bank_name',
        'account_type',
        'account_number',
        'wompi_payout_id',
        'wompi_status',
        'status',
        'triggered_by',
        'wompi_response',
        'paid_at',
    ];

    protected $casts = [
        'wompi_response' => 'array',
        'paid_at'        => 'datetime',
    ];

    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function professional()
    {
        return $this->belongsTo(Professional::class);
    }
}
