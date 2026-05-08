<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalPaymentInfo extends Model
{
    protected $fillable = [
        'professional_id',
        'payment_method',
        'id_type',
        'id_number',
        'full_name',
        'email',
        'bank_id',
        'bank_code',
        'bank_name',
        'account_type',
        'account_number',
    ];

    public function professional()
    {
        return $this->belongsTo(Professional::class);
    }

    /** Etiqueta legible del método de pago */
    public function getMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'nequi'        => 'Nequi',
            'daviplata'    => 'Daviplata',
            default        => "Cuenta {$this->account_type} — {$this->bank_name}",
        };
    }
}
