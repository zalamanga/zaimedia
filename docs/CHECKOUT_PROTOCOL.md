# Mengulang checkout dengan aman

`POST /api/orders` menerima body JSON dengan `operation_key` UUID dan data checkout. Frontend membuat kunci sebelum mengirim, menyimpannya untuk akun tersebut, lalu memakai kunci yang sama sampai hasilnya jelas. Respons jaringan gagal tidak membuktikan order gagal tersimpan.

## Receipt di server

Dalam transaksi, [OrderController](../laravel-api/app/Http/Controllers/Api/OrderController.php) mengunci row user sebelum mencari [CheckoutOperation](../laravel-api/app/Models/CheckoutOperation.php). Database mempunyai unique constraint `(customer_id, operation_key)` di [migration](../laravel-api/database/migrations/2026_10_26_000001_create_checkout_operations_table.php).

| Permintaan | Hasil |
| --- | --- |
| Kunci baru, data valid | Order, item, notification, ledger demo jika aktif, dan receipt disimpan dalam satu transaksi. Respons `201`, `Checkout-Replayed: false`. |
| Akun/kunci/data sama | Respons `201` berisi snapshot JSON pertama, `Checkout-Replayed: true`. Tidak membuat order atau ledger lain. |
| Akun/kunci sama, data berbeda | `409`; periksa order sebelum mencoba intent lain. |
| `GET /api/checkout-operations/{key}` milik akun sendiri | `200` dengan `operation_key`, `intent_hash`, `order`. |
| Receipt belum ditemukan, termasuk kunci milik akun lain | `404` dengan `outcome_unknown: true`. Ini tidak mengizinkan penggantian kunci; POST sebelumnya mungkin masih berjalan. |

Receipt menyimpan snapshot saat order dibuat. Harga, judul buku, atau status pembayaran yang kemudian berubah tidak menulis ulang snapshot itu. Halaman hasil dan dashboard mengambil status order terkini. Receipt tidak kedaluwarsa atau dihapus otomatis oleh pembaruan ini. API tetap memeriksa login, akun aktif, role dan kepemilikan; tidak ada gateway pembayaran baru.

## Identitas data

[CheckoutIntent.php](../laravel-api/app/Support/CheckoutIntent.php) dan [checkout-intent.js](../vue-frontend/src/checkout-intent.js) menghasilkan JSON dalam urutan: `items`, lima field `shipping_*` wajib, `shipping_method`, `payment_method`, `notes`. Tidak ada harga kiriman browser dalam identitas tersebut.

- Maksimal 100 baris dalam array JSON, bukan object berkey angka. `book_id` berupa nilai number bulat antara 1 dan 9007199254740991. Quantity null/tidak ada menjadi 1; selain itu harus number bulat antara 1 dan 10. Penulisan `2.0`/`2e0` menjadi integer `2`; string angka, pecahan dan nilai nonfinite ditolak. ID ganda dijumlahkan dengan batas 10, lalu diurutkan menurut ID numerik.
- Nama/telepon/alamat/kota/kode pos wajib terisi, dengan batas 100/20/2000/50/10 code point. Notes maksimal 2000. Unicode tidak dinormalisasi ke NFC/NFD; karakter internal dipertahankan dan surrogate/UTF8 rusak ditolak.
- Hanya karakter di tepi berikut dibuang: U+0000..0020,0085,00A0,1680,2000..200B,2028,2029,202F,205F,3000,FEFF. Metode kosong/null menjadi `regular` dan `bank_transfer`; notes kosong/null menjadi null. Literal teks `0` tetap teks.
- SHA256 dihitung dari JSON UTF8 dengan Unicode, slash, U+2028 dan U+2029 literal. [Vector sintetis bersama](../tests/checkout-intent-vectors.json) berisi bentuk dan hash acuan kedua bahasa. Controller memakai JSON asli agar middleware konversi string kosong tidak mengubah quantity yang tidak valid menjadi1.

## Pemulihan di tab browser

[checkout-state.js](../vue-frontend/src/checkout-state.js) mempertahankan payload beku hanya di memori. sessionStorage hanya memuat versi, ID akun, kunci operasi, hash intent dan hash cart. Nama, telepon, alamat, notes, token dan respons order tidak dimasukkan ke catatan operasi ini. Sesi login aplikasi tetap memakai mekanisme localStorage yang dijelaskan README.

Checkout baru ditolak sebelum POST jika sessionStorage tidak dapat ditulis dan dibaca balik. Jika storage kemudian gagal setelah pengiriman, payload/kunci tetap dilindungi di memori dan UI memperingatkan keterbatasan pemulihan setelah reload. Menutup tab, menghapus storage atau memakai tab lain tidak dijamin memulihkan operasi; periksa daftar order sebelum mengulang pembelian.

Saat submit, cart/form dikunci dan perpindahan rute dicegah. Saat hasil belum jelas, gunakan **Periksa hasil** atau **Coba operasi yang sama**. Reload tidak menyimpan kembali data pribadi: jika receipt belum ditemukan, isi ulang intent yang persis sama dan centang konfirmasi; hash wajib cocok sebelum kunci lama dikirim ulang. Metadata rusak tidak diam-diam diganti. Respons `422` pada pengiriman pertama mengizinkan koreksi; `422` pada retry operasi yang belum jelas tetap membutuhkan konfirmasi.

Pergantian sesi menghapus payload memori dan mengabaikan hasil sesi lama. Respons `401`/logout yang terlambat tidak menghapus sesi baru. Setelah konfirmasi, cart hanya dibersihkan jika akun dan hash cart masih cocok. Cart valid berbeda yang ditemukan di localStorage disalin kembali ke memori dan dipertahankan. Pemeriksaan identitas terakhir, penulisan cart kosong dan pembacaan balik dilakukan tanpa await di antaranya; ini bukan penguncian semua penulis lintas tab. Penulisan kosong harus terverifikasi sebelum memori dikosongkan dan kunci operasi dilepas. Storage hilang, rusak atau tidak terbaca tidak dianggap cart aman untuk ditimpa: order tetap terkonfirmasi dan kuncinya dipertahankan untuk peninjauan. Jika storage sementara gagal, **Selesaikan konfirmasi lokal** hanya mencoba pembersihan lokal setelah storage pulih, tanpa POST order lain. Tidak tersedia tombol yang menganggap hasil tidak diketahui sebagai pembatalan server.
