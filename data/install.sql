-- ============================================================================
-- 世界杯数据平台 · 成员 C 负责的数据表（MySQL 8，utf8mb4）
-- 覆盖：球队 teams、球员 players、届次名单 squads、阵容 lineups、阵容球员 lineup_players
--
-- 依赖成员 B 的表：
--   competitions(id INT UNSIGNED)
--   matches(id INT UNSIGNED)
-- 集成时由 B 汇编完整的 data/install.sql；本文件不能脱离 B 的表单独建库。
-- 独立演示请使用 data/demo/schema.sql（SQLite 占位版本）。
--
-- 约束说明：
--   * 同一届次中，一名球员只能属于一支球队：UNIQUE(competition_id, player_id)
--   * 同一届次同一球队内，球衣号码唯一：UNIQUE(competition_id, team_id, shirt_number)
--   * 同一场比赛同一球队只有一份阵容：UNIQUE(match_id, team_id)
--   * “首发 11 人、每人只出现一次、球员属于该队该届名单”由应用层校验。
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE teams (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(80)  NOT NULL,
  name_en      VARCHAR(80)  NOT NULL DEFAULT '',
  country_code CHAR(3)      NOT NULL,
  UNIQUE KEY uq_teams_code (country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE players (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  name_en     VARCHAR(100) NOT NULL DEFAULT '',
  birth_date  DATE         NULL,
  nationality VARCHAR(60)  NOT NULL,
  position    ENUM('GK','DF','MF','FW') NOT NULL,
  KEY idx_players_name (name),
  KEY idx_players_position (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE squads (
  competition_id INT UNSIGNED NOT NULL,
  team_id        INT UNSIGNED NOT NULL,
  player_id      INT UNSIGNED NOT NULL,
  shirt_number   TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (competition_id, team_id, player_id),
  UNIQUE KEY uq_squads_number (competition_id, team_id, shirt_number),
  UNIQUE KEY uq_squads_player (competition_id, player_id),
  CONSTRAINT fk_squads_competition FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
  CONSTRAINT fk_squads_team        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE RESTRICT,
  CONSTRAINT fk_squads_player      FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE RESTRICT,
  CONSTRAINT chk_squads_number CHECK (shirt_number BETWEEN 1 AND 99)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lineups (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id   INT UNSIGNED NOT NULL,
  team_id    INT UNSIGNED NOT NULL,
  formation  VARCHAR(16)  NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_lineups_match_team (match_id, team_id),
  CONSTRAINT fk_lineups_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_lineups_team  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lineup_players (
  lineup_id  INT UNSIGNED NOT NULL,
  player_id  INT UNSIGNED NOT NULL,
  is_starter TINYINT(1)   NOT NULL DEFAULT 1,
  position   ENUM('GK','DF','MF','FW') NOT NULL,
  x          TINYINT UNSIGNED NULL,
  y          TINYINT UNSIGNED NULL,
  PRIMARY KEY (lineup_id, player_id),
  KEY idx_lineup_players_player (player_id),
  CONSTRAINT fk_lp_lineup FOREIGN KEY (lineup_id) REFERENCES lineups(id) ON DELETE CASCADE,
  CONSTRAINT fk_lp_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE RESTRICT,
  CONSTRAINT chk_lp_starter CHECK (is_starter IN (0,1)),
  CONSTRAINT chk_lp_coord CHECK ((x IS NULL AND y IS NULL) OR (x BETWEEN 0 AND 100 AND y BETWEEN 0 AND 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
