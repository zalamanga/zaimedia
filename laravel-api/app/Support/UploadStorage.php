<?php

namespace App\Support;

use App\Models\Book;
use App\Models\UploadedFile;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadStorage
{
    public static function disk(string $kind): string
    {
        return $kind === 'pdf' ? 'private' : 'uploads';
    }

    public static function folder(string $kind): string
    {
        return match ($kind) {
            'pdf' => 'books/pdf',
            'cover' => 'books/covers',
            'article' => 'articles',
            'avatar' => 'avatars',
        };
    }

    public static function safeName(?string $name): bool
    {
        return is_string($name) && preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.-]{0,99}\z/D', $name) === 1;
    }

    public static function store(HttpUploadedFile $file, User $owner, string $kind): string
    {
        $extension = $kind === 'pdf' ? 'pdf' : $file->guessExtension();
        abort_unless(in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'], true), 422, 'Jenis file tidak didukung.');
        $name = Str::uuid().'.'.$extension;
        $file->storeAs(self::folder($kind), $name, self::disk($kind));
        UploadedFile::create(['user_id' => $owner->id, 'kind' => $kind, 'name' => $name]);

        return $name;
    }

    public static function reference(User $owner, string $kind, ?string $existing = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($owner, $kind, $existing): void {
            if (! self::safeName($value)) {
                $fail('Nama file tidak valid.');

                return;
            }
            if ($existing === $value) {
                return;
            }
            $owned = UploadedFile::where('user_id', $owner->id)->where('kind', $kind)->where('name', $value)->exists();
            if (! $owned || ! Storage::disk(self::disk($kind))->exists(self::folder($kind).'/'.$value)) {
                $fail('Gunakan file yang diunggah oleh akun ini.');
            }
        };
    }

    public static function deleteUnusedBookFile(?string $name, string $kind): void
    {
        if (! self::safeName($name)) {
            return;
        }
        $column = $kind === 'pdf' ? 'pdf_file' : 'cover_image';
        if (Book::withTrashed()->where($column, $name)->exists()) {
            return;
        }
        $upload = UploadedFile::where('kind', $kind)->where('name', $name)->first();
        if ($upload) {
            Storage::disk(self::disk($kind))->delete(self::folder($kind).'/'.$name);
            $upload->delete();
        }
    }
}
