-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 04, 2026 at 11:51 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `shoe_store`
--

-- --------------------------------------------------------

--
-- Table structure for table `banned_users`
--

CREATE TABLE `banned_users` (
  `id` int(11) NOT NULL,
  `login` varchar(100) NOT NULL,
  `banned_until` timestamp NULL DEFAULT NULL,
  `banned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `banned_by` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `ban_type` enum('login','captcha') DEFAULT 'login'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(3, 'Демисезонная обув'),
(1, 'Женская обувь'),
(2, 'Мужская обувь');

-- --------------------------------------------------------

--
-- Table structure for table `failed_captcha_attempts`
--

CREATE TABLE `failed_captcha_attempts` (
  `id` int(11) NOT NULL,
  `login` varchar(100) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_logins`
--

CREATE TABLE `failed_logins` (
  `id` int(11) NOT NULL,
  `login` varchar(100) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manufacturers`
--

CREATE TABLE `manufacturers` (
  `manufacturer_id` int(11) NOT NULL,
  `manufacturer_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `manufacturers`
--

INSERT INTO `manufacturers` (`manufacturer_id`, `manufacturer_name`) VALUES
(5, 'Alessio Nesca'),
(10, 'ARGO'),
(7, 'Caprice'),
(6, 'CROSBY'),
(11, 'FRAU'),
(1, 'Kari'),
(8, 'Luiza Belly'),
(2, 'Marco Tozzi'),
(4, 'Rieker'),
(9, 'TOFA'),
(3, 'Рос');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pickup_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `delivery_date` date NOT NULL,
  `pickup_code` int(11) NOT NULL,
  `status` enum('Новый','Завершен') NOT NULL DEFAULT 'Новый'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `pickup_id`, `order_date`, `delivery_date`, `pickup_code`, `status`) VALUES
(1, 4, 1, '2025-03-06', '2025-04-27', 901, 'Завершен'),
(4, 3, 11, '2025-02-27', '2025-04-30', 904, 'Завершен'),
(5, 4, 2, '2025-03-24', '2025-05-01', 905, 'Завершен'),
(8, 3, 19, '2025-04-07', '2025-05-04', 908, 'Новый'),
(9, 4, 5, '2025-04-09', '2025-05-05', 909, 'Новый'),
(10, 4, 19, '2025-04-10', '2025-05-06', 910, 'Новый');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `article` varchar(10) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`item_id`, `order_id`, `article`, `quantity`) VALUES
(1, 1, 'А112Т4', 2),
(2, 1, 'F635R4', 2),
(7, 4, 'F572H7', 5),
(8, 4, 'D329H3', 4),
(9, 5, 'А112Т4', 2),
(10, 5, 'F635R4', 2),
(15, 8, 'F572H7', 5),
(16, 8, 'D329H3', 4),
(17, 9, 'B320R5', 5),
(18, 9, 'G432E4', 1),
(19, 10, 'S213E3', 5),
(20, 10, 'E482R4', 5);

-- --------------------------------------------------------

--
-- Table structure for table `pickup_points`
--

CREATE TABLE `pickup_points` (
  `pickup_id` int(11) NOT NULL,
  `address` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pickup_points`
--

INSERT INTO `pickup_points` (`pickup_id`, `address`) VALUES
(1, '420151, г. Лесной, ул. Вишневая, 32'),
(2, '125061, г. Лесной, ул. Подгорная, 8'),
(3, '630370, г. Лесной, ул. Шоссейная, 24'),
(4, '400562, г. Лесной, ул. Зеленая, 32'),
(5, '614510, г. Лесной, ул. Маяковского, 47'),
(6, '410542, г. Лесной, ул. Светлая, 46'),
(7, '620839, г. Лесной, ул. Цветочная, 8'),
(8, '443890, г. Лесной, ул. Коммунистическая, 1'),
(9, '603379, г. Лесной, ул. Спортивная, 46'),
(10, '603721, г. Лесной, ул. Гоголя, 41'),
(11, '410172, г. Лесной, ул. Северная, 13'),
(12, '614611, г. Лесной, ул. Молодежная, 50'),
(13, '454311, г. Лесной, ул. Новая, 19'),
(14, '660007, г. Лесной, ул. Октябрьская, 19'),
(15, '603036, г. Лесной, ул. Садовая, 4'),
(16, '394060, г. Лесной, ул. Фрунзе, 43'),
(17, '410661, г. Лесной, ул. Школьная, 50'),
(18, '625590, г. Лесной, ул. Коммунистическая, 20'),
(19, '625683, г. Лесной, ул. 8 Марта'),
(20, '450983, г. Лесной, ул. Комсомольская, 26'),
(21, '394782, г. Лесной, ул. Чехова, 3'),
(22, '603002, г. Лесной, ул. Дзержинского, 28'),
(23, '450558, г. Лесной, ул. Набережная, 30'),
(24, '344288, г. Лесной, ул. Чехова, 1'),
(25, '614164, г. Лесной, ул. Степная, 30'),
(26, '394242, г. Лесной, ул. Коммунистическая, 43'),
(27, '660540, г. Лесной, ул. Солнечная, 25'),
(28, '125837, г. Лесной, ул. Шоссейная, 40'),
(29, '125703, г. Лесной, ул. Партизанская, 49'),
(30, '625283, г. Лесной, ул. Победы, 46'),
(31, '614753, г. Лесной, ул. Полевая, 35'),
(32, '426030, г. Лесной, ул. Маяковского, 44'),
(33, '450375, г. Лесной, ул. Клубная, 44'),
(34, '625560, г. Лесной, ул. Некрасова, 12'),
(35, '630201, г. Лесной, ул. Комсомольская, 17'),
(36, '190949, г. Лесной, ул. Мичурина, 26');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `article` varchar(10) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `manufacturer_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `unit` varchar(10) NOT NULL DEFAULT 'шт.',
  `price` decimal(10,2) NOT NULL,
  `discount` int(11) NOT NULL DEFAULT 0,
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`article`, `supplier_id`, `manufacturer_id`, `category_id`, `name`, `unit`, `price`, `discount`, `stock_qty`, `description`, `photo`) VALUES
('B320R5', 1, 4, 1, 'Туфли', 'шт.', 4300.00, 2, 6, 'Туфли Rieker женские демисезонные, размер 41, цвет коричневый', '9.jpg'),
('B431R5', 2, 4, 2, 'Ботинки', 'шт.', 2700.00, 2, 5, 'Мужские кожаные ботинки/мужские ботинки', NULL),
('C436G5', 1, 5, 1, 'Ботинки', 'шт.', 10200.00, 15, 9, 'Ботинки женские, ARGO, размер 40', NULL),
('D268G5', 2, 4, 1, 'Туфли', 'шт.', 4399.00, 3, 12, 'Туфли Rieker женские демисезонные, размер 36, цвет коричневый', NULL),
('D329H3', 2, 5, 1, 'Полуботинки', 'шт.', 1890.00, 4, 4, 'Полуботинки Alessio Nesca женские 3-30797-47, размер 37, цвет: бордовый', '8.jpg'),
('D364R4', 1, 1, 1, 'Туфли', 'шт.', 12400.00, 16, 5, 'Туфли Luiza Belly женские Kate-lazo черные из натуральной замши', NULL),
('D572U8', 2, 3, 2, 'Кроссовки', 'шт.', 4100.00, 3, 6, '129615-4 Кроссовки мужские', '6.jpg'),
('E482R4', 1, 1, 1, 'Полуботинки', 'шт.', 1800.00, 2, 14, 'Полуботинки kari женские MYZ20S-149, размер 41, цвет: черный', NULL),
('F427R5', 2, 4, 1, 'Ботинки', 'шт.', 11800.00, 15, 11, 'Ботинки на молнии с декоративной пряжкой FRAU', NULL),
('F572H7', 1, 2, 1, 'Туфли', 'шт.', 2700.00, 2, 14, 'Туфли Marco Tozzi женские летние, размер 39, цвет черный', '7.jpg'),
('F635R4', 2, 2, 1, 'Ботинки', 'шт.', 3244.00, 2, 13, 'Ботинки Marco Tozzi женские демисезонные, размер 39, цвет бежевый', '2.jpg'),
('G432E4', 1, 1, 1, 'Туфли', 'шт.', 2800.00, 3, 15, 'Туфли kari женские TR-YR-413017, размер 37, цвет: черный', '10.jpg'),
('G531F4', 1, 1, 1, 'Ботинки', 'шт.', 6600.00, 12, 9, 'Ботинки женские зимние ROMER арт. 893167-01 Черный', NULL),
('G783F5', 1, 3, 2, 'Ботинки', 'шт.', 5900.00, 2, 8, 'Мужские ботинки Рос-Обувь кожаные с натуральным мехом', '4.jpg'),
('H535R5', 2, 4, 1, 'Ботинки', 'шт.', 2300.00, 2, 7, 'Женские Ботинки демисезонные', NULL),
('H782T5', 1, 1, 2, 'Туфли', 'шт.', 4499.00, 4, 5, 'Туфли kari мужские классика MYZ21AW-450A, размер 43, цвет: черный', '3.jpg'),
('J384T6', 2, 4, 2, 'Ботинки', 'шт.', 3800.00, 2, 16, 'B3430/14 Полуботинки мужские Rieker', '5.jpg'),
('J542F5', 1, 1, 2, 'Тапочки', 'шт.', 500.00, 13, 0, 'Тапочки мужские Арт.70701-55-67син р.41', NULL),
('K345R4', 2, 6, 2, 'Полуботинки', 'шт.', 2100.00, 2, 3, '407700/01-02 Полуботинки мужские CROSBY', NULL),
('K358H6', 1, 4, 2, 'Тапочки', 'шт.', 599.00, 20, 2, 'Тапочки мужские син р.41', NULL),
('L754R4', 1, 1, 1, 'Полуботинки', 'шт.', 1700.00, 2, 7, 'Полуботинки kari женские WB2020SS-26, размер 38, цвет: черный', NULL),
('M542T5', 2, 4, 2, 'Кроссовки', 'шт.', 2800.00, 18, 3, 'Кроссовки мужские TOFA', NULL),
('N457T5', 1, 6, 1, 'Полуботинки', 'шт.', 4600.00, 3, 13, 'Полуботинки Ботинки черные зимние, мех', NULL),
('O754F4', 2, 4, 1, 'Туфли', 'шт.', 5400.00, 4, 18, 'Туфли женские демисезонные Rieker артикул 55073-68/37', NULL),
('P764G4', 1, 6, 1, 'Туфли', 'шт.', 6800.00, 15, 15, 'Туфли женские, ARGO, размер 38', NULL),
('S213E3', 2, 6, 2, 'Полуботинки', 'шт.', 2156.00, 3, 6, '407700/01-01 Полуботинки мужские CROSBY', NULL),
('S326R5', 2, 6, 2, 'Тапочки', 'шт.', 9900.00, 17, 15, 'Мужские кожаные тапочки \"Профиль С.Дали\"', NULL),
('S634B5', 2, 6, 2, 'Кеды', 'шт.', 5500.00, 3, 0, 'Кеды Caprice мужские демисезонные, размер 42, цвет черный', NULL),
('T324F5', 1, 6, 1, 'Сапоги', 'шт.', 4699.00, 2, 5, 'Сапоги замша Цвет: синий', NULL),
('А112Т4', 1, 1, 1, 'Ботинки', 'шт.', 4990.00, 3, 6, 'Женские Ботинки демисезонные kari', '1.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(1, 'Администратор'),
(2, 'Менеджер'),
(3, 'Авторизированный клиент');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`) VALUES
(1, 'Kari'),
(2, 'Обувь для вас');

-- --------------------------------------------------------

--
-- Table structure for table `test_results`
--

CREATE TABLE `test_results` (
  `id` int(11) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `type_label` varchar(100) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `expected` varchar(255) DEFAULT NULL,
  `result` varchar(20) DEFAULT NULL,
  `error_msg` varchar(255) DEFAULT NULL,
  `checked_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_results`
--

INSERT INTO `test_results` (`id`, `type`, `type_label`, `value`, `expected`, `result`, `error_msg`, `checked_at`) VALUES
(1, 'fullName', 'Проверка ФИО', 'Никифоров Никифор Никифорович', 'Только кириллица, пробелы и дефисы', 'Успешно', NULL, '2026-06-04 15:39:21'),
(2, 'inn', 'Проверка ИНН', '7707083895', '10 или 12 цифр', 'Успешно', NULL, '2026-06-04 15:42:28'),
(3, 'inn', 'Проверка ИНН', '7707083893*', '10 или 12 цифр', 'Не успешно', 'ИНН должен содержать 10 или 12 цифр.', '2026-06-04 15:42:49'),
(4, 'fullName', 'Проверка ФИО', 'Лебедев Лебедь Лебедевич', 'Только кириллица, пробелы и дефисы', 'Успешно', NULL, '2026-06-04 15:52:39');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `login` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `full_name`, `login`, `password_hash`) VALUES
(3, 1, 'Одинцов Серафим Артёмович', 'yzls62@outlook.com', '$2y$10$t8fnu/fejh8zxVLaXdY9c.az3E9p9WBo.Nj6F7Tt0rYW0SfNKkUeW'),
(4, 2, 'Степанов Михаил Артёмович', '1diph5e@tutanota.com', '$2y$10$gD00LOWtbcXT0e4UJNKKvOqHVHhDSkjlLU1vzVm6/or2W.baNvyZq'),
(5, 2, 'Ворсин Петр Евгеньевич', 'tjde7c@yahoo.com', '$2y$10$BR0PFODw2RCGJovHX8H29eVX8p3xf.hvelbRVJi5U5v6I45rKUlbm'),
(6, 2, 'Старикова Елена Павловна', 'wpmrc3do@tutanota.com', '$2y$10$wUBu/vxD/gzt5zGKEhM4YuuKg9xsYK28T.UtT8csDl5LOa2w6TcKi'),
(7, 3, 'Михайлюк Анна Вячеславовна', '5d4zbu@tutanota.com', '$2y$10$AbCWPv2ZJK31nYxLj/cDYOrrgKLQXH5FOqW17VisP5JycuawLgUFi'),
(8, 3, 'Ситдикова Елена Анатольевна', 'ptec8ym@yahoo.com', '$2y$10$zUxvbeghqkcG1iL0DlB4QOomGlUf0/iaf5hmiMZVcRsMGqZ2idQfO'),
(9, 3, 'Ворсин Петр Евгеньевич', '1qz4kw@mail.com', '$2y$10$b1vly4un.aR2GgaEHIyBgehsVOQAINMtPlUJDc86wWd594J0msvx.'),
(10, 3, 'Старикова Елена Павловна', '4np6se@mail.com', '$2y$10$g.l741LTADfoy5gEAN.VoeLMCp4n2P/gvTBvPWQcK28gm96FheF.S'),
(11, 2, 'Максим Максим Максимович', 'plum', '$2y$10$5Kmjq1Xb.HVg2BK4/VJGpOypHuo67uSdS.AAz6Ge5q9j1SSTwFZlS'),
(13, 1, 'Никифорова Весения Николаевна', '94d5ous@gmail.com', '$2y$10$Iw.dAWNwxyyRf1pj4WTHEusEOmWc2o.e73oMzkuJxVstBrVCnG3jq'),
(14, 3, 'Клиент', 'client', '$2y$10$18BXJAseiFfklGSJrKGiq.vDz1pEQXNDMsaoAz7M1b5PsVmgtUlyi'),
(15, 1, 'Администратор', 'admin', '$2y$10$KYkWn4AlzTai.4pDvhRY9uJcWmmW5ZzXZ03ppOdHMvbxXu.5OE5BO'),
(20, NULL, 'Степанов Степан Степанович', '', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `banned_users`
--
ALTER TABLE `banned_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`),
  ADD KEY `idx_login` (`login`),
  ADD KEY `idx_banned_until` (`banned_until`),
  ADD KEY `idx_ban_type` (`ban_type`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uq_category_name` (`category_name`);

--
-- Indexes for table `failed_captcha_attempts`
--
ALTER TABLE `failed_captcha_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login` (`login`),
  ADD KEY `idx_attempt_time` (`attempt_time`);

--
-- Indexes for table `failed_logins`
--
ALTER TABLE `failed_logins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login` (`login`),
  ADD KEY `idx_attempt_time` (`attempt_time`);

--
-- Indexes for table `manufacturers`
--
ALTER TABLE `manufacturers`
  ADD PRIMARY KEY (`manufacturer_id`),
  ADD UNIQUE KEY `uq_manufacturer_name` (`manufacturer_name`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `fk_orders_pickup` (`pickup_id`),
  ADD KEY `fk_orders_user` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_items_product` (`article`),
  ADD KEY `fk_items_order` (`order_id`);

--
-- Indexes for table `pickup_points`
--
ALTER TABLE `pickup_points`
  ADD PRIMARY KEY (`pickup_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`article`),
  ADD KEY `fk_products_supplier` (`supplier_id`),
  ADD KEY `fk_products_manufacturer` (`manufacturer_id`),
  ADD KEY `fk_products_category` (`category_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`),
  ADD UNIQUE KEY `uq_supplier_name` (`supplier_name`);

--
-- Indexes for table `test_results`
--
ALTER TABLE `test_results`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_login` (`login`),
  ADD KEY `fk_users_roles` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `banned_users`
--
ALTER TABLE `banned_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `failed_captcha_attempts`
--
ALTER TABLE `failed_captcha_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `failed_logins`
--
ALTER TABLE `failed_logins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `manufacturers`
--
ALTER TABLE `manufacturers`
  MODIFY `manufacturer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `pickup_points`
--
ALTER TABLE `pickup_points`
  MODIFY `pickup_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `test_results`
--
ALTER TABLE `test_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_pickup` FOREIGN KEY (`pickup_id`) REFERENCES `pickup_points` (`pickup_id`),
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_items_product` FOREIGN KEY (`article`) REFERENCES `products` (`article`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `fk_products_manufacturer` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`manufacturer_id`),
  ADD CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
