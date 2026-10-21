<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'zaimedia:create-admin {--username=} {--email=} {--name=} {--role=super_admin} {--password-env=ZAIMEDIA_ADMIN_PASSWORD}';

    protected $description = 'Create an administrator using explicit account details and a secret prompt/environment variable';

    public function handle(): int
    {
        $password = getenv((string) $this->option('password-env'));
        if (! $password && $this->input->isInteractive()) {
            $password = $this->secret('Password admin (minimal 8 karakter)');
        }
        $data = [
            'username' => $this->option('username'), 'email' => $this->option('email'),
            'full_name' => $this->option('name'), 'role' => $this->option('role'),
            'password' => $password ?: null,
        ];
        $validator = Validator::make($data, [
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_.-]+$/', 'unique:users,username'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'full_name' => ['required', 'string', 'max:100'],
            'role' => ['required', Rule::in(['admin', 'super_admin'])],
            'password' => ['required', 'string', Password::min(8)],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        $data['password'] = Hash::make($data['password']);
        User::create($data + ['status' => 'active']);
        $this->info('Akun admin dibuat. Password tidak ditampilkan.');

        return self::SUCCESS;
    }
}
