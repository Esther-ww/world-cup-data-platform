-- 成员 C | 独立演示用的占位数据：届次与比赛。
-- 正式整合时由成员 B 的 competitions / matches 表替换，不要把本文件导入正式库。
INSERT INTO competitions(id,name,start_date,end_date) VALUES(1,'2014 年巴西世界杯（演示子集）','2014-06-12','2014-07-13');
INSERT INTO matches(id,competition_id,stage,kickoff_utc,home_team_id,away_team_id,home_score,away_score,status,venue) VALUES(1,1,'小组赛 A','2014-06-12 20:00:00',1,2,3,1,'finished','Neo Química Arena, São Paulo');
INSERT INTO matches(id,competition_id,stage,kickoff_utc,home_team_id,away_team_id,home_score,away_score,status,venue) VALUES(2,1,'小组赛 A','2014-06-13 16:00:00',3,4,1,0,'finished','Estadio das Dunas, Natal');
INSERT INTO matches(id,competition_id,stage,kickoff_utc,home_team_id,away_team_id,home_score,away_score,status,venue) VALUES(3,1,'小组赛 A','2014-06-17 19:00:00',1,3,0,0,'finished','Estadio Castelao, Fortaleza');
INSERT INTO matches(id,competition_id,stage,kickoff_utc,home_team_id,away_team_id,home_score,away_score,status,venue) VALUES(4,1,'小组赛 A','2014-06-18 22:00:00',4,2,0,4,'finished','Arena Amazonia, Manaus');
INSERT INTO matches(id,competition_id,stage,kickoff_utc,home_team_id,away_team_id,home_score,away_score,status,venue) VALUES(5,1,'小组赛 A','2014-06-23 20:00:00',2,3,1,3,'finished','Arena Pernambuco, Recife');
INSERT INTO matches(id,competition_id,stage,kickoff_utc,home_team_id,away_team_id,home_score,away_score,status,venue) VALUES(6,1,'小组赛 A','2014-06-23 20:00:00',4,1,1,4,'finished','Estádio Nacional Mané Garrincha, Brasília');
INSERT INTO matches(id,competition_id,stage,kickoff_utc,home_team_id,away_team_id,home_score,away_score,status,venue) VALUES(7,1,'小组赛 B','2014-06-13 19:00:00',5,6,1,5,'finished','Fonte Nova Arena, Salvador');
INSERT INTO matches(id,competition_id,stage,kickoff_utc,home_team_id,away_team_id,home_score,away_score,status,venue) VALUES(8,1,'小组赛 B','2014-06-13 22:00:00',7,8,3,1,'finished','Arena Pantanal, Cuiabá');
INSERT INTO matches(id,competition_id,stage,kickoff_utc,home_team_id,away_team_id,home_score,away_score,status,venue) VALUES(9,1,'小组赛 B','2014-06-18 16:00:00',8,6,2,3,'finished','Estadio Beira-Rio, Porto Alegre');
INSERT INTO matches(id,competition_id,stage,kickoff_utc,home_team_id,away_team_id,home_score,away_score,status,venue) VALUES(10,1,'小组赛 B','2014-06-18 19:00:00',5,7,0,2,'finished','Maracanã, Rio de Janeiro');
INSERT INTO matches(id,competition_id,stage,kickoff_utc,home_team_id,away_team_id,home_score,away_score,status,venue) VALUES(11,1,'小组赛 B','2014-06-23 16:00:00',6,7,2,0,'finished','Neo Química Arena, São Paulo');
INSERT INTO matches(id,competition_id,stage,kickoff_utc,home_team_id,away_team_id,home_score,away_score,status,venue) VALUES(12,1,'小组赛 B','2014-06-23 16:00:00',8,5,0,3,'finished','Arena da Baixada, Curitiba');
