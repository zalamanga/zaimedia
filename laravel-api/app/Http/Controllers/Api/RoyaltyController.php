<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AppNotification;
use App\Models\Sale;
use App\Models\SystemSetting;
use App\Models\Withdrawal;
use App\Models\WriterBalance;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoyaltyController extends Controller
{
    public function balance(Request $request)
    {
        return $request->user()->writerBalance()->firstOrCreate(['writer_id' => $request->user()->id]);
    }

    public function sales(Request $request)
    {
        return Sale::with(['book:id,title,cover_image,writer_id', 'order:id,order_number'])
            ->where('writer_id', $request->user()->id)
            ->latest('sale_date')
            ->get();
    }

    public function withdrawals(Request $request)
    {
        return Withdrawal::where('writer_id', $request->user()->id)->latest()->get();
    }

    public function requestWithdrawal(Request $request)
    {
        $min = (int) SystemSetting::value('minimum_withdrawal', 100000);
        $max = (int) SystemSetting::value('maximum_withdrawal', 10000000);
        $data = $request->validate([
            'amount' => ['required', 'numeric', "min:{$min}", "max:{$max}"],
            'bank_name' => ['required', 'string', 'max:50'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($request, $data) {
            $balance = WriterBalance::where('writer_id', $request->user()->id)->lockForUpdate()->firstOrCreate([
                'writer_id' => $request->user()->id,
            ]);

            abort_if($balance->available_balance < $data['amount'], 422, 'Saldo tidak cukup');

            $balance->decrement('available_balance', $data['amount']);
            $balance->increment('pending_balance', $data['amount']);

            $number = 'WD-'.Str::ulid();

            $withdrawal = Withdrawal::create($data + [
                'writer_id' => $request->user()->id,
                'withdrawal_number' => $number,
                'withdrawal_code' => $number,
                'status' => 'pending',
            ]);

            AppNotification::send($request->user()->id, 'withdrawal.requested', 'Withdrawal diajukan', "Withdrawal {$withdrawal->withdrawal_number} sedang menunggu proses admin.", '/dashboard?tab=withdrawals');

            return response()->json($withdrawal, 201);
        });
    }

    public function adminWithdrawals(Request $request)
    {
        return Withdrawal::with('writer:id,full_name,email')
            ->when($request->q, fn ($q, $term) => $q->where(fn ($s) => $s
                ->where('withdrawal_number', 'like', "%{$term}%")
                ->orWhere('withdrawal_code', 'like', "%{$term}%")
                ->orWhere('bank_name', 'like', "%{$term}%")
                ->orWhere('account_name', 'like', "%{$term}%")
                ->orWhereHas('writer', fn ($w) => $w->where('full_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))))
            ->when($request->status && $request->status !== 'all', fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(Pagination::perPage($request, 20));
    }

    public function adminSales(Request $request)
    {
        return Sale::with(['book:id,title,cover_image,writer_id', 'order:id,order_number'])
            ->with('book.writer:id,full_name,email')
            ->when($request->q, fn ($q, $term) => $q->where(fn ($s) => $s
                ->whereHas('book', fn ($b) => $b->where('title', 'like', "%{$term}%"))
                ->orWhereHas('book.writer', fn ($w) => $w->where('full_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$term}%"))))
            ->latest('sale_date')
            ->paginate(Pagination::perPage($request, 20));
    }

    public function process(Request $request, Withdrawal $withdrawal)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'processing', 'completed', 'rejected'])],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string'],
        ]);

        return DB::transaction(function () use ($request, $withdrawal, $data) {
            $withdrawal = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if ($withdrawal->status === $data['status']) {
                return response()->json($withdrawal->load('writer:id,full_name,email'));
            }
            $allowed = match ($withdrawal->status) {
                'pending' => ['approved', 'processing', 'completed', 'rejected'],
                'approved' => ['processing', 'completed', 'rejected'],
                'processing' => ['completed', 'rejected'],
                default => [],
            };
            abort_unless(in_array($data['status'], $allowed, true), 422, 'Transisi penarikan tidak valid.');
            $before = $withdrawal->only(['status', 'rejection_reason', 'processed_by']);
            $balance = WriterBalance::where('writer_id', $withdrawal->writer_id)->lockForUpdate()->firstOrFail();
            abort_if((float) $balance->pending_balance < (float) $withdrawal->amount, 422, 'Saldo penarikan tidak konsisten.');

            if (in_array($withdrawal->status, ['pending', 'approved', 'processing'], true) && $data['status'] === 'rejected') {
                $balance->decrement('pending_balance', $withdrawal->amount);
                $balance->increment('available_balance', $withdrawal->amount);
            }

            if (in_array($withdrawal->status, ['pending', 'approved', 'processing'], true) && $data['status'] === 'completed') {
                $balance->decrement('pending_balance', $withdrawal->amount);
                $balance->increment('total_withdrawn', $withdrawal->amount);
                $data['completed_at'] = now();
            }

            if ($data['status'] !== 'rejected') {
                $data['rejection_reason'] = null;
            }

            $data['processed_by'] = $request->user()->id;
            $data['processed_at'] = now();
            if ($data['status'] === 'approved') {
                $data['approved_by'] = $request->user()->id;
                $data['approved_at'] = now();
            }

            $withdrawal->update($data);

            AdminAuditLog::record($request, 'withdrawal.process', $withdrawal, [
                'before' => $before,
                'after' => $withdrawal->fresh()->only(['status', 'rejection_reason', 'processed_by']),
            ]);

            AppNotification::send($withdrawal->writer_id, 'withdrawal.processed', 'Withdrawal diproses', "Withdrawal {$withdrawal->withdrawal_number} statusnya {$withdrawal->status}.", '/dashboard?tab=withdrawals');

            return response()->json($withdrawal->fresh()->load('writer:id,full_name,email'));
        });
    }
}
