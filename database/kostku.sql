-- ============================================================
--  KOSTKU - Database Schema + Dummy Data (Multi-Image Support)
--  Stack  : PHP native + MySQL/MariaDB (Laragon/XAMPP)
--  Import : phpMyAdmin > Select 'kostku' > Import tab / SQL tab
-- ============================================================

CREATE DATABASE IF NOT EXISTS kostku
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE kostku;

SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW  IF EXISTS v_booking_detail;
DROP VIEW  IF EXISTS v_boarding_house_summary;
DROP VIEW  IF EXISTS v_kost_ringkas;

DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS ulasan;
DROP TABLE IF EXISTS favorites;
DROP TABLE IF EXISTS favorit;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS booking;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS kamar;
DROP TABLE IF EXISTS boarding_house_images;
DROP TABLE IF EXISTS boarding_houses;
DROP TABLE IF EXISTS kost;
DROP TABLE IF EXISTS banners;
DROP TABLE IF EXISTS banner;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. USERS
--    Screens: Login, Register, Profile > Detail Akun, Admin Sidebar
CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(100) NULL,
  email         VARCHAR(150) NOT NULL,
  password      VARCHAR(255) NOT NULL,
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  description   TEXT NULL,
  country_code  VARCHAR(6) NOT NULL DEFAULT '+62',
  phone_number  VARCHAR(20) NULL,
  profile_photo VARCHAR(255) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. BOARDING_HOUSES (Data Utama Kost)
CREATE TABLE boarding_houses (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name             VARCHAR(150) NOT NULL,
  address          VARCHAR(255) NOT NULL,
  city             VARCHAR(100) NOT NULL,
  description      TEXT NULL,
  type             ENUM('male','female','mixed','house') NOT NULL DEFAULT 'mixed',
  price_per_month  INT UNSIGNED NOT NULL,
  total_rooms      INT UNSIGNED NOT NULL DEFAULT 0,
  contact          VARCHAR(20) NOT NULL,
  status           ENUM('available','full') NOT NULL DEFAULT 'available',
  latitude         DECIMAL(10,8) NULL,
  longitude        DECIMAL(11,8) NULL,
  photo            VARCHAR(255) NULL,                -- Gambar utama/thumbnail
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bh_city (city),
  KEY idx_bh_type (type),
  KEY idx_bh_status (status),
  KEY idx_bh_price (price_per_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BOARDING_HOUSE_IMAGES (Galeri/Banyak Gambar per Kost)
--    Digunakan untuk slider/carousel gambar pada Halaman Detail Kost
CREATE TABLE boarding_house_images (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  boarding_house_id INT UNSIGNED NOT NULL,
  image             VARCHAR(255) NOT NULL,          -- Nama file foto di uploads/
  sort_order        TINYINT UNSIGNED NOT NULL DEFAULT 1, -- Urutan slide gambar
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bh_images_bh (boarding_house_id),
  CONSTRAINT fk_bh_images_bh FOREIGN KEY (boarding_house_id)
    REFERENCES boarding_houses (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ROOMS (Pilihan Kamar per Kost)
CREATE TABLE rooms (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  boarding_house_id  INT UNSIGNED NOT NULL,
  name               VARCHAR(100) NOT NULL,
  room_type          VARCHAR(50) NOT NULL,
  description        TEXT NULL,
  price_per_month    INT UNSIGNED NOT NULL,
  status             ENUM('available','occupied') NOT NULL DEFAULT 'available',
  photo              VARCHAR(255) NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rooms_bh (boarding_house_id),
  KEY idx_rooms_status (status),
  CONSTRAINT fk_rooms_bh FOREIGN KEY (boarding_house_id)
    REFERENCES boarding_houses (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BOOKINGS (Transaksi Pemesanan)
CREATE TABLE bookings (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id           INT UNSIGNED NULL,
  room_id           INT UNSIGNED NOT NULL,
  booker_name       VARCHAR(100) NOT NULL,
  contact           VARCHAR(20) NOT NULL,
  check_in_date     DATE NOT NULL,
  check_out_date    DATE NOT NULL,
  price_at_booking  INT UNSIGNED NOT NULL,
  total_price       INT UNSIGNED NOT NULL,
  status            ENUM('pending','confirmed','rejected','cancelled','completed')
                    NOT NULL DEFAULT 'pending',
  hidden_by_user    TINYINT(1) NOT NULL DEFAULT 0,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bookings_user (user_id),
  KEY idx_bookings_room_dates (room_id, check_in_date, check_out_date),
  KEY idx_bookings_status (status),
  CONSTRAINT fk_bookings_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_bookings_room FOREIGN KEY (room_id)
    REFERENCES rooms (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_bookings_dates CHECK (check_out_date > check_in_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. FAVORITES
CREATE TABLE favorites (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id            INT UNSIGNED NOT NULL,
  boarding_house_id  INT UNSIGNED NOT NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_favorites_user_bh (user_id, boarding_house_id),
  KEY idx_favorites_bh (boarding_house_id),
  CONSTRAINT fk_favorites_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_favorites_bh FOREIGN KEY (boarding_house_id)
    REFERENCES boarding_houses (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. REVIEWS (Feedback & Rating)
CREATE TABLE reviews (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id            INT UNSIGNED NOT NULL,
  boarding_house_id  INT UNSIGNED NOT NULL,
  rating             TINYINT UNSIGNED NOT NULL,
  comment            TEXT NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reviews_user_bh (user_id, boarding_house_id),
  KEY idx_reviews_bh (boarding_house_id),
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reviews_bh FOREIGN KEY (boarding_house_id)
    REFERENCES boarding_houses (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. BANNERS
CREATE TABLE banners (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(100) NULL,
  image       VARCHAR(255) NOT NULL,
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- VIEWS
CREATE VIEW v_boarding_house_summary AS
SELECT
  b.id, b.name, b.address, b.city, b.description, b.type,
  b.price_per_month, b.total_rooms, b.contact, b.status, b.latitude, b.longitude, b.photo,
  (SELECT COUNT(*) FROM rooms r WHERE r.boarding_house_id = b.id) AS room_option_count,
  (SELECT COALESCE(ROUND(AVG(rv.rating), 1), 0) FROM reviews rv WHERE rv.boarding_house_id = b.id) AS avg_rating,
  (SELECT COUNT(*) FROM reviews rv WHERE rv.boarding_house_id = b.id) AS review_count
FROM boarding_houses b;

CREATE VIEW v_booking_detail AS
SELECT
  bk.id, bk.user_id, bk.booker_name, bk.contact,
  bk.check_in_date, bk.check_out_date, bk.price_at_booking, bk.total_price,
  bk.status, bk.hidden_by_user, bk.created_at,
  r.id AS room_id, r.name AS room_name, r.photo AS room_photo,
  b.id AS boarding_house_id, b.name AS boarding_house_name,
  b.city, b.photo AS boarding_house_photo
FROM bookings bk
JOIN rooms r           ON r.id = bk.room_id
JOIN boarding_houses b ON b.id = r.boarding_house_id;

-- DUMMY DATA 

INSERT INTO users (id, name, email, password, role, description, country_code, phone_number) VALUES
(1, 'Andi Atmin',   'admin@kostku.com', '$2b$12$LBBMKHp7D0c27tIf7cBCyep84DKSKrU.a23oJ88YFoCIwaUyNAg6u', 'admin', NULL, '+62', '081200000001'),
(2, 'Asep Santoso', 'asep@gmail.com',   '$2b$12$LBBMKHp7D0c27tIf7cBCyep84DKSKrU.a23oJ88YFoCIwaUyNAg6u', 'user',  'Mahasiswa yang sedang mencari kos dekat kampus.', '+62', '081234567890'),
(3, 'Siti Rahma',   'siti@gmail.com',   '$2b$12$LBBMKHp7D0c27tIf7cBCyep84DKSKrU.a23oJ88YFoCIwaUyNAg6u', 'user',  NULL, '+62', '085211112222');

INSERT INTO boarding_houses (id, name, address, city, description, type, price_per_month, total_rooms, contact, status, latitude, longitude, photo) VALUES
(1, 'Kost Melati Putri',  'Jl. Ahmad Yani No. 12',       'Pontianak',  'Kost putri bersih dan nyaman, dekat kampus dan pusat kota.',   'female', 1000000, 10, '6281234567801', 'available', -0.03070000, 109.33210000, 'melati_cover.jpg'),
(2, 'Kost Anggrek Putra', 'Jl. Tanjungpura No. 45',      'Pontianak',  'Kost putra dengan parkir luas dan Wi-Fi cepat.',               'male',   1200000, 12, '6281234567802', 'available', -0.02630000, 109.34250000, 'anggrek_cover.jpg'),
(3, 'Kost Mawar Campur',  'Jl. Sungai Raya Dalam No. 8', 'Kubu Raya',  'Kost campuran dengan kamar luas dan lingkungan tenang.',       'mixed',   900000, 15, '6281234567803', 'available', -0.06540000, 109.38070000, 'mawar_cover.jpg'),
(4, 'Kontrakan Dahlia',   'Jl. Diponegoro No. 20',       'Singkawang', 'Kontrakan rumah tinggal dekat pusat kuliner.',                'house',  1500000,  3, '6281234567804', 'available',  0.90600000, 108.98720000, 'dahlia_cover.jpg');

-- Sample Galeri Gambar untuk Slider / Carousel Detail Kost ID 1
INSERT INTO boarding_house_images (boarding_house_id, image, sort_order) VALUES
(1, 'melati_depan.jpg', 1),
(1, 'melati_kamar_mandi.jpg', 2),
(1, 'melati_dapur.jpg', 3),
(1, 'melati_parkiran.jpg', 4);

INSERT INTO rooms (id, boarding_house_id, name, room_type, description, price_per_month, status) VALUES
(1, 1, 'Kamar A1', 'Standard', 'Kamar 3x3 m, kasur, lemari, kamar mandi luar.',     1000000, 'available'),
(2, 1, 'Kamar A2', 'Deluxe',   'Kamar 3x4 m, AC, kamar mandi dalam.',               1300000, 'occupied'),
(3, 2, 'Kamar B1', 'Standard', 'Kamar 3x3 m, kipas angin, kamar mandi dalam.',      1200000, 'available'),
(4, 2, 'Kamar B2', 'Deluxe',   'Kamar 3x4 m, AC, meja kerja, kamar mandi dalam.',   1500000, 'available'),
(5, 3, 'Kamar C1', 'Standard', 'Kamar 3x3 m, kasur dan lemari.',                     900000, 'available'),
(6, 3, 'Kamar C2', 'VIP',      'Kamar 4x4 m, AC, kamar mandi dalam.',               1100000, 'available'),
(7, 4, 'Kontrakan 1', 'Rumah', 'Rumah 2 Kamar Tidur, 1 Kamar Mandi, Dapur.',        1500000, 'available');

INSERT INTO bookings (id, user_id, room_id, booker_name, contact, check_in_date, check_out_date, price_at_booking, total_price, status) VALUES
(1, 2, 1, 'Asep Santoso', '081234567890', '2025-01-24', '2025-02-24', 1000000, 1000000, 'completed'),
(2, 2, 3, 'Asep Santoso', '081234567890', '2026-03-01', '2026-06-01', 1200000, 3600000, 'completed'),
(3, 2, 5, 'Asep Santoso', '081234567890', '2026-10-15', '2026-11-15',  900000,  900000, 'pending'),
(4, 3, 2, 'Siti Rahma',   '085211112222', '2026-10-01', '2026-12-01', 1300000, 2600000, 'confirmed');

INSERT INTO favorites (user_id, boarding_house_id) VALUES
(2, 1),
(2, 3),
(3, 2);

INSERT INTO reviews (user_id, boarding_house_id, rating, comment) VALUES
(2, 1, 5, 'Kamarnya bersih, pemilik ramah, lokasi strategis.'),
(3, 1, 4, 'Nyaman, hanya parkir motor agak sempit.'),
(2, 2, 4, 'Wi-Fi cepat dan lingkungan aman.');

INSERT INTO banners (title, image, sort_order, is_active) VALUES
('Temukan kost impianmu',               'banner1.jpg', 1, 1),
('Booking mudah dan cepat',             'banner2.jpg', 2, 1),
('Kost terpercaya di Kalimantan Barat', 'banner3.jpg', 3, 1);
