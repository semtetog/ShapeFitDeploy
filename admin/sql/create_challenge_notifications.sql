-- Tabela de notificações de desafios
CREATE TABLE IF NOT EXISTS `sf_challenge_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `challenge_group_id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_challenge_notifications_user` (`user_id`),
  KEY `idx_challenge_notifications_group` (`challenge_group_id`),
  KEY `idx_challenge_notifications_read` (`is_read`),
  KEY `idx_challenge_notifications_created` (`created_at`),
  CONSTRAINT `fk_challenge_notifications_group` FOREIGN KEY (`challenge_group_id`) REFERENCES `sf_challenge_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_challenge_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `sf_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para armazenar última posição conhecida do usuário (para detectar mudanças)
CREATE TABLE IF NOT EXISTS `sf_challenge_user_rank_snapshot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `challenge_group_id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `last_rank` int(11) DEFAULT NULL,
  `last_points` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_challenge_user_snapshot` (`challenge_group_id`, `user_id`),
  KEY `idx_challenge_rank_snapshot_group` (`challenge_group_id`),
  KEY `idx_challenge_rank_snapshot_user` (`user_id`),
  CONSTRAINT `fk_challenge_rank_snapshot_group` FOREIGN KEY (`challenge_group_id`) REFERENCES `sf_challenge_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_challenge_rank_snapshot_user` FOREIGN KEY (`user_id`) REFERENCES `sf_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


