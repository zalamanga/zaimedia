<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Article;
use App\Support\Pagination;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        return Article::with('author:id,full_name')
            ->where('status', 'published')
            ->when($request->q, fn ($q, $term) => $q->where(fn ($s) => $s
                ->where('title', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%")
                ->orWhere('content', 'like', "%{$term}%")))
            ->latest('published_at')
            ->paginate(Pagination::perPage($request, 9));
    }

    public function show(string $slug)
    {
        $article = Article::with('author:id,full_name')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return response()->json($article);
    }

    public function adminIndex(Request $request)
    {
        return Article::with('author:id,full_name')
            ->when($request->q, fn ($q, $term) => $q->where(fn ($s) => $s
                ->where('title', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%")))
            ->when($request->status && $request->status !== 'all', fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(Pagination::perPage($request, 20));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['author_id'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['title']);
        $data['published_at'] = $data['status'] === 'published' ? ($data['published_at'] ?? now()) : null;

        $article = Article::create($data);

        AdminAuditLog::record($request, 'article.create', $article, [
            'after' => $article->only(['title', 'slug', 'status', 'published_at']),
        ]);

        return response()->json($article->load('author:id,full_name,email'), 201);
    }

    public function update(Request $request, Article $article)
    {
        $data = $this->validated($request, $article);
        $before = $article->only(['title', 'slug', 'status', 'published_at']);

        if (array_key_exists('slug', $data) || array_key_exists('title', $data)) {
            $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['title'] ?? $article->title, $article->id);
        }

        if (($data['status'] ?? $article->status) === 'published' && ! $article->published_at && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if (($data['status'] ?? $article->status) === 'draft') {
            $data['published_at'] = null;
        }

        $article->update($data);

        AdminAuditLog::record($request, 'article.update', $article, [
            'before' => $before,
            'after' => $article->fresh()->only(['title', 'slug', 'status', 'published_at']),
        ]);

        return response()->json($article->fresh()->load('author:id,full_name,email'));
    }

    public function destroy(Request $request, Article $article)
    {
        AdminAuditLog::record($request, 'article.delete', $article, [
            'before' => $article->only(['title', 'slug', 'status']),
        ]);

        $article->delete();

        return response()->json(['message' => 'Artikel dihapus.']);
    }

    public function uploadCover(Request $request)
    {
        $request->validate([
            'cover' => ['required', 'image', 'max:5120'],
        ]);

        $name = UploadStorage::store($request->file('cover'), $request->user(), 'article');

        return response()->json(['file' => $name, 'url' => asset("uploads/articles/{$name}")]);
    }

    private function validated(Request $request, ?Article $article = null): array
    {
        $id = $article?->id;

        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('articles', 'slug')->ignore($id)],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'content' => ['required', 'string', 'min:20'],
            'cover_image' => ['nullable', 'string', 'max:100', UploadStorage::reference($request->user(), 'article', $article?->cover_image)],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'artikel';
        $slug = $base;
        $counter = 2;

        while (Article::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
