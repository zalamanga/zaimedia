<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return Category::withCount(['books' => fn ($q) => $q->where('status', 'approved')])
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);

        $category = Category::create($data);

        AdminAuditLog::record($request, 'category.create', $category, [
            'after' => $category->only(['name', 'slug', 'description']),
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category);
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);
        $before = $category->only(['name', 'slug', 'description']);
        $category->update($data);

        AdminAuditLog::record($request, 'category.update', $category, [
            'before' => $before,
            'after' => $category->fresh()->only(['name', 'slug', 'description']),
        ]);

        return response()->json($category->fresh());
    }

    public function destroy(Request $request, Category $category)
    {
        if ($category->books()->withTrashed()->exists()) {
            return response()->json(['message' => 'Kategori masih dipakai oleh buku.'], 422);
        }

        AdminAuditLog::record($request, 'category.delete', $category, [
            'before' => $category->only(['name', 'slug', 'description']),
        ]);

        $category->delete();

        return response()->json(['message' => 'Kategori dihapus.']);
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $id = $category?->id;

        return $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('categories', 'name')->ignore($id)],
            'slug' => ['nullable', 'string', 'max:50', Rule::unique('categories', 'slug')->ignore($id)],
            'description' => ['nullable', 'string'],
        ]);
    }
}
