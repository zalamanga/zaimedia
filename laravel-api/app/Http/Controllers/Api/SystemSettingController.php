<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    public function index()
    {
        return response()->json(SystemSetting::orderBy('key')->get()->mapWithKeys(fn ($setting) => [
            $setting->key => SystemSetting::value($setting->key),
        ]));
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Hanya super admin yang bisa mengubah settings.');

        $data = $request->validate([
            'royalty_percentage' => ['required', 'numeric', 'min:1', 'max:95'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'minimum_withdrawal' => ['required', 'numeric', 'min:0'],
            'maximum_withdrawal' => ['required', 'numeric', 'gte:minimum_withdrawal'],
            'checkout_auto_paid' => ['required', 'boolean'],
            'platform_name' => ['required', 'string', 'max:100'],
        ]);

        $before = SystemSetting::orderBy('key')->get()->mapWithKeys(fn ($setting) => [$setting->key => SystemSetting::value($setting->key)])->all();
        $types = [
            'royalty_percentage' => 'number',
            'tax_rate' => 'number',
            'minimum_withdrawal' => 'number',
            'maximum_withdrawal' => 'number',
            'checkout_auto_paid' => 'boolean',
            'platform_name' => 'string',
        ];

        foreach ($data as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], [
                'value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
                'type' => $types[$key],
            ]);
        }

        AdminAuditLog::record($request, 'settings.update', $request->user(), [
            'before' => $before,
            'after' => $data,
        ]);

        return $this->index();
    }
}
