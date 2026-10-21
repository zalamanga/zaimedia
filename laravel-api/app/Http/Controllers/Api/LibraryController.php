<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use App\Models\Review;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with(['items.book.category:id,name', 'items.book.writer:id,username,full_name'])
            ->where('customer_id', $request->user()->id)
            ->where('payment_status', 'paid')->latest()->get();
        $reviewed = Review::where('customer_id', $request->user()->id)->pluck('book_id')->all();
        $books = collect();
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (! $item->book || $books->has($item->book_id)) {
                    continue;
                }
                $books->put($item->book_id, $item->book->toArray() + [
                    'purchase_date' => optional($order->created_at)->toISOString(),
                    'order_number' => $order->order_number,
                    'has_reviewed' => in_array($item->book_id, $reviewed, true),
                    'download_url' => '/api/library/'.$item->book_id.'/download',
                ]);
            }
        }

        return response()->json($books->values());
    }

    public function show(Request $request, int $book)
    {
        $book = $this->authorizedBook($request, $book);

        return response()->json($book->load(['category:id,name', 'writer:id,username,full_name'])->toArray() + [
            'download_url' => '/api/library/'.$book->id.'/download',
        ]);
    }

    public function download(Request $request, int $book)
    {
        $book = $this->authorizedBook($request, $book);
        abort_unless(UploadStorage::safeName($book->pdf_file), 404, 'PDF tidak tersedia.');
        $disk = Storage::disk('private');
        $path = 'books/pdf/'.$book->pdf_file;
        abort_unless($disk->exists($path), 404, 'PDF tidak tersedia.');

        return $disk->response($path, 'book-'.$book->id.'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizedBook(Request $request, int $id): Book
    {
        $book = Book::withTrashed()->findOrFail($id);
        $user = $request->user();
        $owned = Order::where('customer_id', $user->id)->where('payment_status', 'paid')
            ->whereHas('items', fn ($q) => $q->where('book_id', $book->id))->exists();
        abort_unless($owned || $user->isAdmin() || (int) $book->writer_id === (int) $user->id, 403, 'Anda belum memiliki akses ke buku ini.');
        abort_unless($book->pdf_file, 404, 'PDF tidak tersedia untuk buku ini.');

        return $book;
    }
}
