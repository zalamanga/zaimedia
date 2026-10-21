<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\UploadedFile;
use App\Models\User;
use App\Support\Pagination;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return User::query()
            ->when($request->q, fn ($q, $term) => $q->where(fn ($s) => $s
                ->where('username', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('full_name', 'like', "%{$term}%")))
            ->when($request->role && $request->role !== 'all', fn ($q, $role) => $q->where('role', $role))
            ->when($request->status && $request->status !== 'all', fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(Pagination::perPage($request, 20));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'date_of_birth' => ['nullable', 'date'],
            'website' => ['nullable', 'string', 'max:100'],
            'facebook' => ['nullable', 'string', 'max:100'],
            'twitter' => ['nullable', 'string', 'max:100'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'linkedin' => ['nullable', 'string', 'max:100'],
        ]);

        $request->user()->update($data);

        return response()->json($request->user()->fresh());
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', Password::min(8)],
        ]);

        abort_unless(password_verify($data['current_password'], $request->user()->password), 422, 'Password lama salah');

        $request->user()->update(['password' => Hash::make($data['new_password'])]);
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Password updated']);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:3072'],
        ]);

        $name = UploadStorage::store($request->file('avatar'), $request->user(), 'avatar');
        $oldAvatar = $request->user()->avatar;
        $request->user()->update(['avatar' => $name]);
        if (UploadStorage::safeName($oldAvatar)) {
            Storage::disk('uploads')->delete('avatars/'.$oldAvatar);
            UploadedFile::where('user_id', $request->user()->id)
                ->where('kind', 'avatar')->where('name', $oldAvatar)->delete();
        }

        return response()->json($request->user()->fresh());
    }

    public function setRole(Request $request, User $user)
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Hanya super admin yang bisa mengubah role user.');
        abort_if($request->user()->id === $user->id, 422, 'Tidak bisa mengubah role akun sendiri');

        $data = $request->validate(['role' => ['required', Rule::in(['super_admin', 'admin', 'writer', 'customer'])]]);
        $before = $user->only(['role']);
        $user->update($data);

        AdminAuditLog::record($request, 'user.role_update', $user, [
            'before' => $before,
            'after' => $user->only(['role']),
        ]);

        return response()->json($user);
    }

    public function setStatus(Request $request, User $user)
    {
        abort_if($request->user()->id === $user->id, 422, 'Tidak bisa mengubah status akun sendiri');
        abort_if($user->isAdmin() && ! $request->user()->isSuperAdmin(), 403, 'Admin biasa tidak bisa mengubah status admin lain.');

        $data = $request->validate(['status' => ['required', Rule::in(['active', 'inactive', 'suspended'])]]);
        $before = $user->only(['status']);
        $user->update($data);

        if ($user->status !== 'active') {
            $user->tokens()->delete();
        }

        AdminAuditLog::record($request, 'user.status_update', $user, [
            'before' => $before,
            'after' => $user->only(['status']),
        ]);

        return response()->json($user);
    }
}
