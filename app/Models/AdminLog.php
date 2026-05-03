<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLog extends Model
{
    protected $fillable = [
        'admin_id', 'admin_name', 'action', 'entity',
        'entity_id', 'description', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public static function record(User $admin, string $action, string $entity, ?int $entityId, string $description, array $meta = [])
    {
        static::create([
            'admin_id'    => $admin->id,
            'admin_name'  => $admin->name,
            'action'      => $action,
            'entity'      => $entity,
            'entity_id'   => $entityId,
            'description' => $description,
            'meta'        => empty($meta) ? null : $meta,
        ]);
    }
}
