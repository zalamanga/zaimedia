<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AppNotification;
use App\Models\Book;
use App\Models\User;
use App\Support\Pagination;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function index(Request $request)
    {
        $books = Book::query()
            ->with(['category:id,name', 'writer:id,username,full_name'])
            ->where('status', 'approved')
            ->when($request->q, fn ($q, $term) => $q->where(fn ($s) => $s
                ->where('title', 'like', "%{$term}%")
                ->orWhere('author', 'like', "%{$term}%")
                ->orWhere('short_description', 'like', "%{$term}%")))
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->sort === 'popular', fn ($q) => $q->orderByDesc('views'))
            ->when($request->sort === 'price_low', fn ($q) => $q->orderBy('price'))
            ->when($request->sort === 'price_high', fn ($q) => $q->orderByDesc('price'))
            ->when(! in_array($request->sort, ['popular', 'price_low', 'price_high'], true), fn ($q) => $q->latest())
            ->paginate(Pagination::perPage($request, 12));

        return response()->json($books);
    }

    public function show(string $book)
    {
        $book = Book::where('id', $book)->orWhere('slug', $book)->firstOrFail();

        if ($book->status !== 'approved') {
            return response()->json(['message' => 'Book not found'], 404);
        }

        $book->increment('views');

        $book->load(['category:id,name', 'writer:id,username,full_name,bio', 'reviews' => fn ($q) => $q->where('status', 'visible')->with('customer:id,full_name')]);
        $ratings = $book->reviews;
        $distribution = collect(range(5, 1))->mapWithKeys(fn ($star) => [
            $star => $ratings->where('rating', $star)->count(),
        ]);
        $related = Book::with(['category:id,name', 'writer:id,username,full_name'])
            ->where('status', 'approved')
            ->where('id', '!=', $book->id)
            ->when($book->category_id, fn ($q) => $q->where('category_id', $book->category_id))
            ->latest()
            ->limit(4)
            ->get();

        return response()->json($book->toArray() + [
            'rating_average' => round((float) $ratings->avg('rating'), 1),
            'rating_count' => $ratings->count(),
            'rating_distribution' => $distribution,
            'related_books' => $related,
        ]);
    }

    public function writerBooks(Request $request)
    {
        return Book::withTrashed()
            ->with('category:id,name')
            ->withCount('sales')
            ->withSum('sales', 'royalty_amount')
            ->where('writer_id', $request->user()->id)
            ->latest()
            ->get();
    }

    public function adminBooks(Request $request)
    {
        return Book::withTrashed()
            ->with(['category:id,name', 'writer:id,username,full_name'])
            ->when($request->q, fn ($q, $term) => $q->where(fn ($s) => $s
                ->where('title', 'like', "%{$term}%")
                ->orWhere('author', 'like', "%{$term}%")
                ->orWhere('publisher', 'like', "%{$term}%")))
            ->when($request->status && $request->status !== 'all' && $request->status !== 'archived', fn ($q) => $q->where('status', $request->status))
            ->when($request->status === 'archived', fn ($q) => $q->onlyTrashed())
            ->latest()
            ->paginate(Pagination::perPage($request, 20));
    }

    public function store(Request $request)
    {
        $data = $this->normalizeBookData($this->validatedBook($request));
        $data['writer_id'] = $request->user()->id;
        $data['status'] = $request->user()->isAdmin() ? ($request->status ?? 'approved') : 'pending';

        $book = Book::create($data);

        User::whereIn('role', ['admin', 'super_admin'])->pluck('id')->each(fn ($adminId) => AppNotification::send(
            $adminId,
            'book.submitted',
            'Buku baru menunggu review',
            "{$book->title} menunggu review admin.",
            '/dashboard?tab=books'
        ));

        return response()->json($book, 201);
    }

    public function update(Request $request, Book $book)
    {
        if (! $request->user()->isAdmin() && $book->writer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $this->normalizeBookData($this->validatedBook($request, true, $book), true, $book->id);
        if (! $request->user()->isAdmin()) {
            unset($data['status'], $data['rejection_reason']);
            $data['status'] = 'pending';
        }

        $oldCover = $book->cover_image;
        $oldPdf = $book->pdf_file;

        $book->update($data);

        $this->deleteReplacedBookFiles($oldCover, $book->cover_image, $oldPdf, $book->pdf_file);

        return response()->json($book->fresh());
    }

    public function review(Request $request, Book $book)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'pending'])],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string'],
        ]);

        if ($data['status'] !== 'rejected') {
            $data['rejection_reason'] = null;
        }

        $before = $book->only(['status', 'rejection_reason']);
        $book->update($data);

        AdminAuditLog::record($request, 'book.review', $book, [
            'before' => $before,
            'after' => $book->only(['status', 'rejection_reason']),
        ]);

        AppNotification::send($book->writer_id, 'book.reviewed', 'Status buku diperbarui', "{$book->title} sekarang berstatus {$book->status}.", '/dashboard?tab=books');

        return response()->json($book->fresh());
    }

    public function destroy(Request $request, Book $book)
    {
        if (! $request->user()->isAdmin() && $book->writer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        AdminAuditLog::record($request, 'book.archive', $book, [
            'title' => $book->title,
            'writer_id' => $book->writer_id,
            'had_transactions' => $book->sales()->exists() || $book->orderItems()->exists(),
        ]);

        $book->delete();

        return response()->json(['message' => 'Book archived']);
    }

    public function restore(Request $request, int $book)
    {
        $book = Book::withTrashed()->findOrFail($book);

        if (! $request->user()->isAdmin() && $book->writer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (! $book->trashed()) {
            return response()->json($book->fresh());
        }

        $book->restore();

        AdminAuditLog::record($request, 'book.restore', $book, [
            'title' => $book->title,
            'writer_id' => $book->writer_id,
        ]);

        return response()->json($book->fresh());
    }

    public function upload(Request $request, string $type)
    {
        abort_unless(in_array($type, ['cover', 'pdf'], true), 404);

        $field = $type === 'cover' ? 'cover' : 'pdf';
        $rules = $type === 'cover'
            ? ['required', 'image', 'max:5120']
            : ['required', 'mimes:pdf', 'max:51200'];
        $request->validate([$field => $rules]);

        $name = UploadStorage::store($request->file($field), $request->user(), $type);

        return response()->json(['file' => $name] + ($type === 'cover'
            ? ['url' => asset("uploads/books/covers/{$name}")]
            : []));
    }

    private function validatedBook(Request $request, bool $partial = false, ?Book $book = null): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:200'],
            'author' => [$required, 'string', 'max:100'],
            'publisher' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'pages' => ['nullable', 'integer', 'min:0'],
            'price' => [$required, 'numeric', 'min:0', 'max:9999999999.99'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'cover_image' => ['nullable', 'string', 'max:100', UploadStorage::reference($request->user(), 'cover', $book?->cover_image)],
            'pdf_file' => ['nullable', 'string', 'max:100', UploadStorage::reference($request->user(), 'pdf', $book?->pdf_file)],
            'short_description' => ['nullable', 'string'],
            'long_description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'rejection_reason' => ['nullable', 'string'],
        ]);
    }

    private function normalizeBookData(array $data, bool $partial = false, ?int $ignoreId = null): array
    {
        if ($partial) {
            foreach (['cover_image', 'pdf_file'] as $field) {
                if (array_key_exists($field, $data) && ! $data[$field]) {
                    unset($data[$field]);
                }
            }
        }

        if (! $partial || array_key_exists('publisher', $data)) {
            $data['publisher'] = $data['publisher'] ?? '';
        }

        if (! $partial || array_key_exists('year', $data)) {
            $data['year'] = $data['year'] ?? (int) date('Y');
        }

        if (! $partial || array_key_exists('pages', $data)) {
            $data['pages'] = $data['pages'] ?? 0;
        }

        if (! $partial || array_key_exists('title', $data)) {
            $data['slug'] = $this->uniqueSlug($data['title'] ?? 'book', $ignoreId);
        }

        return $data;
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'book';
        $slug = $base;
        $counter = 2;

        while (Book::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function deleteReplacedBookFiles(?string $oldCover, ?string $newCover, ?string $oldPdf, ?string $newPdf): void
    {
        if ($oldCover !== $newCover) {
            UploadStorage::deleteUnusedBookFile($oldCover, 'cover');
        }
        if ($oldPdf !== $newPdf) {
            UploadStorage::deleteUnusedBookFile($oldPdf, 'pdf');
        }
    }
}
