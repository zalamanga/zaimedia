<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\UploadedFile;
use App\Models\User;
use App\Support\OrderLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('zaimedia.checkout_demo_mode')) {
            throw new RuntimeException('DemoSeeder hanya untuk local/testing dengan CHECKOUT_DEMO_MODE=true.');
        }
        $password = (string) env('ZAIMEDIA_DEMO_PASSWORD', '');
        if (strlen($password) < 8) {
            throw new RuntimeException('Tetapkan ZAIMEDIA_DEMO_PASSWORD minimal 8 karakter; tidak ada password bawaan.');
        }
        $this->call(DatabaseSeeder::class);
        DB::transaction(function () use ($password) {
            $users = collect(['customer', 'other_customer', 'writer', 'admin', 'super_admin'])->mapWithKeys(function ($role) use ($password) {
                $username = 'demo_'.$role;
                $user = User::firstOrCreate(['username' => $username], [
                    'email' => $username.'@example.test', 'full_name' => 'Demo '.Str::headline($role),
                    'password' => Hash::make($password), 'status' => 'active',
                    'role' => $role === 'other_customer' ? 'customer' : $role,
                ]);

                return [$role => $user];
            });
            $category = Category::firstOrCreate(['slug' => 'demo-pemrograman'], ['name' => 'Demo Pemrograman', 'description' => 'Data sintetis untuk mencoba aplikasi lokal.']);
            $books = collect(['belajar-laravel-demo' => 'Belajar Laravel (Demo)', 'panduan-vue-demo' => 'Panduan Vue (Demo)'])->map(function ($title, $slug) use ($users, $category) {
                $name = $slug.'.pdf';
                Storage::disk('private')->put('books/pdf/'.$name, $this->samplePdf());
                UploadedFile::firstOrCreate(['name' => $name], ['user_id' => $users['writer']->id, 'kind' => 'pdf']);

                return Book::firstOrCreate(['slug' => $slug], [
                    'writer_id' => $users['writer']->id, 'category_id' => $category->id,
                    'title' => $title, 'author' => 'Penulis Demo', 'publisher' => 'Demo Lokal',
                    'year' => 2026, 'pages' => 1, 'price' => 50000, 'pdf_file' => $name,
                    'short_description' => 'Contoh buku sintetis untuk pengujian lokal.',
                    'long_description' => 'Dokumen ini dibuat khusus sebagai contoh aplikasi. Tidak berisi buku atau data pribadi dari instalasi lama.',
                    'status' => 'approved',
                ]);
            })->values();
            foreach (['paid', 'pending'] as $index => $status) {
                $order = Order::firstOrCreate(['order_number' => 'DEMO-'.strtoupper($status)], [
                    'customer_id' => $users['customer']->id, 'subtotal' => 50000, 'tax' => 5500, 'total' => 55500,
                    'payment_method' => 'bank_transfer', 'payment_status' => $status,
                    'order_status' => $status === 'paid' ? 'processing' : 'pending',
                    'shipping_name' => 'Pembaca Demo', 'shipping_phone' => '0000000000',
                    'shipping_address' => 'Alamat sintetis', 'shipping_city' => 'Kota Demo', 'shipping_postal_code' => '00000',
                ]);
                $order->items()->firstOrCreate(['book_id' => $books[$index]->id], ['quantity' => 1, 'price' => 50000, 'subtotal' => 50000]);
                if ($status === 'paid') {
                    OrderLedger::pay($order);
                }
            }
            Article::firstOrCreate(['slug' => 'selamat-datang-demo'], [
                'author_id' => $users['admin']->id, 'title' => 'Mencoba Zaimedia secara lokal',
                'excerpt' => 'Artikel contoh yang dibuat khusus untuk demo.',
                'content' => 'Semua akun, buku, dan transaksi di instalasi demo ini adalah data sintetis. Gunakan katalog, perpustakaan, dan dashboard untuk mempelajari alur aplikasi.',
                'status' => 'published', 'published_at' => now(),
            ]);
        });
    }

    private function samplePdf(): string
    {
        $stream = 'BT /F1 18 Tf 50 760 Td (Zaimedia - synthetic demo document) Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
