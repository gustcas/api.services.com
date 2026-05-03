<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkEvidence extends Model
{
    use HasFactory;

    protected $table = 'work_evidences';

    protected $fillable = [
        'service_request_id',
        'professional_id',
        'file_path',
        'file_type',
        'note',
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
