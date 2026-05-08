<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    protected $fillable = [
        'client_id',
        'category_id',
        'service_id',
        'description',
        'address',
        'lat',
        'lng',
        'city_id',
        'service_date',
        'service_time',
        'people_count',
        'people_names',
        'people_identifications',
        'budget',
        'status',
        'payment_status',
        'payout_status',
        'professional_id',
        'completion_code',
        'completion_code_expires_at'
    ];

    protected $casts = [
        'people_names' => 'array',
        'people_identifications' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(User::class,'client_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function professional()
    {
        return $this->belongsTo(Professional::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function payout()
    {
        return $this->hasOne(Payout::class);
    }
}
