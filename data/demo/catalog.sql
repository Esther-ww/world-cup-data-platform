CREATE TABLE teams(id INTEGER PRIMARY KEY,name TEXT NOT NULL);
CREATE TABLE matches(id INTEGER PRIMARY KEY,name TEXT NOT NULL);
INSERT INTO teams VALUES (1,'示例蓝队'),(2,'示例白队');
INSERT INTO matches VALUES (1,'示例蓝队 vs 示例白队');
CREATE VIEW a_team_catalog AS SELECT id,name AS title FROM teams;
CREATE VIEW a_match_catalog AS SELECT id,name AS title FROM matches;
