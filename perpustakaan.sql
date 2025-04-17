-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 10, 2025 at 12:55 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `perpustakaan`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `calculate_smart_return_date` (IN `user_id_param` INT, IN `book_id_param` INT, OUT `return_date` DATE)   BEGIN
    DECLARE user_reliability DECIMAL(3,2);
    DECLARE book_popularity INT;
    DECLARE base_loan_days INT DEFAULT 7;
    DECLARE additional_days INT;
    
    -- Calculate user reliability based on return history (0-1 scale)
    SELECT 
        CASE 
            WHEN COUNT(*) = 0 THEN 0.5 -- New borrower starts at neutral
            ELSE COUNT(CASE WHEN tanggal_kembali IS NOT NULL AND tanggal_kembali <= DATE_ADD(tanggal_pinjam, INTERVAL 7 DAY) THEN 1 END) / COUNT(*)
        END INTO user_reliability
    FROM peminjaman
    WHERE user_id = user_id_param;
    
    -- Get book popularity
    SELECT popularity_score INTO book_popularity FROM books WHERE id = book_id_param;
    
    -- Calculate additional days based on reliability and inverse of popularity
    SET additional_days = FLOOR(user_reliability * 7) - LEAST(FLOOR(book_popularity/10), 3);
    
    -- Ensure minimum loan period is maintained
    SET return_date = DATE_ADD(CURRENT_DATE, INTERVAL (base_loan_days + GREATEST(additional_days, 0)) DAY);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `extend_loan` (IN `borrow_id` INT, IN `additional_days` INT)   BEGIN
    DECLARE current_return_date DATE;
    
    -- Ambil tanggal pengembalian yang dijadwalkan dari tabel borrows
    SELECT return_date INTO current_return_date
    FROM borrows
    WHERE id = borrow_id;

    -- Perbarui tanggal pengembalian dengan menambah hari
    UPDATE borrows
    SET return_date = DATE_ADD(current_return_date, INTERVAL additional_days DAY)
    WHERE id = borrow_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `generate_recommendations` (IN `user_id_param` INT)   BEGIN
    -- Clear previous recommendations for this user
    DELETE FROM book_recommendations WHERE user_id = user_id_param;
    
    -- Insert new recommendations based on categories the user has borrowed from
    INSERT INTO book_recommendations (user_id, book_id, reason, score, created_at)
    SELECT DISTINCT
        user_id_param as user_id,
        b.id as book_id,
        CONCAT('Based on your interest in ', c.name, ' books') as reason,
        (0.7 + (RAND() * 0.3)) as score, -- Base score with some randomness
        NOW() as created_at
    FROM books b
    JOIN book_categories bc ON b.id = bc.book_id
    JOIN categories c ON bc.category_id = c.id
    WHERE 
        -- Find categories user has borrowed from
        bc.category_id IN (
            SELECT DISTINCT bc2.category_id
            FROM peminjaman p
            JOIN books b2 ON p.book_id = b2.id
            JOIN book_categories bc2 ON b2.id = bc2.book_id
            WHERE p.user_id = user_id_param
        )
        -- Exclude books already borrowed
        AND b.id NOT IN (
            SELECT DISTINCT book_id 
            FROM peminjaman 
            WHERE user_id = user_id_param
        )
        -- Only recommend available books
        AND b.stock > 0
    LIMIT 5;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_seasonal_recommendations` ()   BEGIN
    DECLARE current_season VARCHAR(20);
    
    -- Determine current season based on date
    SET current_season = CASE 
        WHEN MONTH(CURRENT_DATE) IN (3, 4, 5) THEN 'spring'
        WHEN MONTH(CURRENT_DATE) IN (6, 7, 8) THEN 'summer'
        WHEN MONTH(CURRENT_DATE) IN (9, 10, 11) THEN 'fall'
        ELSE 'winter'
    END;
    
    -- Find seasonal recommendations and matching books
    SELECT 
        b.id, 
        b.title, 
        b.author, 
        b.description,
        sr.theme AS seasonal_theme
    FROM 
        books b
    JOIN 
        book_categories bc ON b.id = bc.book_id
    JOIN 
        seasonal_recommendations sr ON bc.category_id = sr.category_id
    WHERE 
        (sr.season = current_season OR 
         (sr.active_from <= CURRENT_DATE AND sr.active_to >= CURRENT_DATE))
        AND b.stock > 0
    ORDER BY 
        b.popularity_score DESC
    LIMIT 10;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `return_book` (IN `borrow_id` INT, IN `return_date` DATE)   BEGIN
    DECLARE due_date DATE;
    DECLARE fine DECIMAL(10, 2) DEFAULT 0.00;
    
    -- Ambil tanggal jatuh tempo (return_date yang dijadwalkan) dari tabel borrows
    SELECT return_date INTO due_date
    FROM borrows
    WHERE id = borrow_id;

    -- Jika pengembalian terlambat, hitung denda (misalnya, denda 500 per hari keterlambatan)
    IF return_date > due_date THEN
        SET fine = DATEDIFF(return_date, due_date) * 500;
    END IF;

    -- Perbarui status dan tanggal pengembalian aktual
    UPDATE borrows
    SET actual_return_date = return_date,
        status = 'returned',
        fine = fine
    WHERE id = borrow_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `upcoming_due_reminders` (IN `days_threshold` INT)   BEGIN
    SELECT 
        p.id AS peminjaman_id,
        u.fullname AS borrower_name,
        u.email,
        b.title AS book_title,
        p.tanggal_pinjam AS borrow_date,
        DATE_ADD(p.tanggal_pinjam, INTERVAL 7 DAY) AS due_date,
        DATEDIFF(DATE_ADD(p.tanggal_pinjam, INTERVAL 7 DAY), CURRENT_DATE) AS days_remaining
    FROM 
        peminjaman p
    JOIN 
        users u ON p.user_id = u.id
    JOIN 
        books b ON p.book_id = b.id
    WHERE 
        p.status = 'dipinjam'
        AND DATEDIFF(DATE_ADD(p.tanggal_pinjam, INTERVAL 7 DAY), CURRENT_DATE) BETWEEN 0 AND days_threshold
    ORDER BY 
        days_remaining ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `update_reading_progress` (IN `user_id_param` INT, IN `book_id_param` INT, IN `pages_read_param` INT, IN `status_param` VARCHAR(20), IN `notes_param` TEXT)   BEGIN
    DECLARE total_pages INT;
    DECLARE completion_pct DECIMAL(5,2);
    
    -- Get total pages for the book
    SELECT page_count INTO total_pages FROM books WHERE id = book_id_param;
    
    -- Calculate completion percentage
    IF total_pages > 0 THEN
        SET completion_pct = (pages_read_param / total_pages) * 100;
    ELSE
        SET completion_pct = 0;
    END IF;
    
    -- Update or insert reading journey record
    INSERT INTO reading_journey (user_id, book_id, pages_read, completion_percentage, status, notes)
    VALUES (user_id_param, book_id_param, pages_read_param, completion_pct, status_param, notes_param)
    ON DUPLICATE KEY UPDATE
        pages_read = pages_read_param,
        completion_percentage = completion_pct,
        status = status_param,
        notes = CASE WHEN notes_param IS NOT NULL THEN notes_param ELSE notes END,
        finish_date = CASE WHEN status_param = 'completed' THEN CURRENT_DATE ELSE finish_date END;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `is_age_appropriate` (`user_id` INT, `book_id` INT) RETURNS TINYINT(1) DETERMINISTIC BEGIN
    DECLARE user_birth_year INT;
    DECLARE user_age INT;
    DECLARE book_age_rating VARCHAR(20);
    
    -- Get user birth year (assuming you add a birth_date column to users table)
    SELECT YEAR(birth_date) INTO user_birth_year FROM users WHERE id = user_id;
    
    -- Calculate user age
    SET user_age = YEAR(CURRENT_DATE) - user_birth_year;
    
    -- Get book age rating
    SELECT age_rating INTO book_age_rating FROM books WHERE id = book_id;
    
    -- Check appropriateness
    RETURN CASE
        WHEN book_age_rating = 'all_ages' THEN TRUE
        WHEN book_age_rating = 'children' AND user_age <= 12 THEN TRUE
        WHEN book_age_rating = 'young_adult' AND user_age >= 13 THEN TRUE
        WHEN book_age_rating = 'adult' AND user_age >= 18 THEN TRUE
        WHEN book_age_rating = 'children' AND user_age > 12 THEN TRUE -- Adults can borrow children's books
        ELSE FALSE
    END;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `author_statistics`
--

CREATE TABLE `author_statistics` (
  `author_name` varchar(100) NOT NULL,
  `books_count` int DEFAULT '0',
  `total_borrows` int DEFAULT '0',
  `popularity_score` decimal(10,2) DEFAULT '0.00',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `author_statistics`
--

INSERT INTO `author_statistics` (`author_name`, `books_count`, `total_borrows`, `popularity_score`, `last_updated`) VALUES
('Abdullah Zaky', 1, 6, 0.00, '2025-03-20 09:58:48'),
('Albert Einstein', 1, 2, 0.00, '2025-03-20 09:58:48'),
('Alwisol', 1, 2, 0.00, '2025-03-20 09:58:48'),
('Andrea Hirata', 1, 3, 4.00, '2025-03-30 03:39:56'),
('Haidar Musyafa', 1, 3, 4.00, '2025-03-22 04:23:01'),
('Henry Manampiring', 1, 6, 7.00, '2025-03-22 09:03:21'),
('James Clear', 1, 4, 0.00, '2025-03-20 09:58:48'),
('jiwa raga', 1, 7, 8.00, '2025-04-09 03:02:08'),
('jiwi', 1, 1, 0.00, '2025-03-28 19:48:47'),
('M.C. Ricklefs', 1, 3, 0.00, '2025-03-20 09:58:48'),
('Masashi Kishimoto', 1, 8, 0.00, '2025-03-20 09:58:48'),
('miaw', 1, 17, 18.00, '2025-04-09 04:38:39'),
('Pramoedya Ananta Toer', 1, 3, 0.00, '2025-03-20 09:58:48'),
('sbwbhbs', 2, 1, 0.00, '2025-04-05 04:54:59');

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(100) NOT NULL,
  `publisher` varchar(100) NOT NULL,
  `year_published` year DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `description` text,
  `stock` int NOT NULL DEFAULT '0',
  `cover` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `language` varchar(50) DEFAULT 'Indonesia',
  `page_count` int DEFAULT NULL,
  `availability_status` varchar(20) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL COMMENT 'Physical location in library',
  `popularity_score` int DEFAULT '0',
  `age_rating` enum('children','young_adult','adult','all_ages') DEFAULT 'all_ages',
  `year` int NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `content` text,
  `cover_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `publisher`, `year_published`, `isbn`, `category`, `description`, `stock`, `cover`, `created_at`, `updated_at`, `language`, `page_count`, `availability_status`, `location`, `popularity_score`, `age_rating`, `year`, `file_path`, `content`, `cover_image`) VALUES
(1, 'Laskar Pelangi', 'Andrea Hirata', 'Bentang Pustaka', '2005', '9789793062792', 'Fiksi', 'Novel yang menceritakan kisah persahabatan 10 anak di Belitung.', 0, 'laskar_pelangi.jpg', '2025-03-14 07:23:10', '2025-04-03 01:49:51', 'Indonesia', NULL, 'available', NULL, 1, 'all_ages', 0, NULL, NULL, ''),
(2, 'Bumi Manusia', 'Pramoedya Ananta Toer', 'Hasta Mitra', '1980', '9789799731234', 'Fiksi', 'Novel pertama dari Tetralogi Buru yang menceritakan kisah Minke di masa kolonial Belanda.', 0, 'bumi_manusia.jpg', '2025-03-14 07:23:10', '2025-03-15 09:34:19', 'Indonesia', NULL, 'available', NULL, 0, 'all_ages', 0, NULL, NULL, ''),
(3, 'Filosofi Teras', 'Henry Manampiring', 'Kompas', '2018', '9786024125189', 'Non-Fiksi', 'Buku yang membahas filosofi Stoa dan penerapannya dalam kehidupan modern.', 0, 'filosofi_teras.jpg', '2025-03-14 07:23:10', '2025-03-22 09:03:21', 'Indonesia', NULL, 'available', NULL, 1, 'all_ages', 0, NULL, NULL, ''),
(4, 'Atomic Habits', 'James Clear', 'Gramedia', '2019', '9786020633497', 'Non-Fiksi', 'Buku tentang mengubah kebiasaan kecil untuk hasil yang luar biasa.', 0, 'atomic_habits.jpg', '2025-03-14 07:23:10', '2025-04-09 14:36:18', 'Indonesia', NULL, 'available', NULL, 0, 'all_ages', 0, NULL, NULL, ''),
(5, 'Psikologi Kepribadian', 'Alwisol', 'UMM Press', '2015', '9786028700375', 'Pendidikan', 'Buku referensi tentang berbagai teori kepribadian dalam psikologi.', 0, 'psikologi_kepribadian.jpg', '2025-03-14 07:23:10', '2025-03-19 08:47:54', 'Indonesia', NULL, 'available', NULL, 0, 'all_ages', 0, NULL, NULL, ''),
(6, 'Sejarah Indonesia Modern', 'M.C. Ricklefs', 'Serambi', '2008', '9789791084287', 'Sejarah', 'Buku yang membahas sejarah Indonesia dari masa kolonial hingga modern.', 0, 'sejarah_indonesia_modern.jpg', '2025-03-14 07:23:10', '2025-03-15 09:59:06', 'Indonesia', NULL, 'available', NULL, 0, 'all_ages', 0, NULL, NULL, ''),
(7, 'Teori Relativitas', 'Albert Einstein', 'Kepustakaan Populer Gramedia', '2010', '9786024816452', 'Sains', 'Buku yang menjelaskan teori relativitas Einstein dengan bahasa yang mudah dipahami.', 0, 'teori_relatifitas.jpg', '2025-03-14 07:23:10', '2025-03-15 09:59:14', 'Indonesia', NULL, 'available', NULL, 0, 'all_ages', 0, NULL, NULL, ''),
(8, 'Pemrograman Web', 'Abdullah Zaky', 'Informatika', '2020', '9786230101234', 'Teknologi', 'Buku panduan untuk belajar pemrograman web dari dasar hingga mahir.', 0, 'pemrograman_web.jpg', '2025-03-14 07:23:10', '2025-03-19 08:47:44', 'Indonesia', NULL, 'available', NULL, 0, 'all_ages', 0, NULL, NULL, ''),
(9, 'Hamka: Sebuah Novel Biografi', 'Haidar Musyafa', 'Imania', '2019', '9786020639321', 'Biografi', 'Novel biografi tentang kehidupan dan perjuangan Buya Hamka.', 0, 'hamka.jpg', '2025-03-14 07:23:10', '2025-03-22 04:23:01', 'Indonesia', NULL, 'available', NULL, 1, 'all_ages', 0, NULL, NULL, ''),
(10, 'Naruto Vol. 1', 'Masashi Kishimoto', 'Elex Media Komputindo', '2005', '9786020427859', 'Komik', 'Manga tentang perjalanan Naruto menjadi ninja terkuat di desanya.', 0, 'naruto.jpg', '2025-03-14 07:23:10', '2025-03-19 08:48:19', 'Indonesia', NULL, 'available', NULL, 0, 'all_ages', 0, NULL, NULL, ''),
(11, 'jawa', 'jiwi', 'tokobuku', NULL, NULL, 'fiksi', 'bagus', 0, '67e366e9cb4e1.jpg', '2025-03-26 02:31:05', '2025-03-28 19:48:47', 'Indonesia', NULL, 'available', NULL, 1, 'all_ages', 2023, NULL, NULL, ''),
(12, 'kucing', 'miaw', 'tokobuku', NULL, NULL, 'fiksi', 'kucing', 0, '67e36747c71d1.jpg', '2025-03-26 02:32:39', '2025-03-30 03:40:49', 'Indonesia', NULL, 'available', NULL, 1, 'all_ages', 2023, NULL, NULL, ''),
(13, 'buku kucing', 'miaw', 'tokobuku', NULL, NULL, 'fiksi', 'bagus ni', 0, '67e8be2d0f1d9.jpg', '2025-03-30 03:44:45', '2025-04-08 14:28:51', 'Indonesia', NULL, 'available', NULL, 5, 'all_ages', 2023, NULL, NULL, ''),
(14, 'kucing', 'miaw', 'tokobuku', NULL, NULL, 'fiksi', 'baguss', 2, '67ea2a9e50221.jpg', '2025-03-31 05:39:42', '2025-04-10 12:40:44', 'Indonesia', NULL, 'available', NULL, 10, 'all_ages', 2025, NULL, '<p>hahaha bagus banget woy</p>', ''),
(15, 'pelangi pagi', 'jiwa raga', 'tokobuku', NULL, NULL, 'fiksi', 'mntp', 0, NULL, '2025-04-05 04:24:56', '2025-04-10 03:18:19', 'Indonesia', NULL, 'not available', NULL, 7, 'all_ages', 2024, NULL, '<p>pelangi yang indah ya</p>', '67f0b09833341.jpg'),
(18, 'sus', 'sbwbhbs', 'tokobuku', NULL, NULL, 'fiksi', 'shausn', 0, NULL, '2025-04-05 04:54:52', '2025-04-05 12:42:00', 'Indonesia', NULL, 'available', NULL, 1, 'all_ages', 2025, NULL, '<p>xsbxuhisas</p>', '67f0b79ccc2bf.jpg'),
(19, 'kucingmiaw', 'miaw', 'tokobuku', NULL, NULL, 'fiksi', 'baguss', 0, NULL, '2025-04-08 08:20:05', '2025-04-08 13:28:39', 'Indonesia', NULL, 'available', NULL, 1, 'all_ages', 2025, NULL, '<p>hahaha</p>', '67f4dc35c4b00.jpg'),
(39, 'miaw1', 'miaw', 'tokobuku', NULL, NULL, 'fiksi', 'qw', 0, NULL, '2025-04-10 04:02:24', '2025-04-10 04:02:24', 'Indonesia', NULL, NULL, NULL, 0, 'all_ages', 2025, NULL, '<p>we</p>', '67f742d0edc9b.jpg'),
(41, 'miaw1', 'miaw', 'tokobuku', NULL, NULL, 'fiksi', 'mantap', 0, NULL, '2025-04-10 12:21:51', '2025-04-10 12:21:51', 'Indonesia', NULL, NULL, NULL, 0, 'all_ages', 2025, NULL, '<p>hi</p>', '67f7b7df2c780.jpg'),
(42, 'miaw2', 'miaw', 'tokobuku', NULL, NULL, 'fiksi', 'bagus', 1, NULL, '2025-04-10 12:41:55', '2025-04-10 12:42:12', 'Indonesia', NULL, NULL, NULL, 0, 'all_ages', 2023, NULL, '<p>halo</p>', '67f7bc939c4d7.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `book_categories`
--

CREATE TABLE `book_categories` (
  `book_id` int NOT NULL,
  `category_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `book_categories`
--

INSERT INTO `book_categories` (`book_id`, `category_id`) VALUES
(1, 1),
(2, 1),
(3, 2),
(4, 2),
(5, 3),
(2, 4),
(6, 4),
(7, 5),
(8, 6),
(1, 7),
(2, 7),
(9, 9),
(10, 10);

-- --------------------------------------------------------

--
-- Stand-in structure for view `book_loan_history`
-- (See below for the actual view)
--
CREATE TABLE `book_loan_history` (
`author` varchar(100)
,`avg_loan_duration` decimal(12,4)
,`book_id` int
,`current_status` varchar(19)
,`last_borrowed_date` date
,`last_borrower` varchar(100)
,`times_borrowed` bigint
,`title` varchar(255)
);

-- --------------------------------------------------------

--
-- Table structure for table `book_recommendations`
--

CREATE TABLE `book_recommendations` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `book_id` int NOT NULL,
  `reason` text,
  `score` decimal(3,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `borrows`
--

CREATE TABLE `borrows` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `book_id` int NOT NULL,
  `borrow_date` date NOT NULL,
  `return_date` date NOT NULL,
  `actual_return_date` date DEFAULT NULL,
  `status` enum('borrowed','returned','overdue') NOT NULL DEFAULT 'borrowed',
  `fine` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `borrows`
--

INSERT INTO `borrows` (`id`, `user_id`, `book_id`, `borrow_date`, `return_date`, `actual_return_date`, `status`, `fine`, `created_at`, `updated_at`) VALUES
(3, 3, 14, '2025-04-10', '2025-04-17', NULL, 'borrowed', 0.00, '2025-04-10 02:38:00', '2025-04-10 02:38:00'),
(4, 3776, 14, '2025-04-10', '2025-04-17', NULL, 'borrowed', 0.00, '2025-04-10 03:17:31', '2025-04-10 03:17:31'),
(5, 3776, 15, '2025-04-10', '2025-04-17', NULL, 'borrowed', 0.00, '2025-04-10 03:18:19', '2025-04-10 03:18:19'),
(6, 3, 14, '2025-04-10', '2025-04-17', NULL, 'borrowed', 0.00, '2025-04-10 12:40:44', '2025-04-10 12:40:44'),
(7, 3, 42, '2025-04-10', '2025-04-17', NULL, 'borrowed', 0.00, '2025-04-10 12:42:12', '2025-04-10 12:42:12');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'Fiksi', '2025-03-14 07:23:10', '2025-03-14 07:23:10'),
(2, 'Non-Fiksi', '2025-03-14 07:23:10', '2025-03-14 07:23:10'),
(3, 'Pendidikan', '2025-03-14 07:23:10', '2025-03-14 07:23:10'),
(4, 'Sejarah', '2025-03-14 07:23:10', '2025-03-14 07:23:10'),
(5, 'Sains', '2025-03-14 07:23:10', '2025-03-14 07:23:10'),
(6, 'Teknologi', '2025-03-14 07:23:10', '2025-03-14 07:23:10'),
(7, 'Sastra', '2025-03-14 07:23:10', '2025-03-14 07:23:10'),
(8, 'Agama', '2025-03-14 07:23:10', '2025-03-14 07:23:10'),
(9, 'Biografi', '2025-03-14 07:23:10', '2025-03-14 07:23:10'),
(10, 'Komik', '2025-03-14 07:23:10', '2025-03-14 07:23:10');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `location` varchar(255) NOT NULL,
  `max_participants` int DEFAULT NULL,
  `organizer_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_participants`
--

CREATE TABLE `event_participants` (
  `event_id` int NOT NULL,
  `user_id` int NOT NULL,
  `registration_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('registered','attended','cancelled') DEFAULT 'registered'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fines`
--

CREATE TABLE `fines` (
  `id` int NOT NULL,
  `peminjaman_id` int NOT NULL,
  `user_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','paid') DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `book_id` int NOT NULL,
  `tanggal_pinjam` date NOT NULL,
  `tanggal_kembali` date DEFAULT NULL,
  `status` enum('dipinjam','dikembalikan') DEFAULT 'dipinjam',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `perpanjangan_count` int DEFAULT '0',
  `extension_count` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `peminjaman`
--

INSERT INTO `peminjaman` (`id`, `user_id`, `book_id`, `tanggal_pinjam`, `tanggal_kembali`, `status`, `created_at`, `updated_at`, `perpanjangan_count`, `extension_count`) VALUES
(26, 3773, 3, '2025-03-19', '2025-03-26', 'dipinjam', '2025-03-19 08:47:21', '2025-03-19 08:47:21', 0, 0),
(27, 3773, 8, '2025-03-19', '2025-03-26', 'dipinjam', '2025-03-19 08:47:26', '2025-03-19 08:47:26', 0, 0),
(28, 3773, 10, '2025-03-19', '2025-03-26', 'dipinjam', '2025-03-19 08:47:30', '2025-03-19 08:47:30', 0, 0),
(29, 3773, 8, '2025-03-19', '2025-03-26', 'dipinjam', '2025-03-19 08:47:40', '2025-03-19 08:47:40', 0, 0),
(30, 3773, 8, '2025-03-19', '2025-03-26', 'dipinjam', '2025-03-19 08:47:44', '2025-03-19 08:47:44', 0, 0),
(31, 3773, 5, '2025-03-19', '2025-03-26', 'dipinjam', '2025-03-19 08:47:48', '2025-03-19 08:47:48', 0, 0),
(32, 3773, 5, '2025-03-19', '2025-03-26', 'dipinjam', '2025-03-19 08:47:54', '2025-03-19 08:47:54', 0, 0),
(33, 3773, 10, '2025-03-19', '2025-03-26', 'dipinjam', '2025-03-19 08:48:03', '2025-03-19 08:48:03', 0, 0),
(34, 3773, 10, '2025-03-19', '2025-03-26', 'dipinjam', '2025-03-19 08:48:09', '2025-03-19 08:48:09', 0, 0),
(35, 3773, 10, '2025-03-19', '2025-03-26', 'dipinjam', '2025-03-19 08:48:12', '2025-03-19 08:48:12', 0, 0),
(36, 3773, 10, '2025-03-19', '2025-03-26', 'dipinjam', '2025-03-19 08:48:16', '2025-03-19 08:48:16', 0, 0),
(37, 3773, 10, '2025-03-19', '2025-03-26', 'dipinjam', '2025-03-19 08:48:19', '2025-03-19 08:48:19', 0, 0),
(38, 2, 9, '2025-03-22', '2025-03-22', 'dikembalikan', '2025-03-22 04:23:01', '2025-03-22 08:31:47', 0, 0),
(39, 2, 3, '2025-03-22', '2025-03-29', 'dipinjam', '2025-03-22 09:03:21', '2025-03-22 09:03:21', 0, 0),
(40, 3, 12, '2025-03-28', '2025-03-29', 'dikembalikan', '2025-03-28 19:47:15', '2025-03-28 19:47:33', 0, 0),
(41, 3, 11, '2025-03-28', '2025-03-29', 'dikembalikan', '2025-03-28 19:48:47', '2025-03-28 19:48:58', 0, 0),
(42, 3774, 1, '2025-03-30', '2025-03-30', 'dikembalikan', '2025-03-30 03:39:56', '2025-03-30 03:41:06', 0, 0),
(43, 3, 13, '2025-03-30', '2025-03-31', 'dikembalikan', '2025-03-30 03:44:54', '2025-03-31 04:46:51', 0, 0),
(44, 3, 13, '2025-03-31', '2025-03-31', 'dikembalikan', '2025-03-31 04:46:39', '2025-03-31 06:43:03', 0, 0),
(45, 3, 14, '2025-03-31', '2025-03-31', 'dikembalikan', '2025-03-31 05:40:09', '2025-03-31 05:40:57', 0, 0),
(46, 3, 14, '2025-04-03', '2025-04-03', 'dikembalikan', '2025-04-03 01:50:00', '2025-04-03 02:21:15', 0, 0),
(47, 3, 13, '2025-04-03', '2025-04-04', 'dikembalikan', '2025-04-03 02:15:56', '2025-04-04 09:44:41', 0, 0),
(48, 3, 14, '2025-04-03', '2025-04-04', 'dikembalikan', '2025-04-03 02:29:50', '2025-04-04 09:45:57', 0, 0),
(49, 3, 14, '2025-04-04', '2025-04-04', 'dikembalikan', '2025-04-04 09:49:01', '2025-04-04 09:49:20', 0, 0),
(50, 3, 14, '2025-04-04', '2025-04-05', 'dikembalikan', '2025-04-04 09:54:00', '2025-04-05 04:45:00', 0, 2),
(51, 3, 14, '2025-04-05', '2025-04-05', 'dikembalikan', '2025-04-05 03:07:54', '2025-04-05 04:45:16', 0, 0),
(52, 3, 15, '2025-04-05', '2025-04-05', 'dikembalikan', '2025-04-05 04:25:07', '2025-04-05 04:45:09', 0, 0),
(53, 3, 15, '2025-04-05', '2025-04-05', 'dikembalikan', '2025-04-05 04:45:23', '2025-04-05 04:45:43', 0, 0),
(54, 3, 15, '2025-04-05', '2025-04-05', 'dikembalikan', '2025-04-05 04:54:07', '2025-04-05 12:34:29', 0, 0),
(55, 3, 18, '2025-04-05', '2025-04-05', 'dikembalikan', '2025-04-05 04:54:59', '2025-04-05 12:41:44', 0, 0),
(56, 3, 15, '2025-04-05', '2025-04-05', 'dikembalikan', '2025-04-05 12:42:18', '2025-04-05 12:42:35', 0, 0),
(57, 3, 19, '2025-04-08', '2025-04-08', 'dikembalikan', '2025-04-08 13:28:39', '2025-04-08 13:28:55', 0, 0),
(58, 3, 13, '2025-04-08', '2025-04-08', 'dikembalikan', '2025-04-08 13:46:27', '2025-04-08 13:46:41', 0, 0),
(59, 3, 13, '2025-04-08', '2025-04-08', 'dikembalikan', '2025-04-08 14:28:51', '2025-04-08 14:29:01', 0, 0),
(60, 3, 14, '2025-04-08', '2025-04-09', 'dikembalikan', '2025-04-08 23:16:14', '2025-04-09 02:31:26', 0, 0),
(61, 3, 15, '2025-04-09', '2025-04-09', 'dikembalikan', '2025-04-09 01:39:04', '2025-04-09 01:41:07', 0, 0),
(62, 3, 14, '2025-04-09', '2025-04-09', 'dikembalikan', '2025-04-09 02:58:30', '2025-04-09 02:58:49', 0, 0),
(63, 3, 15, '2025-04-09', '2025-04-09', 'dikembalikan', '2025-04-09 02:59:01', '2025-04-09 04:17:57', 0, 2),
(64, 2, 15, '2025-04-09', '2025-04-23', 'dipinjam', '2025-04-09 03:02:08', '2025-04-09 03:02:44', 0, 1),
(65, 3, 14, '2025-04-09', '2025-04-10', 'dikembalikan', '2025-04-09 04:11:01', '2025-04-09 23:10:55', 0, 0),
(66, 3, 14, '2025-04-09', '2025-04-10', 'dikembalikan', '2025-04-09 04:38:39', '2025-04-10 02:21:06', 0, 0);

--
-- Triggers `peminjaman`
--
DELIMITER $$
CREATE TRIGGER `calculate_fine` AFTER UPDATE ON `peminjaman` FOR EACH ROW BEGIN
    DECLARE days_overdue INT;
    DECLARE fine_amount DECIMAL(10,2);
    DECLARE fine_per_day DECIMAL(10,2) DEFAULT 1000.00; -- 1000 Rupiah per day
    
    IF NEW.status = 'dikembalikan' AND OLD.status = 'dipinjam' THEN
        -- Calculate days overdue
        SET days_overdue = DATEDIFF(NEW.tanggal_kembali, NEW.tanggal_pinjam) - 7; -- Assuming 7-day lending period
        
        IF days_overdue > 0 THEN
            -- Calculate fine amount
            SET fine_amount = days_overdue * fine_per_day;
            
            -- Insert into fines table
            INSERT INTO fines (peminjaman_id, user_id, amount, status, created_at)
            VALUES (NEW.id, NEW.user_id, fine_amount, 'pending', NOW());
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `maintain_book_stock` BEFORE INSERT ON `peminjaman` FOR EACH ROW BEGIN
    DECLARE current_stock INT;
    
    -- Get current stock
    SELECT stock INTO current_stock FROM books WHERE id = NEW.book_id;
    
    -- Check if stock is available
    IF current_stock <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot borrow book: out of stock';
    ELSE
        -- Update stock
        UPDATE books SET stock = stock - 1 WHERE id = NEW.book_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_author_stats` AFTER INSERT ON `peminjaman` FOR EACH ROW BEGIN
    DECLARE author_name VARCHAR(100);
    
    -- Get the author of the borrowed book
    SELECT author INTO author_name FROM books WHERE id = NEW.book_id;
    
    -- Update author statistics
    INSERT INTO author_statistics (author_name, books_count, total_borrows)
    VALUES (author_name, 
           (SELECT COUNT(*) FROM books WHERE author = author_name),
           1)
    ON DUPLICATE KEY UPDATE
        total_borrows = total_borrows + 1,
        popularity_score = (total_borrows + 1) / books_count;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_book_popularity` AFTER INSERT ON `peminjaman` FOR EACH ROW BEGIN
    -- Increase popularity score of borrowed book
    UPDATE books
    SET popularity_score = popularity_score + 1
    WHERE id = NEW.book_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `reading_lists`
--

CREATE TABLE `reading_lists` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `is_public` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reading_list_books`
--

CREATE TABLE `reading_list_books` (
  `reading_list_id` int NOT NULL,
  `book_id` int NOT NULL,
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `related_books`
-- (See below for the actual view)
--
CREATE TABLE `related_books` (
`book_id` int
,`book_title` varchar(255)
,`related_book_id` int
,`related_book_title` varchar(255)
,`shared_categories` bigint
);

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `book_id` int NOT NULL,
  `reservation_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','fulfilled','cancelled') DEFAULT 'pending',
  `expiry_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int NOT NULL,
  `book_id` int NOT NULL,
  `user_id` int NOT NULL,
  `rating` int NOT NULL,
  `comment` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `seasonal_recommendations`
--

CREATE TABLE `seasonal_recommendations` (
  `id` int NOT NULL,
  `season` enum('spring','summer','fall','winter','ramadan','new_year','vacation') NOT NULL,
  `category_id` int DEFAULT NULL,
  `theme` varchar(100) DEFAULT NULL,
  `active_from` date DEFAULT NULL,
  `active_to` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `fullname` varchar(100) NOT NULL DEFAULT 'Unknown',
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `profile_picture` varchar(255) DEFAULT NULL,
  `member_since` date DEFAULT (curdate()),
  `account_status` enum('active','suspended','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `username`, `email`, `password`, `role`, `created_at`, `updated_at`, `phone`, `address`, `profile_picture`, `member_since`, `account_status`) VALUES
(2, 'gilang', 'gilang', 'gilangaja@gmail.com', '$2y$10$jckDMvQKYbTY97EJ6AXT7eMBpVQwt7huFSLmmyiJ3jVgtZg64Kgzi', 'member', '2025-03-14 07:25:38', '2025-03-14 07:25:38', NULL, NULL, NULL, '2025-03-20', 'active'),
(3, 'satya berguna', 'anak admin', 'admin@gmail.com', '$2y$10$VIODdFjVUVC0.Hm/HnIR3umRRPFn1UOLfG6OmvxA/Oi73.aZ.Eda6', 'admin', '2025-03-17 06:13:57', '2025-04-09 03:17:05', NULL, NULL, NULL, '2025-03-20', 'active'),
(3773, 'wahyu ari ', 'wahyu', 'wahyugilangaditya27@gmail.com', '$2y$10$v8tMCdDghmvP5mmUJqq09e3FgMDrzwvkw88OCtXXSk6hwh8WkpaKm', 'member', '2025-03-19 08:45:21', '2025-03-19 08:45:21', NULL, NULL, NULL, '2025-03-20', 'active'),
(3774, 'jawa', 'jawa', 'jawa@gmail.com', '$2y$10$RJVQ76KxwWFPez7Tng52R.a4eKD3ieJniV8cor/28dsMeo.JnYr7e', 'member', '2025-03-30 03:25:05', '2025-03-30 03:29:01', NULL, NULL, NULL, '2025-03-30', 'active'),
(3776, 'Arroby Alan Nasafi', 'robz', 'robz@gmail.com', '$2y$10$L7su2APX7TSdMofTfVw3ROgl6niRxMV0/ibkKgidTWHG.42RR1.Oy', 'member', '2025-04-10 03:14:24', '2025-04-10 03:16:26', NULL, NULL, NULL, '2025-04-10', 'active');

-- --------------------------------------------------------

--
-- Stand-in structure for view `user_reading_stats`
-- (See below for the actual view)
--
CREATE TABLE `user_reading_stats` (
`days_as_reader` int
,`different_categories_read` bigint
,`favorite_category` varchar(50)
,`fullname` varchar(100)
,`total_books_borrowed` bigint
,`user_id` int
);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `author_statistics`
--
ALTER TABLE `author_statistics`
  ADD PRIMARY KEY (`author_name`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `isbn` (`isbn`);

--
-- Indexes for table `book_categories`
--
ALTER TABLE `book_categories`
  ADD PRIMARY KEY (`book_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `book_recommendations`
--
ALTER TABLE `book_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`book_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `borrows`
--
ALTER TABLE `borrows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organizer_id` (`organizer_id`);

--
-- Indexes for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD PRIMARY KEY (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `fines`
--
ALTER TABLE `fines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `peminjaman_id` (`peminjaman_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `reading_lists`
--
ALTER TABLE `reading_lists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reading_list_books`
--
ALTER TABLE `reading_list_books`
  ADD PRIMARY KEY (`reading_list_id`,`book_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_id` (`book_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `seasonal_recommendations`
--
ALTER TABLE `seasonal_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `book_recommendations`
--
ALTER TABLE `book_recommendations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `borrows`
--
ALTER TABLE `borrows`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fines`
--
ALTER TABLE `fines`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `reading_lists`
--
ALTER TABLE `reading_lists`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seasonal_recommendations`
--
ALTER TABLE `seasonal_recommendations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3777;

-- --------------------------------------------------------

--
-- Structure for view `book_loan_history`
--
DROP TABLE IF EXISTS `book_loan_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `book_loan_history`  AS SELECT `b`.`id` AS `book_id`, `b`.`title` AS `title`, `b`.`author` AS `author`, count(`p`.`id`) AS `times_borrowed`, avg((to_days(coalesce(`p`.`tanggal_kembali`,curdate())) - to_days(`p`.`tanggal_pinjam`))) AS `avg_loan_duration`, max(`p`.`tanggal_pinjam`) AS `last_borrowed_date`, (select `u`.`fullname` from `users` `u` where (`u`.`id` = (select `p2`.`user_id` from `peminjaman` `p2` where (`p2`.`book_id` = `b`.`id`) order by `p2`.`tanggal_pinjam` desc limit 1))) AS `last_borrower`, (case when (`b`.`stock` > 0) then 'Available' else 'All copies borrowed' end) AS `current_status` FROM (`books` `b` left join `peminjaman` `p` on((`b`.`id` = `p`.`book_id`))) GROUP BY `b`.`id`, `b`.`title`, `b`.`author` ORDER BY `times_borrowed` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `related_books`
--
DROP TABLE IF EXISTS `related_books`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `related_books`  AS SELECT `b1`.`id` AS `book_id`, `b1`.`title` AS `book_title`, `b2`.`id` AS `related_book_id`, `b2`.`title` AS `related_book_title`, count(distinct `bc1`.`category_id`) AS `shared_categories` FROM (((`books` `b1` join `book_categories` `bc1` on((`b1`.`id` = `bc1`.`book_id`))) join `book_categories` `bc2` on((`bc1`.`category_id` = `bc2`.`category_id`))) join `books` `b2` on((`bc2`.`book_id` = `b2`.`id`))) WHERE (`b1`.`id` <> `b2`.`id`) GROUP BY `b1`.`id`, `b1`.`title`, `b2`.`id`, `b2`.`title` HAVING (count(distinct `bc1`.`category_id`) > 0) ORDER BY `b1`.`id` ASC, `shared_categories` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `user_reading_stats`
--
DROP TABLE IF EXISTS `user_reading_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_reading_stats`  AS SELECT `u`.`id` AS `user_id`, `u`.`fullname` AS `fullname`, count(`p`.`id`) AS `total_books_borrowed`, (select count(distinct `bc`.`category_id`) from ((`peminjaman` `p2` join `books` `b` on((`p2`.`book_id` = `b`.`id`))) join `book_categories` `bc` on((`b`.`id` = `bc`.`book_id`))) where (`p2`.`user_id` = `u`.`id`)) AS `different_categories_read`, (select `c`.`name` from (((`peminjaman` `p3` join `books` `b2` on((`p3`.`book_id` = `b2`.`id`))) join `book_categories` `bc2` on((`b2`.`id` = `bc2`.`book_id`))) join `categories` `c` on((`bc2`.`category_id` = `c`.`id`))) where (`p3`.`user_id` = `u`.`id`) group by `c`.`id` order by count(0) desc limit 1) AS `favorite_category`, (to_days(curdate()) - to_days(min(`p`.`tanggal_pinjam`))) AS `days_as_reader` FROM (`users` `u` left join `peminjaman` `p` on((`u`.`id` = `p`.`user_id`))) GROUP BY `u`.`id`, `u`.`fullname` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `book_categories`
--
ALTER TABLE `book_categories`
  ADD CONSTRAINT `book_categories_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `book_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `book_recommendations`
--
ALTER TABLE `book_recommendations`
  ADD CONSTRAINT `book_recommendations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `book_recommendations_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `borrows`
--
ALTER TABLE `borrows`
  ADD CONSTRAINT `borrows_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `borrows_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD CONSTRAINT `event_participants_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fines`
--
ALTER TABLE `fines`
  ADD CONSTRAINT `fines_ibfk_1` FOREIGN KEY (`peminjaman_id`) REFERENCES `peminjaman` (`id`),
  ADD CONSTRAINT `fines_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `peminjaman_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`);

--
-- Constraints for table `reading_lists`
--
ALTER TABLE `reading_lists`
  ADD CONSTRAINT `reading_lists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `reading_list_books`
--
ALTER TABLE `reading_list_books`
  ADD CONSTRAINT `reading_list_books_ibfk_1` FOREIGN KEY (`reading_list_id`) REFERENCES `reading_lists` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reading_list_books_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seasonal_recommendations`
--
ALTER TABLE `seasonal_recommendations`
  ADD CONSTRAINT `seasonal_recommendations_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
