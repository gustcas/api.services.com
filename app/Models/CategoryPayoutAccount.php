<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryPayoutAccount extends Model
{
    protected $fillable = [
        'category_id',
        'payout_account_id',
        'entity_type',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function payoutAccount()
    {
        return $this->belongsTo(PayoutAccount::class);
    }
}
