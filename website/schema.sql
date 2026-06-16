-- ═══════════════════════════════════════════════════════════
-- COINOR CHRONICLES — DATABASE SCHEMA
-- Run once on your Hostinger MySQL database.
-- Database: coinor_chronicles
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Chronicles (AI-generated stories)
CREATE TABLE IF NOT EXISTS chronicles (
  id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  episode_number INT UNSIGNED    NOT NULL,
  title          VARCHAR(500)    NOT NULL,
  excerpt        TEXT            NOT NULL,
  body           LONGTEXT        NOT NULL,
  season         ENUM('golden','dark','flat') NOT NULL DEFAULT 'golden',
  clan           VARCHAR(100)    NOT NULL DEFAULT 'The Fellowship',
  character_name VARCHAR(100)    NOT NULL DEFAULT 'Aragorn',
  slug           VARCHAR(200)    NOT NULL,
  published      TINYINT(1)      NOT NULL DEFAULT 0,
  published_at   DATETIME        NULL,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slug (slug),
  UNIQUE KEY uq_episode (episode_number),
  KEY idx_published_at (published, published_at),
  KEY idx_season (season)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subscribers (email list)
CREATE TABLE IF NOT EXISTS subscribers (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(320) NOT NULL,
  subscribed_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email),
  KEY idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Price snapshots (for tracking Heaven/Gate positions)
CREATE TABLE IF NOT EXISTS price_snapshots (
  id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  coin_id      VARCHAR(50)     NOT NULL,
  symbol       VARCHAR(20)     NOT NULL,
  price_usd    DECIMAL(30, 10) NOT NULL,
  change_24h   DECIMAL(10, 4)  NULL,
  heaven_num   TINYINT         NULL,
  gate_num     TINYINT         NULL,
  snapped_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_coin_time (coin_id, snapped_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
