<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'royalty_percentage' => [70, 'number'],
            'tax_rate' => [0.11, 'number'],
            'minimum_withdrawal' => [100000, 'number'],
            'maximum_withdrawal' => [10000000, 'number'],
            'checkout_auto_paid' => [config('zaimedia.checkout_auto_paid') ? 'true' : 'false', 'boolean'],
            'platform_name' => ['Zaimedia', 'string'],
        ];
        foreach ($settings as $key => [$value, $type]) {
            SystemSetting::firstOrCreate(['key' => $key], ['value' => (string) $value, 'type' => $type]);
        }
    }
}
