
--
-- Table structure for table `mailqueue`
--

CREATE TABLE IF NOT EXISTS /*TWIST_DATABASE_TABLE_PREFIX*/`mailqueue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('new','processing','sent','delete','failed','restricted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `error` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `send_attempts` int(11) NOT NULL DEFAULT 0,
  `started` datetime DEFAULT NULL,
  `added` datetime NOT NULL,
  `sent` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1 ;