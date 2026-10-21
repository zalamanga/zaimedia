<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Book;
use App\Models\Order;
use App\Models\Review;
use App\Support\Pagination;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function book(string $book)
    {
        $bookModel = Book::where('id', $book)->orWhere('slug', $book)->firstOrFail();

        return Review::with('customer:id,full_name')
            ->where('book_id', $bookModel->id)
            ->where('status', 'visible')
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        if (! in_array($request->user()->role, ['customer', 'writer'], true)) {
            return response()->json(['message' => 'Review hanya bisa dibuat oleh customer atau writer yang membeli buku.'], 403);
        }

        $data = $request->validate([
            'book_id' => ['required', 'exists:books,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:100'],
            'content' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $verified = Order::where('customer_id', $request->user()->id)
            ->where('payment_status', 'paid')
            ->whereHas('items', fn ($q) => $q->where('book_id', $data['book_id']))
            ->exists();

        $book = Book::findOrFail($data['book_id']);
        if ((int) $book->writer_id === (int) $request->user()->id) {
            return response()->json(['message' => 'Writer tidak bisa review buku sendiri.'], 422);
        }

        if (! $verified) {
            return response()->json(['message' => 'Kamu perlu membeli buku ini sebelum menulis review.'], 403);
        }

        if (Review::where('customer_id', $request->user()->id)->where('book_id', $data['book_id'])->exists()) {
            return response()->json(['message' => 'Kamu sudah menulis review untuk buku ini.'], 422);
        }

        $review = Review::create($data + [
            'customer_id' => $request->user()->id,
            'is_verified' => true,
        ]);

        return response()->json($review->load('customer:id,full_name'), 201);
    }

    public function adminIndex(Request $request)
    {
        return Review::with(['customer:id,full_name,email', 'book:id,title,slug'])
            ->when($request->q, fn ($q, $term) => $q->where(fn ($s) => $s
                ->where('title', 'like', "%{$term}%")
                ->orWhere('content', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                ->orWhereHas('book', fn ($b) => $b->where('title', 'like', "%{$term}%"))))
            ->when($request->status && $request->status !== 'all', fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(Pagination::perPage($request, 20));
    }

    public function moderate(Request $request, Review $review)
    {
        $data = $request->validate([
            'status' => ['required', 'in:visible,hidden'],
            'moderation_reason' => ['required_if:status,hidden', 'nullable', 'string', 'max:2000'],
        ]);

        $before = $review->only(['status', 'moderation_reason']);
        $review->update([
            'status' => $data['status'],
            'moderation_reason' => $data['status'] === 'hidden' ? ($data['moderation_reason'] ?? null) : null,
        ]);

        AdminAuditLog::record($request, 'review.moderate', $review, [
            'before' => $before,
            'after' => $review->fresh()->only(['status', 'moderation_reason']),
        ]);

        return response()->json($review->fresh()->load(['customer:id,full_name,email', 'book:id,title']));
    }
}
