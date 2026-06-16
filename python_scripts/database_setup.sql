-- ============================================================
-- CoinorChronicles · MySQL Database Schema v3.0
-- Run this in your Hostinger MySQL panel (phpMyAdmin)
-- ============================================================

-- Create and select the database
CREATE DATABASE IF NOT EXISTS coinor_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE coinor_db;

-- ─── STORIES TABLE ──────────────────────────────────────────────────────────
-- The Book of Meme — every story ever told
CREATE TABLE stories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    episode_number  INT NOT NULL,                           -- Global story count
    clan            ENUM('ancients', 'swift', 'meme_lords', 'builders') NOT NULL,
    clan_name       VARCHAR(100) NOT NULL,                  -- Display name
    territory       VARCHAR(100) NOT NULL,                  -- Clan territory
    title           VARCHAR(255) NOT NULL,                  -- Episode title (Coinor language)
    story_text      LONGTEXT NOT NULL,                      -- Full story content
    market_condition ENUM('golden_season', 'dark_siege', 'waiting_plains') NOT NULL,
    is_celebration  BOOLEAN DEFAULT FALSE,                  -- Milestone episode?
    is_donation     BOOLEAN DEFAULT FALSE,                  -- Every 20th story
    is_creator_override BOOLEAN DEFAULT FALSE,             -- Creator manually set direction
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    published_at    TIMESTAMP NULL,                         -- When it went live on site
    
    INDEX idx_clan (clan),
    INDEX idx_created (created_at),
    INDEX idx_episode (episode_number)
) ENGINE=InnoDB;

-- ─── CHARACTER STATUS TABLE ─────────────────────────────────────────────────
-- Current Heaven position for every character
CREATE TABLE character_status (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    coin_ticker     VARCHAR(10) NOT NULL UNIQUE,
    character_name  VARCHAR(100) NOT NULL,
    clan            VARCHAR(50) NOT NULL,
    current_price   DECIMAL(20, 8) NOT NULL DEFAULT 0,
    gate_number     INT NOT NULL DEFAULT 1,                 -- Which Gate they're pursuing
    heaven_number   INT NOT NULL DEFAULT 1,                 -- Which Heaven within that Gate
    gate1_price     DECIMAL(20, 8) NOT NULL,               -- Gate 1 price target
    gate2_price     DECIMAL(20, 8) NOT NULL,               -- Gate 2 price target
    gate_reached    BOOLEAN DEFAULT FALSE,                  -- Has Gate 1 been crossed?
    last_updated    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    notes           TEXT NULL,                              -- Creator notes on this character
    
    INDEX idx_coin (coin_ticker),
    INDEX idx_clan (clan)
) ENGINE=InnoDB;

-- Seed character data
INSERT INTO character_status (coin_ticker, character_name, clan, current_price, gate_number, heaven_number, gate1_price, gate2_price, notes) VALUES
('BTC',   'Aragorn',      'ancients',   0, 1, 1, 100000, 1000000,    'Reached Gate 1 — now reclaiming after market wars'),
('ETH',   'Gandalf',      'ancients',   0, 1, 1, 10000,  100000,     'The Grey to White transformation (The Merge) is lore'),
('LTC',   'Samwise',      'ancients',   0, 1, 1, 1000,   10000,      'Silver to BTC gold — the reliable one'),
('BNB',   'Elrond',       'ancients',   0, 1, 1, 10000,  100000,     'Oversees the Great Bazaar of Binance'),
('XRP',   'Boromir',      'ancients',   0, 1, 1, 100,    1000,       'Fighting the Eagle Warriors (SEC) — this is his defining arc'),
('SOL',   'Legolas',      'swift',      0, 1, 1, 1000,   10000,      'Fastest on the Electric Plains'),
('TON',   'Eomer',        'swift',      0, 1, 1, 10,     100,        'Leads the Riders of Telegram'),
('AVAX',  'Eowyn',        'swift',      0, 1, 1, 500,    5000,       'She proved she could slay the giants'),
('XLM',   'Faramir',      'swift',      0, 1, 1, 5,      50,         'Quiet, principled bridge builder'),
('TRX',   'Saruman',      'swift',      0, 1, 1, 1,      10,         'Walks apart from the fellowship — unaligned'),
('ADA',   'Treebeard',    'builders',   0, 1, 1, 10,     100,        'Do not be hasty. Peer-reviewed everything.'),
('LINK',  'Gimli',        'builders',   0, 1, 1, 1000,   10000,      'Dwarf-made. Compact and sturdy.'),
('UNI',   'Eowyn_2',      'builders',   0, 1, 1, 100,    1000,       'Update character assignment'),
('AAVE',  'Council_Elder','builders',   0, 1, 1, 10000,  100000,     'DeFi lending halls'),
('INJ',   'Forge_Master', 'builders',   0, 1, 1, 1000,   10000,      'The Forge commander'),
('JASMY', 'Galadriel',    'builders',   0, 1, 1, 1,      10,         'The Light — data sovereignty visionary'),
('PEPE',  'Tom_Bombadil', 'meme_lords', 0, 1, 1, 1,      10,         'He is Master. Outside all laws.'),
('SHIB',  'Merry',        'meme_lords', 0, 1, 1, 0.001,  0.01,       'The $1 dream. His defining quest.'),
('DOGE',  'Pippin',       'meme_lords', 0, 1, 1, 10,     100,        'The survivor. A fool of a Took who outlasted everyone.'),
('FLOKI', 'Theoden',      'meme_lords', 0, 1, 1, 1,      10,         'The revived king. Second rise is the legend.'),
('NOT',   'Frodo',        'meme_lords', 0, 1, 1, 0.1,    1,          'Carries the Nothing coin. Unlikely hero.'),
('BOME',  'Bilbo',        'meme_lords', 0, 1, 1, 0.1,    1,          'The Chronicler. His price = how widely the Book is read.'),
('BONK',  'Gollum',       'meme_lords', 0, 1, 1, 1,      10,         'Wanderer. Solana obsessed. No clan loyalty.');

-- ─── CLAN ROTATION LOG ──────────────────────────────────────────────────────
-- Tracks which clan ran last to manage rotation
CREATE TABLE clan_rotation (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    clan        VARCHAR(50) NOT NULL,
    ran_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    story_id    INT NULL,
    
    FOREIGN KEY (story_id) REFERENCES stories(id),
    INDEX idx_ran_at (ran_at)
) ENGINE=InnoDB;

-- ─── STORY MEMORY TABLE ─────────────────────────────────────────────────────
-- The "last episode summary" fed into next generation for each clan
CREATE TABLE story_memory (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    clan            VARCHAR(50) NOT NULL UNIQUE,
    last_summary    TEXT NOT NULL,                          -- What happened last episode
    story_direction TEXT NULL,                              -- Where the arc is heading
    last_updated    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed memory with starting state
INSERT INTO story_memory (clan, last_summary, story_direction) VALUES
('ancients',   'The chronicle begins. Aragorn speaks for the first time.', 'Aragorn reclaims his position after the wars.'),
('swift',      'The chronicle begins. The Electric Plains await.', 'Legolas leads the clan through volatile times.'),
('meme_lords', 'The chronicle begins. The Bazaar awakens.', 'Merry marches toward his $1 dream. The Bazaar endures.'),
('builders',   'The chronicle begins. The Forge fires are lit.', 'The Builders construct in silence.');

-- ─── CREATOR CONTROL TABLE ──────────────────────────────────────────────────
-- Creator Override input — Motunrayo types here, the system reads it
CREATE TABLE creator_control (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    override_text   TEXT NULL,                              -- Story direction to inject
    target_clan     VARCHAR(50) NULL,                      -- NULL = any clan
    is_active       BOOLEAN DEFAULT TRUE,                  -- Set to FALSE after use
    priority        ENUM('low', 'normal', 'high') DEFAULT 'normal',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at         TIMESTAMP NULL,                        -- When it was consumed
    notes           TEXT NULL
) ENGINE=InnoDB;

-- ─── MILESTONES TABLE ───────────────────────────────────────────────────────
-- Tracks all Gate and Heaven crossings
CREATE TABLE milestones (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    coin_ticker     VARCHAR(10) NOT NULL,
    character_name  VARCHAR(100) NOT NULL,
    milestone_type  ENUM('heaven', 'gate') NOT NULL,
    milestone_value INT NOT NULL,                          -- Heaven 3 = 3, Gate 1 = 1
    price_at_crossing DECIMAL(20, 8) NOT NULL,
    story_id        INT NULL,                              -- The celebration episode
    crossed_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (story_id) REFERENCES stories(id),
    INDEX idx_coin (coin_ticker),
    INDEX idx_crossed (crossed_at)
) ENGINE=InnoDB;

-- ─── SITE SETTINGS TABLE ────────────────────────────────────────────────────
CREATE TABLE site_settings (
    setting_key     VARCHAR(100) PRIMARY KEY,
    setting_value   TEXT NOT NULL,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed site settings
INSERT INTO site_settings (setting_key, setting_value) VALUES
('total_story_count',       '0'),
('pipeline_status',         'active'),
('last_run',                ''),
('next_donation_at',        '20'),
('site_launch_date',        NOW()),
('sam_reveal_active',       'false'),
('arwen_coin_revealed',     'false');

-- ─── PIPELINE LOGS ──────────────────────────────────────────────────────────
CREATE TABLE pipeline_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    run_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    clan        VARCHAR(50) NULL,
    status      ENUM('success', 'failed', 'review_fail', 'skipped') NOT NULL,
    story_id    INT NULL,
    error_msg   TEXT NULL,
    duration_ms INT NULL,                                  -- How long the run took
    
    INDEX idx_run_at (run_at),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ─── VERIFY SETUP ────────────────────────────────────────────────────────────
SELECT 'Database setup complete' AS status;
SELECT COUNT(*) AS characters_seeded FROM character_status;
SELECT COUNT(*) AS memories_seeded FROM story_memory;
