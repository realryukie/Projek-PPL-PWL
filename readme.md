# KOSTKU
KOSTKU adalah aplikasi pencarian dan pemesanan kos berbasis web yang dibangun menggunakan PHP native, MySQL/MariaDB, HTML, CSS, dan JavaScript. Aplikasi ini menghubungkan pencari kos dengan pengelola kos melalui pencarian berbasis lokasi, filter kebutuhan, detail kamar, favorit, ulasan, serta pengelolaan pemesanan.

## Deskripsi Masalah
Pencari kos sering kesulitan membandingkan informasi hunian karena data lokasi, harga, tipe kos, ketersediaan kamar, foto, dan kontak tersebar di berbagai sumber. Selain alamat, pencari kos biasanya perlu mengetahui apakah kos dekat dengan tujuan penting mereka, seperti kampus atau tempat kerja. Pihak pengelola kos juga membutuhkan satu tempat untuk mengelola data kos, kamar, pemesanan, serta status ketersediaannya secara konsisten.

Tanpa sistem terpusat, proses pencarian dan pemesanan dapat memerlukan komunikasi berulang, rentan salah informasi, dan sulit memantau riwayat pemesanan maupun perubahan status kamar.

## Solusi
KOSTKU menyediakan sistem terpusat yang mempermudah pencarian kos melalui tampilan beranda, pencarian dengan filter, dan halaman detail. Alamat dan peta lokasi ditampilkan pada detail kos agar pencari dapat melihat posisi hunian dan menilai kedekatannya dengan kampus, tempat kerja, atau tujuan lain. Pengguna juga dapat melihat pilihan kamar, mengecek ketersediaan berdasarkan tanggal, menyimpan kos favorit, memberikan ulasan, serta mengelola profil dan riwayat pemesanan.

Pengelola mendapatkan panel admin untuk mengelola banner, data kos, galeri foto, pilihan kamar, pemesanan, dan pengguna. Status pemesanan serta ketersediaan kamar dapat diperbarui melalui sistem, sehingga informasi yang ditampilkan kepada pengguna lebih terstruktur dan mudah dipantau.

## Fitur Utama

### Pengguna
- Registrasi, login, dan logout.
- Pencarian kos berdasarkan lokasi, tipe, rentang harga, tanggal, dan pengurutan.
- Penyaringan ketersediaan kamar berdasarkan periode tanggal.
- Melihat detail kos, galeri foto, kamar, alamat, harga, dan ulasan.
- Melihat lokasi kos pada peta OpenStreetMap di halaman detail untuk menilai kedekatannya dengan kampus, tempat kerja, atau tujuan lain.
- Membuat pemesanan dan melihat riwayat pemesanan.
- Menyimpan serta mengelola kos favorit.
- Mengubah profil, kata sandi, kontak, dan foto profil.
- Memberikan satu ulasan dan nilai 1-5 untuk setiap kos.

### Admin
- Mengelola banner halaman beranda.
- Menambah, mengubah, dan menghapus data kos.
- Mengelola galeri foto dan informasi kamar.
- Melihat serta memperbarui status pemesanan.
- Mengelola data pengguna.
- Memantau daftar kos, kamar, dan pemesanan.

### Keamanan dan Validasi
- Autentikasi sesi untuk membatasi halaman login dan admin.
- Proteksi CSRF pada aksi berbasis formulir.
- Escaping output untuk mengurangi risiko Cross-Site Scripting (XSS).
- Prepared statement untuk mencegah SQL Injection.
- Password disimpan menggunakan `password_hash()`.
- Validasi tipe, ukuran, dan format foto sebelum proses unggah.

## Teknologi
- PHP native
- MySQL atau MariaDB
- HTML5
- CSS3
- JavaScript
- Laragon atau XAMPP
- phpMyAdmin

## Instalasi dan Konfigurasi
1. Letakkan folder proyek ini di direktori web Laragon, misalnya `D:\laragon\www\kostku`.
2. Pastikan Apache dan MySQL sudah berjalan.
3. Buat database dan import file `database/kostku.sql` melalui phpMyAdmin atau MySQL CLI.
4. Sesuaikan koneksi database pada `config/config.php` jika konfigurasi lokal berbeda:

```php
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'kostku';
```

5. Buka aplikasi melalui Laragon, misalnya:
   - `http://localhost/Projek_PPL_PWL`
   - atau domain virtual host Laragon yang telah dibuat untuk folder proyek.

## Akun Demo
Semua akun demo menggunakan kata sandi `password123`.

Admin    = `admin@kostku.com`
Pengguna = `asep@gmail.com`
Pengguna = `siti@gmail.com`

## Struktur Folder

```text
config/       Konfigurasi aplikasi dan koneksi database
auth/         Registrasi, login, dan logout
admin/        Halaman dan fitur panel admin
includes/     Autentikasi, helper, unggah, kartu, header, dan footer
assets/css/   Berkas stylesheet
assets/js/    Berkas JavaScript
assets/img/   Aset gambar dan banner
database/     Skema database beserta data awal
uploads/      Foto yang diunggah pengguna dan admin
  boarding_houses/  Galeri dan foto kos
  rooms/            Foto kamar
  profiles/         Foto profil
```

## Struktur Halaman

- `index.php`: Beranda, banner, rekomendasi kos, dan informasi situs.
- `search.php`: Pencarian serta filter kos.
- `product_desc.php`: Detail kos, kamar, galeri, favorit, ulasan, dan pemesanan.
- `profile.php`: Profil akun, riwayat pemesanan, dan daftar favorit.
- `auth/login.php`: Login pengguna.
- `auth/register.php`: Registrasi pengguna.
- `auth/logout.php`: Logout dan penghentian sesi.
- `admin/`: Panel admin untuk data kos, kamar, pemesanan, pengguna, dan banner.

## Struktur Basis Data

Database `kostku` menggunakan tabel utama berikut:

- `users`: akun pengguna dan admin.
- `boarding_houses`: data utama kos atau kontrakan.
- `boarding_house_images`: galeri foto untuk setiap kos.
- `rooms`: pilihan kamar beserta statusnya.
- `bookings`: data pemesanan dan riwayat status.
- `favorites`: relasi kos favorit milik pengguna.
- `reviews`: ulasan dan rating pengguna terhadap kos.
- `banners`: banner aktif pada halaman beranda.

Database juga menyediakan view:

- `v_boarding_house_summary`: ringkasan kos beserta jumlah kamar, rating rata-rata, dan jumlah ulasan.
- `v_booking_detail`: gabungan data pemesanan, kamar, kos, dan pengguna.

## Alur Pemesanan

1. Pengguna mencari kos dan memilih kamar yang tersedia.
2. Pengguna mengisi nama, nomor kontak, tanggal masuk, dan tanggal keluar.
3. Sistem menghitung total harga berdasarkan lama sewa.
4. Pemesanan awal memiliki status `pending`.
5. Admin memeriksa dan mengubah status menjadi `confirmed`, `rejected`, `cancelled`, atau `completed`.
6. Riwayat pemesanan dapat dilihat pada halaman profil pengguna.

## Pengembangan

Perubahan skema database dapat dikelola pada `database/kostku.sql`. Gunakan prepared statement pada query yang menerima input pengguna, jalankan validasi CSRF pada aksi POST, dan pastikan unggahan tetap diproses melalui helper yang tersedia pada `includes/upload.php`.

## Anggota Kelompok

- David Jaya Amin
- Vincent
- Samuel Adam
- Caroline Aprillianty