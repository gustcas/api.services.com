<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [

        'category_id',
        'name',
        'price',
        'allies_percentage',
        'payment_gateway_commission',
        'imavicx_commission',
        'asecalidad_commission',
        'maintenance_percentage',
        'is_active'

    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function professionals()
    {
        return $this->belongsToMany(
            Professional::class,
            'professional_services'
        );
    }
}
