<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutAccount extends Model
{
    protected $fillable = [
        'entity_name',
        'entity_type',
        'bank_name',
        'bank_code',
        'account_type',
        'account_number',
        'account_holder',
        'document_number',
        'email',
        'is_active',
    ];

    public function categories()
    {
        return $this->belongsToMany(
            Category::class,
            'category_payout_accounts',
            'payout_account_id',
            'category_id'
        )->withPivot('entity_type')->withTimestamps();
    }
}
