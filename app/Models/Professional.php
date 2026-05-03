<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Professional extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'service_id',
        'document_number',
        'identity_card',
        'professional_card',
        'professional_title',
        'photo',
        'phone',
        'bio',
        'address',
        'city_id', 
        'status',
        'is_verified'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function services()
    {
        return $this->belongsToMany(
            Service::class,
            'professional_services'
        );
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
