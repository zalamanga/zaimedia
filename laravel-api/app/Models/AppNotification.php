<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = ['user_id', 'type', 'title', 'message', 'action_url', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public static function send(int $userId, string $type, string $title, string $message, ?string $actionUrl = null): void
    {
        self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
