<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AppNotification;
use App\Models\Book;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Support\Checkout;
use App\Support\OrderLedger;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.book_id' => ['required', 'exists:books,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'shipping_name' => ['required', 'string', 'max:100'],
            'shipping_phone' => ['required', 'string', 'max:20'],
            'shipping_address' => ['required', 'string', 'max:2000'],
            'shipping_city' => ['required', 'string', 'max:50'],
            'shipping_postal_code' => ['required', 'string', 'max:10'],
            'shipping_method' => ['nullable', Rule::in(['regular', 'express', 'same_day'])],
            'payment_method' => ['nullable', Rule::in(['bank_transfer', 'credit_card', 'e_wallet', 'cod'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($data, $request) {
            $items = collect($data['items'])
                ->groupBy(fn ($item) => (int) $item['book_id'])
                ->map(fn ($rows, $bookId) => [
                    'book_id' => (int) $bookId,
                    'quantity' => min(10, $rows->sum(fn ($row) => (int) ($row['quantity'] ?? 1))),
                ])
                ->values();

            $ids = $items->pluck('book_id');
            $books = Book::whereIn('id', $ids)->where('status', 'approved')->get()->keyBy('id');

            $resolved = $items->map(function ($item) use ($books, $request) {
                $book = $books->get((int) $item['book_id']);
                abort_if(! $book, 422, 'Book tidak tersedia');
                abort_if((int) $book->writer_id === (int) $request->user()->id, 422, 'Writer tidak bisa membeli buku sendiri.');
                $qty = max(1, (int) ($item['quantity'] ?? 1));
                $subtotal = (float) $book->price * $qty;

                return compact('book', 'qty', 'subtotal');
            });

            $subtotal = $resolved->sum('subtotal');
            $tax = round($subtotal * (float) SystemSetting::value('tax_rate', 0.11));

            $order = Order::create([
                'customer_id' => $request->user()->id,
                'order_number' => 'ORD-'.Str::ulid(),
                'subtotal' => $subtotal,
                'shipping_cost' => 0,
                'tax' => $tax,
                'total' => $subtotal + $tax,
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'payment_status' => Checkout::autoPaid() ? 'paid' : 'pending',
                'order_status' => Checkout::autoPaid() ? 'processing' : 'pending',
                'shipping_name' => $data['shipping_name'],
                'shipping_phone' => $data['shipping_phone'],
                'shipping_address' => $data['shipping_address'],
                'shipping_city' => $data['shipping_city'],
                'shipping_postal_code' => $data['shipping_postal_code'],
                'shipping_method' => $data['shipping_method'] ?? 'regular',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($resolved as $row) {
                $order->items()->create([
                    'book_id' => $row['book']->id,
                    'quantity' => $row['qty'],
                    'price' => $row['book']->price,
                    'subtotal' => $row['subtotal'],
                ]);
            }

            if ($order->payment_status === 'paid') {
                OrderLedger::pay($order);
            }

            AppNotification::send($request->user()->id, 'order.created', 'Order berhasil dibuat', "Order {$order->order_number} berhasil dibuat.", '/dashboard?tab=orders');

            return response()->json($order->load('items.book'), 201);
        });
    }

    public function mine(Request $request)
    {
        return Order::with('items.book')
            ->where('customer_id', $request->user()->id)
            ->latest()
            ->get();
    }

    public function show(Request $request, Order $order)
    {
        if (! $request->user()->isAdmin() && $order->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $order->load(['customer:id,full_name,email', 'items.book']);
    }

    public function showByNumber(Request $request, string $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        if (! $request->user()->isAdmin() && $order->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $order->load(['customer:id,full_name,email', 'items.book']);
    }

    public function adminIndex(Request $request)
    {
        return Order::with(['customer:id,full_name,email', 'items.book'])
            ->when($request->q, fn ($q, $term) => $q->where(fn ($s) => $s
                ->where('order_number', 'like', "%{$term}%")
                ->orWhere('shipping_name', 'like', "%{$term}%")
                ->orWhere('shipping_city', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))))
            ->when($request->payment_status && $request->payment_status !== 'all', fn ($q, $status) => $q->where('payment_status', $status))
            ->when($request->order_status && $request->order_status !== 'all', fn ($q, $status) => $q->where('order_status', $status))
            ->latest()
            ->paginate(Pagination::perPage($request, 20));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'order_status' => ['nullable', Rule::in(['pending', 'processing', 'shipped', 'delivered', 'cancelled'])],
            'payment_status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'refunded'])],
        ]);

        return DB::transaction(function () use ($request, $order, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $before = $order->only(['payment_status', 'order_status']);
            $nextPayment = $data['payment_status'] ?? $order->payment_status;
            $nextStatus = $data['order_status'] ?? $order->order_status;
            $allowed = match ($order->payment_status) {
                'pending' => ['pending', 'paid', 'failed'],
                'failed' => ['failed', 'pending', 'paid'],
                'paid' => ['paid', 'refunded'],
                'refunded' => ['refunded'],
                default => [],
            };
            abort_unless(in_array($nextPayment, $allowed, true), 422, 'Transisi pembayaran tidak valid.');
            abort_if($order->order_status === 'cancelled' && $nextStatus !== 'cancelled', 422, 'Order yang dibatalkan tidak dapat dibuka ulang.');
            abort_if($nextStatus === 'cancelled' && $nextPayment === 'paid', 422, 'Catat refund sebelum membatalkan order lunas.');
            abort_if(in_array($nextStatus, ['shipped', 'delivered'], true) && $nextPayment !== 'paid', 422, 'Order belum lunas.');
            if ($nextPayment === 'refunded') {
                $nextStatus = 'cancelled';
            }
            if ($nextPayment === 'paid' && $order->payment_status !== 'paid') {
                abort_if($order->order_status === 'cancelled', 422, 'Order dibatalkan.');
                OrderLedger::pay($order);
            } elseif ($nextPayment === 'refunded' && $order->payment_status === 'paid') {
                OrderLedger::refund($order);
            }
            $order->update(['payment_status' => $nextPayment, 'order_status' => $nextStatus]);
            if ($before !== $order->only(['payment_status', 'order_status'])) {
                AdminAuditLog::record($request, 'order.status_update', $order, [
                    'before' => $before,
                    'after' => $order->only(['payment_status', 'order_status']),
                ]);
            }

            return response()->json($order->fresh()->load('items.book'));
        });
    }
}
