-- ═══════════════════════════════════════════════════════════════════════════
-- COINOR CHRONICLES — COMPLETE DATABASE SCHEMA
-- Run once on your Hostinger MySQL database.
-- Database: coinor_chronicles
-- Charset:  utf8mb4 / utf8mb4_unicode_ci
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ── Chronicles (AI-generated episodes) ───────────────────────────────────────
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

-- ── Subscribers ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS subscribers (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(320) NOT NULL,
  subscribed_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email),
  KEY idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Price snapshots (Heaven/Gate tracking) ────────────────────────────────────
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

-- ── Character status (live Heaven/Gate per coin) ──────────────────────────────
CREATE TABLE IF NOT EXISTS character_status (
  id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  coin_ticker    VARCHAR(20)     NOT NULL,
  character_name VARCHAR(100)    NOT NULL,
  clan           VARCHAR(50)     NOT NULL,
  gate_number    SMALLINT        NOT NULL DEFAULT 1,
  heaven_number  TINYINT         NOT NULL DEFAULT 1,
  current_price  DECIMAL(30, 10) NOT NULL DEFAULT 0,
  last_updated   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ticker (coin_ticker)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed character_status ─────────────────────────────────────────────────────
INSERT IGNORE INTO character_status (coin_ticker, character_name, clan, gate_number, heaven_number) VALUES
  ('BTC',   'Aragorn',      'ancients',   1, 1),
  ('ETH',   'Gandalf',      'ancients',   1, 1),
  ('LTC',   'Samwise',      'ancients',   1, 1),
  ('BNB',   'Elrond',       'ancients',   1, 1),
  ('XRP',   'Boromir',      'ancients',   1, 1),
  ('SOL',   'Legolas',      'swift',      1, 1),
  ('TON',   'Eomer',        'swift',      1, 1),
  ('AVAX',  'Eowyn',        'swift',      1, 1),
  ('XLM',   'Faramir',      'swift',      1, 1),
  ('TRX',   'Saruman',      'swift',      1, 1),
  ('PEPE',  'Tom Bombadil', 'meme_lords', 1, 1),
  ('SHIB',  'Merry',        'meme_lords', 1, 1),
  ('DOGE',  'Pippin',       'meme_lords', 1, 1),
  ('FLOKI', 'Theoden',      'meme_lords', 1, 1),
  ('NOT',   'Frodo',        'meme_lords', 1, 1),
  ('BOME',  'Bilbo',        'meme_lords', 1, 1),
  ('BONK',  'Gollum',       'meme_lords', 1, 1),
  ('ADA',   'Treebeard',    'builders',   1, 1),
  ('SUI',   'Gimli',        'builders',   1, 1),
  ('JASMY', 'Galadriel',    'builders',   1, 1),
  ('UNI',   'Denethor',     'builders',   1, 1);

-- ── Story memory (per-clan context for next episode) ─────────────────────────
CREATE TABLE IF NOT EXISTS story_memory (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  clan            VARCHAR(50)  NOT NULL,
  last_summary    TEXT         NOT NULL DEFAULT '',
  story_direction TEXT         NOT NULL DEFAULT '',
  last_updated    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clan (clan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO story_memory (clan, last_summary, story_direction) VALUES
  ('ancients',   'The chronicle begins.', ''),
  ('swift',      'The chronicle begins.', ''),
  ('meme_lords', 'The chronicle begins.', ''),
  ('builders',   'The chronicle begins.', '');

-- ── Clan rotation log ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS clan_rotation (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  clan     VARCHAR(50)  NOT NULL,
  story_id INT UNSIGNED NULL,
  ran_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clan_ran (clan, ran_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Milestones (Gate / Heaven crossings) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS milestones (
  id                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  coin_ticker       VARCHAR(20)     NOT NULL,
  character_name    VARCHAR(100)    NOT NULL,
  milestone_type    ENUM('gate','heaven') NOT NULL,
  milestone_value   SMALLINT        NOT NULL,
  price_at_crossing DECIMAL(30, 10) NOT NULL,
  crossed_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_crossing (coin_ticker, milestone_type, milestone_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Creator control (override next episode direction) ─────────────────────────
CREATE TABLE IF NOT EXISTS creator_control (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  target_clan   VARCHAR(50)  NULL COMMENT 'NULL = any clan',
  override_text TEXT         NOT NULL,
  priority      TINYINT      NOT NULL DEFAULT 5,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used_at       DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Site settings (key/value store) ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_settings (
  setting_key   VARCHAR(100) NOT NULL,
  setting_value TEXT         NOT NULL,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
  ('total_story_count', '0'),
  ('last_run', '');

-- ── Pipeline logs ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pipeline_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  clan        VARCHAR(50)  NULL,
  status      ENUM('success','failed','skipped') NOT NULL,
  story_id    INT UNSIGNED NULL,
  error_msg   TEXT         NULL,
  duration_ms INT          NOT NULL DEFAULT 0,
  ran_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ran_at (ran_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
