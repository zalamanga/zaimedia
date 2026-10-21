# Zaimedia API

Laravel 13, PHP 8.3+, dan Sanctum 4. Panduan menjalankan aplikasi Vue + API dengan Docker ada di [README utama](../README.md).

Migrasi di folder ini membuat skema lengkap untuk **database baru yang kosong**. Kode ini tidak mengimpor dump, akun, buku, atau berkas dari instalasi lama dan bukan alat migrasi database lama. Simpan cadangan instalasi lama secara terpisah.

## Instalasi dan akun

`php artisan migrate --seed` membuat tabel dan pengaturan awal tanpa membuat akun. Buat admin dengan identitas yang dipilih sendiri:

```sh
php artisan zaimedia:create-admin --username=nama_admin --email=admin@example.test --name="Nama Admin"
```

Command meminta password secara tersembunyi. Untuk otomasi, berikan password lewat environment `ZAIMEDIA_ADMIN_PASSWORD` dan tambahkan `--no-interaction`; jangan menaruh password di argumen CLI atau commit. Role bawaan command adalah `super_admin`; `--role=admin` tersedia.

Seed demo opsional:

```sh
php artisan db:seed --class=DemoSeeder
```

Seed ini membutuhkan `APP_ENV=local` atau `testing`, `CHECKOUT_DEMO_MODE=true`, dan environment `ZAIMEDIA_DEMO_PASSWORD` minimal 8 karakter. Tidak ada password bawaan. Ia membuat lima akun sintetis `demo_customer`, `demo_other_customer`, `demo_writer`, `demo_admin`, dan `demo_super_admin` dengan email `<username>@example.test`, dua buku/PDF buatan demo, satu artikel, serta order `DEMO-PAID` dan `DEMO-PENDING`. Menjalankannya kembali mempertahankan akun yang telah ada, termasuk passwordnya.

## Pembayaran simulasi

Tidak ada integrasi payment gateway atau transfer bank. Order normal dimulai sebagai `pending`. Simulasi otomatis lunas hanya aktif jika seluruh kondisi berikut terpenuhi:

- Environment aplikasi `local` atau `testing`.
- `CHECKOUT_DEMO_MODE=true` dan `CHECKOUT_AUTO_PAID=true`.
- Pengaturan database `checkout_auto_paid` tidak dinonaktifkan.

Env `CHECKOUT_AUTO_PAID=false` selalu menutup simulasi meskipun pengaturan database bernilai true. Status admin hanya mencatat status lokal; tidak mengirim atau mengembalikan uang sungguhan.

Transisi paid menghasilkan satu catatan penjualan per buku/order. Pembaruan status yang sama tidak menggandakan royalti. Refund paid menghapus catatan penjualan dan mengurangi saldo royalti dalam transaksi yang sama; bila saldo telah dialokasikan untuk penarikan, API menolak dengan 422. Order refunded tidak dapat dilunasi ulang. Withdrawal completed/rejected merupakan status akhir dan tidak dapat dibuka kembali.

## Kontrak frontend

| Endpoint | Akses dan respons |
| --- | --- |
| `GET /api/storefront-config` | Publik; hanya `tax_rate` dan `checkout_demo_mode` efektif. |
| `GET /api/books` dan `/api/books/{id-or-slug}` | Publik; `has_pdf` boolean, tanpa `pdf_file` atau URL PDF. |
| `POST /api/uploads/cover` | Writer/admin; field multipart `cover`, menghasilkan `file` + URL publik. |
| `POST /api/uploads/pdf` | Writer/admin; field multipart `pdf`, menghasilkan basename `file` tanpa URL publik. |
| `GET /api/library/{id}` | Pembeli dengan order paid, pemilik writer, atau admin; menghasilkan `download_url` relatif. |
| `GET /api/library/{id}/download` | Izin yang sama; PDF inline dengan `Cache-Control: private, no-store`. |

Kirim Bearer token saat mengambil PDF, baca sebagai Blob, dan gunakan object URL di reader. Jangan membuat URL PDF dari nama berkas. PDF disimpan di disk `private` (`storage/app/private/books/pdf`), sedangkan cover/avatar/artikel di disk `uploads` (`public/uploads`). Server web tidak boleh memetakan storage privat sebagai file publik.

Referensi file baru pada buku/artikel harus berasal dari upload akun yang sama. Nilai nama file lama boleh dipertahankan pada record tersebut. Kolom `pdf_file` kosong saat update buku mempertahankan PDF yang ada. Penggantian hanya menghapus file terdaftar yang tidak dipakai buku lain. PDF/cover buku yang diarsipkan tetap disimpan agar histori dan akses pembeli tetap bekerja.

`per_page` dibatasi 1–100. Registrasi selalu menjadi customer, password minimal 8 karakter, dan perubahan password mencabut token API. Role admin dan status akun aktif tetap diperiksa pada endpoint privat.

## Tes

Dengan PHP 8.3+ dan dependency Composer terpasang:

```sh
composer test
```

PHPUnit memaksa `APP_ENV=testing` dan SQLite `:memory:`; guard menolak koneksi database lain. Tes menggunakan migrasi fresh, data sintetis, dan fake storage, sehingga tidak memakai `.env`/database/file upload instalasi lama. Tes meliputi auth/roles, upload dan traversal, izin PDF, checkout pending/demo, ledger/refund/withdrawal, dashboard, seed, dan command admin.
