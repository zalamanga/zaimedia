<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_.-]+$/', 'unique:users,username'],
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $data['role'] = 'customer';
        $data['status'] = 'active';
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('zaimedia')->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])
            ->orWhere('username', $data['email'])
            ->first();

        if (! $user || ! password_verify($data['password'], $user->password)) {
            return response()->json(['message' => 'Email/username atau password salah'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Akun belum aktif'], 403);
        }

        $user->tokens()->delete();

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('zaimedia')->plainTextToken,
        ]);
    }

    public function me(Request $request)
    {
        return $request->user();
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
