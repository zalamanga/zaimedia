<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public static function record(Request $request, string $action, Model $target, array $metadata = []): void
    {
        self::create([
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'target_type' => $target::class,
            'target_id' => $target->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
