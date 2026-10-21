<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'type'];

    public static function value(string $key, mixed $default = null): mixed
    {
        $setting = self::find($key);
        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'number' => is_numeric($setting->value) ? (float) $setting->value : $default,
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            default => $setting->value,
        };
    }
}
