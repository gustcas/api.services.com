<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id', 'subject', 'message', 'type',
        'status', 'priority', 'admin_reply', 'replied_at', 'replied_by',
    ];

    protected $casts = [
        'replied_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function repliedByUser()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
