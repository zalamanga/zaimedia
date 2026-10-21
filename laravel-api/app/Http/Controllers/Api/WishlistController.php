<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        return Wishlist::with(['book.category:id,name', 'book.writer:id,username,full_name'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->pluck('book')
            ->filter()
            ->values();
    }

    public function toggle(Request $request, Book $book)
    {
        abort_if($book->status !== 'approved', 404);
        abort_if((int) $book->writer_id === (int) $request->user()->id, 422, 'Tidak bisa wishlist buku sendiri.');

        $existing = Wishlist::where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['wishlisted' => false]);
        }

        Wishlist::create([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
        ]);

        return response()->json(['wishlisted' => true]);
    }
}
