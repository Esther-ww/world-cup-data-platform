-- ============================================================================
-- 成员 C 独立演示库（SQLite）
-- 仅用于本地演示和自动化测试，不导入正式 MySQL 库。
-- users / competitions / matches 是占位表：
--   * users 联调时替换为成员 A 的账号模块；
--   * competitions / matches 联调时替换为成员 B 的赛事模块。
-- 正式表结构见 data/install.sql。
-- ============================================================================

PRAGMA foreign_keys = ON;

CREATE TABLE users (
  id            INTEGER PRIMARY KEY,
  username      VARCHAR(48) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(8) NOT NULL DEFAULT 'user'
);

CREATE TABLE competitions (
  id         INTEGER PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL
);

CREATE TABLE matches (
  id             INTEGER PRIMARY KEY,
  competition_id INTEGER NOT NULL REFERENCES competitions(id) ON DELETE CASCADE,
  stage          VARCHAR(40) NOT NULL,
  kickoff_utc    VARCHAR(19) NOT NULL,
  home_team_id   INTEGER NOT NULL REFERENCES teams(id) ON DELETE RESTRICT,
  away_team_id   INTEGER NOT NULL REFERENCES teams(id) ON DELETE RESTRICT,
  home_score     INTEGER NULL,
  away_score     INTEGER NULL,
  status         VARCHAR(16) NOT NULL,
  venue          VARCHAR(120) NOT NULL DEFAULT '',
  CHECK (home_team_id <> away_team_id)
);

CREATE TABLE teams (
  id           INTEGER PRIMARY KEY,
  name         VARCHAR(80) NOT NULL,
  name_en      VARCHAR(80) NOT NULL DEFAULT '',
  country_code CHAR(3) NOT NULL UNIQUE
);

CREATE TABLE players (
  id          INTEGER PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  name_en     VARCHAR(100) NOT NULL DEFAULT '',
  birth_date  DATE NULL,
  nationality VARCHAR(60) NOT NULL,
  position    VARCHAR(2) NOT NULL CHECK (position IN ('GK','DF','MF','FW'))
);

CREATE INDEX idx_players_name ON players(name);
CREATE INDEX idx_players_position ON players(position);

CREATE TABLE squads (
  competition_id INTEGER NOT NULL REFERENCES competitions(id) ON DELETE CASCADE,
  team_id        INTEGER NOT NULL REFERENCES teams(id) ON DELETE RESTRICT,
  player_id      INTEGER NOT NULL REFERENCES players(id) ON DELETE RESTRICT,
  shirt_number   INTEGER NOT NULL CHECK (shirt_number BETWEEN 1 AND 99),
  PRIMARY KEY (competition_id, team_id, player_id),
  UNIQUE (competition_id, team_id, shirt_number),
  UNIQUE (competition_id, player_id)
);

CREATE TABLE lineups (
  id         INTEGER PRIMARY KEY,
  match_id   INTEGER NOT NULL REFERENCES matches(id) ON DELETE CASCADE,
  team_id    INTEGER NOT NULL REFERENCES teams(id) ON DELETE RESTRICT,
  formation  VARCHAR(16) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (match_id, team_id)
);

CREATE TABLE lineup_players (
  lineup_id  INTEGER NOT NULL REFERENCES lineups(id) ON DELETE CASCADE,
  player_id  INTEGER NOT NULL REFERENCES players(id) ON DELETE RESTRICT,
  is_starter INTEGER NOT NULL DEFAULT 1 CHECK (is_starter IN (0,1)),
  position   VARCHAR(2) NOT NULL CHECK (position IN ('GK','DF','MF','FW')),
  x          INTEGER NULL CHECK (x BETWEEN 0 AND 100),
  y          INTEGER NULL CHECK (y BETWEEN 0 AND 100),
  PRIMARY KEY (lineup_id, player_id)
);
