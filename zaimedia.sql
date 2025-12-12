-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 12 Des 2025 pada 08.09
-- Versi server: 10.4.27-MariaDB
-- Versi PHP: 8.1.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `zaimedia`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `writer_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `author` varchar(100) NOT NULL,
  `publisher` varchar(100) NOT NULL,
  `year` int(11) NOT NULL,
  `pages` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `pdf_file` varchar(255) DEFAULT NULL,
  `short_description` text DEFAULT NULL,
  `long_description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `books`
--

INSERT INTO `books` (`id`, `writer_id`, `title`, `author`, `publisher`, `year`, `pages`, `price`, `isbn`, `category_id`, `cover_image`, `pdf_file`, `short_description`, `long_description`, `status`, `rejection_reason`, `views`, `created_at`, `updated_at`) VALUES
(1, 2, 'technolgy education', 'James Clear', 'Penguin Books', 2018, 320, '54000.00', '978-0735211292', 6, 'cover_1765270713_6937e4b9578fc.png', NULL, 'Di era digital yang terus berkembang, sebuah sekolah inovatif bernama NovaTech Academy menjadi pelopor dalam menggabungkan teknologi mutakhir dengan metode belajar modern. Di sana, para siswa tidak hanya belajar lewat buku, tetapi juga melalui kecerdasan buatan, realitas virtual, robotika, dan simulasi digital yang membawa mereka pada pengalaman belajar tanpa batas.\r\n\r\nSeorang siswa bernama Mika, yang awalnya kesulitan mengikuti pelajaran tradisional, menemukan dunia baru dalam sistem pembelajaran berbasis teknologi ini. Dengan bantuan platform pintar yang bisa menyesuaikan materi sesuai kemampuan tiap individu, Mika perlahan menemukan potensinya yang selama ini tersembunyi.\r\n\r\nNamun perkembangan teknologi juga memunculkan tantangan baru—dari kesenjangan akses, etika penggunaan AI, hingga dampak psikologis pada siswa. Melalui perjalanan Mika dan teman-temannya, Technology Education menggambarkan bagaimana pendidikan masa depan tidak hanya soal kecanggihan, tetapi juga tentang bagaimana teknologi dapat benar-benar memanusiakan proses belajar.', 'Di era digital yang terus berkembang, sebuah sekolah inovatif bernama NovaTech Academy menjadi pelopor dalam menggabungkan teknologi mutakhir dengan metode belajar modern. Di sana, para siswa tidak hanya belajar lewat buku, tetapi juga melalui kecerdasan buatan, realitas virtual, robotika, dan simulasi digital yang membawa mereka pada pengalaman belajar tanpa batas.\r\n\r\nSeorang siswa bernama Mika, yang awalnya kesulitan mengikuti pelajaran tradisional, menemukan dunia baru dalam sistem pembelajaran berbasis teknologi ini. Dengan bantuan platform pintar yang bisa menyesuaikan materi sesuai kemampuan tiap individu, Mika perlahan menemukan potensinya yang selama ini tersembunyi.\r\n\r\nNamun perkembangan teknologi juga memunculkan tantangan baru—dari kesenjangan akses, etika penggunaan AI, hingga dampak psikologis pada siswa. Melalui perjalanan Mika dan teman-temannya, Technology Education menggambarkan bagaimana pendidikan masa depan tidak hanya soal kecanggihan, tetapi juga tentang bagaimana teknologi dapat benar-benar memanusiakan proses belajar.', 'approved', NULL, 4, '2025-11-17 16:05:58', '2025-12-09 09:09:52'),
(2, 2, 'a journey through time', 'Cal Newport', 'Grand Central Publishing', 2016, 296, '105000.00', '978-1455586691', 7, 'cover_1765270801_6937e511c610b.png', NULL, 'Dalam sebuah laboratorium penelitian modern, seorang pelajar muda bernama Leo secara tak sengaja mengaktifkan prototipe mesin waktu yang dibuat untuk proyek sains sekolahnya. Tanpa peringatan, Leo terlempar ke berbagai era—dari masa prasejarah, kejayaan kerajaan kuno, revolusi industri, hingga masa depan yang dipenuhi teknologi canggih.\r\n\r\nDi setiap perjalanan, ia belajar tentang sejarah, ilmu pengetahuan, budaya, dan pilihan-pilihan kecil yang mampu mengubah arah dunia. Namun setiap lompatan waktu juga membawa bahaya: perubahan sejarah yang tak disengaja, paradoks waktu, dan sosok misterius yang tampaknya mengikuti Leo dari satu era ke era lainnya.\r\n\r\nUntuk kembali ke masanya, Leo harus memahami makna perjalanan tersebut—bahwa waktu bukan hanya garis lurus, tetapi rangkaian pelajaran yang membentuk dirinya. A Journey Through Time adalah kisah petualangan edukatif tentang keberanian, rasa ingin tahu, dan betapa berharganya setiap momen dalam sejarah manusia.', 'Dalam sebuah laboratorium penelitian modern, seorang pelajar muda bernama Leo secara tak sengaja mengaktifkan prototipe mesin waktu yang dibuat untuk proyek sains sekolahnya. Tanpa peringatan, Leo terlempar ke berbagai era—dari masa prasejarah, kejayaan kerajaan kuno, revolusi industri, hingga masa depan yang dipenuhi teknologi canggih.\r\n\r\nDi setiap perjalanan, ia belajar tentang sejarah, ilmu pengetahuan, budaya, dan pilihan-pilihan kecil yang mampu mengubah arah dunia. Namun setiap lompatan waktu juga membawa bahaya: perubahan sejarah yang tak disengaja, paradoks waktu, dan sosok misterius yang tampaknya mengikuti Leo dari satu era ke era lainnya.\r\n\r\nUntuk kembali ke masanya, Leo harus memahami makna perjalanan tersebut—bahwa waktu bukan hanya garis lurus, tetapi rangkaian pelajaran yang membentuk dirinya. A Journey Through Time adalah kisah petualangan edukatif tentang keberanian, rasa ingin tahu, dan betapa berharganya setiap momen dalam sejarah manusia.', 'approved', NULL, 1, '2025-11-17 16:05:58', '2025-12-09 09:00:50'),
(3, 2, 'The Power of Habit', 'Charles Duhigg', 'Random House', 2012, 371, '95000.00', '978-0812981605', 8, NULL, NULL, 'Mengapa kita melakukan apa yang kita lakukan', 'Buku ini menjelaskan ilmu di balik kebiasaan.', 'rejected', 'kurang bagus', 0, '2025-11-17 16:05:58', '2025-11-18 19:22:05'),
(5, 2, 'jejak yang tak pernah pulang', 'asd', 'hutoyu', 2021, 90, '100000.00', '112-111-4430', 2, 'cover_1765270568_6937e4283bca0.png', 'book_1763489188_691cb5a4d315e.pdf', 'Di sebuah kota kecil yang sunyi, seorang pemuda bernama Raka menghilang tanpa jejak setelah mengejar mimpi yang selama ini membara dalam dirinya. Orang-orang hanya menemukan langkah-langkah terakhirnya yang mengarah ke luar kota—jejak yang seakan menandai awal perjalanan tanpa akhir.\r\n\r\nTahun demi tahun berlalu, dan keluarganya terus menunggu, berharap suatu hari ia kembali membawa cerita dari dunia yang ia jelajahi. Namun ketika sahabat masa kecilnya, Hana, menemukan petunjuk misterius tentang keberadaan Raka, ia memutuskan untuk mengikuti jejak itu.\r\n\r\nPerjalanan Hana membuka rahasia yang tak pernah terbayangkan: kisah tentang pencarian diri, luka yang disembunyikan, serta pilihan-pilihan yang membuat seseorang tak sanggup kembali. Jejak yang Tak Pernah Pulang adalah kisah emosional tentang kehilangan, penantian, dan keberanian menghadapi kenyataan yang mungkin tidak pernah ingin kita temui.', 'Di sebuah kota kecil yang sunyi, seorang pemuda bernama Raka menghilang tanpa jejak setelah mengejar mimpi yang selama ini membara dalam dirinya. Orang-orang hanya menemukan langkah-langkah terakhirnya yang mengarah ke luar kota—jejak yang seakan menandai awal perjalanan tanpa akhir.\r\n\r\nTahun demi tahun berlalu, dan keluarganya terus menunggu, berharap suatu hari ia kembali membawa cerita dari dunia yang ia jelajahi. Namun ketika sahabat masa kecilnya, Hana, menemukan petunjuk misterius tentang keberadaan Raka, ia memutuskan untuk mengikuti jejak itu.\r\n\r\nPerjalanan Hana membuka rahasia yang tak pernah terbayangkan: kisah tentang pencarian diri, luka yang disembunyikan, serta pilihan-pilihan yang membuat seseorang tak sanggup kembali. Jejak yang Tak Pernah Pulang adalah kisah emosional tentang kehilangan, penantian, dan keberanian menghadapi kenyataan yang mungkin tidak pernah ingin kita temui.', 'approved', NULL, 40, '2025-11-18 18:06:28', '2025-12-09 09:10:16'),
(6, 2, 'the path to succes', 'rafik', 'hutotu', 2025, 190, '78000.00', '111-222-1119', 9, 'cover_1765270417_6937e3912180c.png', 'book_1765270417_6937e39123a13.pdf', 'In a world full of obstacles, an individual sets out on a long and winding journey toward their greatest ambition. The climb is steep, the road is uncertain, and doubt often overshadows determination. Yet every step pushes them closer to the peak, where a clearer, stronger version of themselves awaits. The Path to Success is a story about perseverance, self-discovery, and the transformative power of the journey itself showing that true success is not only about reaching the top, but about who we become along the way.', 'In a world full of obstacles, an individual sets out on a long and winding journey toward their greatest ambition. The climb is steep, the road is uncertain, and doubt often overshadows determination. Yet every step pushes them closer to the peak, where a clearer, stronger version of themselves awaits. The Path to Success is a story about perseverance, self-discovery, and the transformative power of the journey itself showing that true success is not only about reaching the top, but about who we become along the way.', 'approved', NULL, 0, '2025-12-09 08:53:37', '2025-12-09 09:00:34');

-- --------------------------------------------------------

--
-- Struktur dari tabel `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `cart`
--

INSERT INTO `cart` (`id`, `customer_id`, `book_id`, `quantity`, `created_at`, `updated_at`) VALUES
(4, 1, 5, 1, '2025-12-04 06:54:56', '2025-12-04 06:54:56');

-- --------------------------------------------------------

--
-- Struktur dari tabel `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `created_at`) VALUES
(1, 'Self Improvement', 'self-improvement', 'Buku pengembangan diri', '2025-11-17 16:05:58'),
(2, 'Fiction', 'fiction', 'Buku fiksi dan novel', '2025-11-17 16:05:58'),
(3, 'Business', 'business', 'Buku bisnis dan entrepreneurship', '2025-11-17 16:05:58'),
(4, 'Romance', 'romance', 'Buku roman dan cinta', '2025-11-17 16:05:58'),
(5, 'Science', 'science', 'Buku sains dan teknologi', '2025-11-17 16:05:58'),
(6, 'Technology', 'technology', 'Buku teknologi dan pemrograman', '2025-11-17 16:05:58'),
(7, 'Productivity', 'productivity', 'Buku produktivitas', '2025-11-17 16:05:58'),
(8, 'Psychology', 'psychology', 'Buku psikologi', '2025-11-17 16:05:58'),
(9, 'Leadership', 'leadership', 'Buku kepemimpinan', '2025-11-17 16:05:58'),
(10, 'Finance', 'finance', 'Buku keuangan', '2025-11-17 16:05:58'),
(11, 'tes', '', 'asdasd', '2025-11-20 03:57:33');

-- --------------------------------------------------------

--
-- Struktur dari tabel `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `shipping_cost` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `payment_method` enum('bank_transfer','credit_card','e_wallet','cod') NOT NULL,
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `order_status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `shipping_name` varchar(100) NOT NULL,
  `shipping_phone` varchar(20) NOT NULL,
  `shipping_address` text NOT NULL,
  `shipping_city` varchar(50) NOT NULL,
  `shipping_postal_code` varchar(10) NOT NULL,
  `shipping_method` enum('regular','express','same_day') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `order_number`, `subtotal`, `shipping_cost`, `tax`, `total`, `payment_method`, `payment_status`, `order_status`, `shipping_name`, `shipping_phone`, `shipping_address`, `shipping_city`, `shipping_postal_code`, `shipping_method`, `notes`, `created_at`, `updated_at`) VALUES
(1, 3, 'ORD-20251120-4253B2', '3666666.00', '0.00', '403333.26', '4069999.26', 'bank_transfer', 'paid', 'delivered', 'oasdajds', '+6281234567891', 'asdasdasd', 'ajsdaljsd', '123123', 'regular', 'asdasdasd', '2025-11-20 06:47:47', '2025-11-20 06:51:31'),
(2, 1, 'ORD-20251204-6C7F5F', '1222222.00', '0.00', '134444.42', '1356666.42', 'bank_transfer', 'paid', 'processing', 'Administrator', 'q3123123', 'asdasd', 'asdsa', '123123', 'regular', 'sfsadads', '2025-12-04 06:43:54', '2025-12-04 06:43:54'),
(3, 3, 'ORD-20251204-23E2CC', '1222222.00', '0.00', '134444.42', '1356666.42', 'bank_transfer', 'paid', 'processing', 'Customer Demo', '+6281234567891', 'asdsad', 'asd', '1231', 'regular', 'asdasd', '2025-12-04 06:47:11', '2025-12-04 06:47:11'),
(4, 3, 'ORD-20251208-992F78', '1222222.00', '0.00', '134444.42', '1356666.42', 'bank_transfer', 'paid', 'processing', 'Customer Demoasdasd', '+6281234567891', 'asd', 'as', '12313', 'regular', '', '2025-12-08 15:01:49', '2025-12-08 15:01:49');

-- --------------------------------------------------------

--
-- Struktur dari tabel `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `book_id`, `quantity`, `price`, `subtotal`) VALUES
(1, 1, 5, 3, '1222222.00', '3666666.00'),
(2, 2, 5, 1, '1222222.00', '1222222.00'),
(3, 3, 5, 1, '1222222.00', '1222222.00'),
(4, 4, 5, 1, '1222222.00', '1222222.00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `title` varchar(100) DEFAULT NULL,
  `content` text NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0 COMMENT '1 if customer purchased the book',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `reviews`
--

INSERT INTO `reviews` (`id`, `book_id`, `customer_id`, `rating`, `title`, `content`, `is_verified`, `created_at`, `updated_at`) VALUES
(1, 5, 3, 5, 'sdasdd', 'asdasdassd', 1, '2025-12-06 20:02:51', '2025-12-06 20:02:51');

-- --------------------------------------------------------

--
-- Struktur dari tabel `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `writer_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `book_price` decimal(10,2) NOT NULL,
  `royalty_percentage` decimal(5,2) DEFAULT 10.00,
  `royalty_amount` decimal(10,2) NOT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `sales`
--

INSERT INTO `sales` (`id`, `book_id`, `writer_id`, `order_id`, `quantity`, `book_price`, `royalty_percentage`, `royalty_amount`, `sale_date`) VALUES
(1, 5, 2, 1, 3, '1222222.00', '70.00', '2566666.20', '2025-11-20 06:47:47'),
(2, 5, 2, 2, 1, '1222222.00', '70.00', '855555.40', '2025-12-04 06:43:54'),
(3, 5, 2, 3, 1, '1222222.00', '70.00', '855555.40', '2025-12-04 06:47:11'),
(4, 5, 2, 4, 1, '1222222.00', '70.00', '855555.40', '2025-12-08 15:01:49');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','writer','customer') DEFAULT 'customer',
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `facebook` varchar(100) DEFAULT NULL,
  `twitter` varchar(100) DEFAULT NULL,
  `instagram` varchar(100) DEFAULT NULL,
  `linkedin` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `role`, `avatar`, `bio`, `address`, `city`, `postal_code`, `date_of_birth`, `website`, `facebook`, `twitter`, `instagram`, `linkedin`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@zaimedia.com', '$2a$12$WEj9X3UT.FZodHohvcw96.kkZQAo.5sye/5Uo0BKPNCpdrcYkybEW', 'Administrator', NULL, 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-11-17 16:05:58', '2025-11-17 16:19:02'),
(2, 'johndoe', 'rafikmas78@gmail.com', '$2y$10$t.jKNXMvYG6RtyzIXmPjNuo.FS3IDYnqo5dqBeTFsV4dJm.BhQvE.', 'rafik', '+6281234567890', 'writer', NULL, 'Saya adalah seorang penulis yang passionate dalam bidang self-improvement dan produktivitas.', 'perum griya kenari mas block d9-no 19', 'Kab. Bogor', '16820', NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-11-17 16:05:58', '2025-11-18 19:25:27'),
(3, 'customer1', 'customer@example.com', '$2a$12$WEj9X3UT.FZodHohvcw96.kkZQAo.5sye/5Uo0BKPNCpdrcYkybEW', 'Customer Demo', '+6281234567891', 'customer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-11-17 16:05:58', '2025-11-17 16:19:11'),
(4, 'rafik', 'domainjalansukses@gmail.com', '$2y$10$IXXM9ChlxwYaSi2k0U/Ckeu6FaCR2uXvRGQ0Gn9Q5dw/ZAKOCzf9O', 'Andrina Haura Azzahra', '085711047939', 'writer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-12-06 18:32:54', '2025-12-06 18:32:54');

-- --------------------------------------------------------

--
-- Struktur dari tabel `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL,
  `withdrawal_code` varchar(50) DEFAULT NULL,
  `writer_id` int(11) NOT NULL,
  `withdrawal_number` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `bank_name` varchar(50) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','processing','completed','rejected') DEFAULT 'pending',
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `withdrawals`
--

INSERT INTO `withdrawals` (`id`, `withdrawal_code`, `writer_id`, `withdrawal_number`, `amount`, `bank_name`, `account_number`, `account_name`, `notes`, `status`, `processed_at`, `processed_by`, `rejection_reason`, `approved_by`, `approved_at`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 'WD-20251119-000001', 2, 'WD-20251119-511EE1', '1000000.00', 'BCA', '12121', 'rafik', '', 'rejected', '2025-11-20 12:57:17', 1, 'no', NULL, NULL, NULL, '2025-11-18 17:51:04', '2025-11-20 05:57:17'),
(2, 'WD-20251119-000002', 2, 'WD-20251119-9C32F2', '200000.00', 'Danamon', '02102012', 'rafik', 'asd', 'approved', '2025-11-20 12:57:35', 1, NULL, NULL, NULL, NULL, '2025-11-18 17:51:27', '2025-11-20 05:57:35');

-- --------------------------------------------------------

--
-- Struktur dari tabel `writer_balance`
--

CREATE TABLE `writer_balance` (
  `id` int(11) NOT NULL,
  `writer_id` int(11) NOT NULL,
  `available_balance` decimal(10,2) DEFAULT 0.00,
  `pending_balance` decimal(10,2) DEFAULT 0.00,
  `total_withdrawn` decimal(10,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `writer_balance`
--

INSERT INTO `writer_balance` (`id`, `writer_id`, `available_balance`, `pending_balance`, `total_withdrawn`, `updated_at`) VALUES
(1, 2, '7233332.40', '1700000.00', '11700000.00', '2025-12-08 15:01:49'),
(5, 4, '0.00', '0.00', '0.00', '2025-12-06 18:40:41');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_writer` (`writer_id`);

--
-- Indeks untuk tabel `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cart_item` (`customer_id`,`book_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indeks untuk tabel `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indeks untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_customer` (`customer_id`);

--
-- Indeks untuk tabel `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indeks untuk tabel `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_book_id` (`book_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_book_rating` (`book_id`,`rating`),
  ADD KEY `idx_verified` (`is_verified`);

--
-- Indeks untuk tabel `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_id` (`book_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `idx_writer` (`writer_id`),
  ADD KEY `idx_sale_date` (`sale_date`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`);

--
-- Indeks untuk tabel `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `withdrawal_number` (`withdrawal_number`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_writer` (`writer_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indeks untuk tabel `writer_balance`
--
ALTER TABLE `writer_balance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `writer_id` (`writer_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `writer_balance`
--
ALTER TABLE `writer_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `books`
--
ALTER TABLE `books`
  ADD CONSTRAINT `books_ibfk_1` FOREIGN KEY (`writer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `books_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`writer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`writer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `withdrawals_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `writer_balance`
--
ALTER TABLE `writer_balance`
  ADD CONSTRAINT `writer_balance_ibfk_1` FOREIGN KEY (`writer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
