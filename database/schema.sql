CREATE TABLE IF NOT EXISTS `{prefix}settings` (
  `key` varchar(100) NOT NULL,
  `value` longtext NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(30) NOT NULL,
  `email` varchar(190) NOT NULL,
  `display_name` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `api_token_hash` char(64) NULL,
  `role` varchar(20) NOT NULL DEFAULT 'MEMBER',
  `bio` text NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `{prefix}users_username_unique` (`username`),
  UNIQUE KEY `{prefix}users_email_unique` (`email`),
  KEY `{prefix}users_api_token_hash_index` (`api_token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(90) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` text NULL,
  `color` varchar(20) NOT NULL DEFAULT '#2563eb',
  `sort_order` int NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `{prefix}categories_slug_unique` (`slug`),
  KEY `{prefix}categories_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}threads` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `title` varchar(180) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `content` longtext NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'PUBLISHED',
  `pinned` tinyint(1) NOT NULL DEFAULT 0,
  `views` int unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_activity_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `{prefix}threads_slug_unique` (`slug`),
  KEY `{prefix}threads_category_index` (`category_id`),
  KEY `{prefix}threads_user_index` (`user_id`),
  KEY `{prefix}threads_feed_index` (`status`, `pinned`, `last_activity_at`),
  CONSTRAINT `{prefix}threads_category_fk` FOREIGN KEY (`category_id`) REFERENCES `{prefix}categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `{prefix}threads_user_fk` FOREIGN KEY (`user_id`) REFERENCES `{prefix}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}posts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `content` longtext NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'VISIBLE',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `{prefix}posts_thread_index` (`thread_id`, `created_at`),
  KEY `{prefix}posts_user_index` (`user_id`),
  CONSTRAINT `{prefix}posts_thread_fk` FOREIGN KEY (`thread_id`) REFERENCES `{prefix}threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `{prefix}posts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `{prefix}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

