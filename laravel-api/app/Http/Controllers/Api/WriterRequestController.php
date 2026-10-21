<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AppNotification;
use App\Models\User;
use App\Models\WriterBalance;
use App\Models\WriterRequest;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WriterRequestController extends Controller
{
    public function mine(Request $request)
    {
        return WriterRequest::with('reviewer:id,full_name,email')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();
    }

    public function store(Request $request)
    {
        if ($request->user()->isAdmin()) {
            return response()->json(['message' => 'Admin tidak perlu mengajukan writer request.'], 422);
        }

        if ($request->user()->role === 'writer') {
            return response()->json(['message' => 'Akun kamu sudah aktif sebagai writer.'], 422);
        }

        $pending = WriterRequest::where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            return response()->json(['message' => 'Writer request kamu masih menunggu review admin.'], 422);
        }

        $data = $request->validate([
            'pen_name' => ['required', 'string', 'max:100'],
            'bio' => ['required', 'string', 'min:20', 'max:2000'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
        ]);

        $writerRequest = WriterRequest::create($data + [
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        User::whereIn('role', ['admin', 'super_admin'])->pluck('id')->each(fn ($adminId) => AppNotification::send(
            $adminId,
            'writer_request.created',
            'Writer request baru',
            "{$request->user()->full_name} mengajukan akses writer.",
            '/dashboard?tab=writerRequests'
        ));

        return response()->json($writerRequest->load('user:id,full_name,email,role'), 201);
    }

    public function index(Request $request)
    {
        return WriterRequest::with(['user:id,full_name,email,username,role,status', 'reviewer:id,full_name,email'])
            ->when($request->q, fn ($q, $term) => $q->where(fn ($s) => $s
                ->where('pen_name', 'like', "%{$term}%")
                ->orWhere('reason', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u
                    ->where('full_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"))))
            ->when($request->status && $request->status !== 'all', fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(Pagination::perPage($request, 20));
    }

    public function process(Request $request, WriterRequest $writerRequest)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
        ]);

        if ($writerRequest->status !== 'pending') {
            return response()->json(['message' => 'Writer request ini sudah diproses.'], 422);
        }

        $before = $writerRequest->only(['status', 'rejection_reason']);

        $writerRequest->update([
            'status' => $data['status'],
            'rejection_reason' => $data['status'] === 'rejected' ? ($data['rejection_reason'] ?? null) : null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($data['status'] === 'approved') {
            $user = User::findOrFail($writerRequest->user_id);
            $userBefore = $user->only(['role', 'bio']);
            $user->update([
                'role' => 'writer',
                'bio' => $writerRequest->bio,
            ]);
            WriterBalance::firstOrCreate(['writer_id' => $user->id]);

            AdminAuditLog::record($request, 'writer_request.approve', $writerRequest, [
                'before' => $before,
                'after' => $writerRequest->fresh()->only(['status', 'rejection_reason']),
                'user_before' => $userBefore,
                'user_after' => $user->fresh()->only(['role', 'bio']),
            ]);

            AppNotification::send($user->id, 'writer_request.approved', 'Writer request approved', 'Akun kamu sudah aktif sebagai writer.', '/dashboard?tab=books');
        } else {
            AdminAuditLog::record($request, 'writer_request.reject', $writerRequest, [
                'before' => $before,
                'after' => $writerRequest->fresh()->only(['status', 'rejection_reason']),
            ]);

            AppNotification::send($writerRequest->user_id, 'writer_request.rejected', 'Writer request rejected', $writerRequest->rejection_reason ?: 'Pengajuan writer kamu ditolak.', '/dashboard?tab=writerRequest');
        }

        return response()->json($writerRequest->fresh()->load(['user:id,full_name,email,username,role,status', 'reviewer:id,full_name,email']));
    }
}
