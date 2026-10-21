<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use App\Models\Review;
use App\Models\Sale;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WriterRequest;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $monthlyRevenue = Order::where('payment_status', 'paid')
                ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
                ->get(['created_at', 'total'])->groupBy(fn ($order) => $order->created_at->format('Y-m'))
                ->sortKeys()->map(fn ($rows, $month) => [
                    'month' => $month, 'revenue' => (float) $rows->sum('total'), 'order_count' => $rows->count(),
                ])->values();

            $topBooks = Sale::query()
                ->selectRaw('book_id, SUM(quantity) as total_sold, SUM(book_price * quantity) as total_revenue')
                ->with('book:id,title,author')
                ->groupBy('book_id')
                ->orderByDesc('total_sold')
                ->limit(5)
                ->get()
                ->map(fn ($sale) => [
                    'book_id' => $sale->book_id,
                    'title' => $sale->book?->title ?? 'Deleted book',
                    'author' => $sale->book?->author,
                    'total_sold' => (int) $sale->total_sold,
                    'total_revenue' => (float) $sale->total_revenue,
                ]);

            return [
                'users' => User::count(),
                'books' => Book::withTrashed()->count(),
                'pending_books' => Book::where('status', 'pending')->count(),
                'approved_books' => Book::where('status', 'approved')->count(),
                'orders' => Order::count(),
                'paid_orders' => Order::where('payment_status', 'paid')->count(),
                'revenue' => Order::where('payment_status', 'paid')->sum('total'),
                'withdrawals' => Withdrawal::where('status', 'pending')->count(),
                'writer_requests' => WriterRequest::where('status', 'pending')->count(),
                'hidden_reviews' => Review::where('status', 'hidden')->count(),
                'monthly_revenue' => $monthlyRevenue,
                'top_books' => $topBooks,
                'recent_orders' => Order::with('customer:id,full_name,email')
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'customer_id', 'order_number', 'total', 'payment_status', 'order_status', 'created_at']),
                'pending_book_queue' => Book::with('writer:id,full_name,username')
                    ->where('status', 'pending')
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'writer_id', 'title', 'author', 'created_at']),
            ];
        }

        if ($user->isWriter()) {
            return [
                'books' => Book::where('writer_id', $user->id)->count(),
                'approved_books' => Book::where('writer_id', $user->id)->where('status', 'approved')->count(),
                'pending_books' => Book::where('writer_id', $user->id)->where('status', 'pending')->count(),
                'rejected_books' => Book::where('writer_id', $user->id)->where('status', 'rejected')->count(),
                'total_views' => Book::where('writer_id', $user->id)->sum('views'),
                'books_sold' => Sale::where('writer_id', $user->id)->sum('quantity'),
                'sales' => Sale::where('writer_id', $user->id)->sum('royalty_amount'),
                'balance' => $user->writerBalance()->firstOrCreate(['writer_id' => $user->id]),
            ];
        }

        return [
            'orders' => Order::where('customer_id', $user->id)->count(),
            'paid_orders' => Order::where('customer_id', $user->id)->where('payment_status', 'paid')->count(),
            'spend' => Order::where('customer_id', $user->id)->where('payment_status', 'paid')->sum('total'),
        ];
    }
}
