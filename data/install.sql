-- MySQL 8：先导入 B/C 的 matches、teams，两表主键为 INT UNSIGNED。
CREATE TABLE users(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,username VARCHAR(48) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL,role VARCHAR(8) NOT NULL DEFAULT 'user');
CREATE TABLE team_profiles(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(80) NOT NULL,intro TEXT NOT NULL,contact VARCHAR(120) NOT NULL);
CREATE TABLE team_members(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,profile_id INT UNSIGNED NOT NULL,label VARCHAR(8) NOT NULL UNIQUE,intro TEXT NOT NULL,display_order INTEGER NOT NULL,FOREIGN KEY(profile_id) REFERENCES team_profiles(id));
CREATE TABLE messages(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,body TEXT NOT NULL,status VARCHAR(10) NOT NULL DEFAULT 'pending',created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE assignments(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,member_id INT UNSIGNED,title VARCHAR(100) NOT NULL,file_key VARCHAR(64) NOT NULL UNIQUE,FOREIGN KEY(member_id) REFERENCES team_members(id));
CREATE TABLE favorite_matches(user_id INT UNSIGNED NOT NULL,match_id INT UNSIGNED NOT NULL,PRIMARY KEY(user_id,match_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(match_id) REFERENCES matches(id) ON DELETE CASCADE);
CREATE TABLE favorite_teams(user_id INT UNSIGNED NOT NULL,team_id INT UNSIGNED NOT NULL,PRIMARY KEY(user_id,team_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(team_id) REFERENCES teams(id) ON DELETE CASCADE);
INSERT INTO team_profiles VALUES(1,'世界杯数据平台项目组','从赛程、球队与球员三个角度了解世界杯。','');
INSERT INTO team_members VALUES(1,1,'A','账号、收藏、团队主页与资料下载',1),(2,1,'B','赛程、比赛数据与积分榜',2),(3,1,'C','球队、球员与阵容',3);
INSERT INTO assignments(member_id,title,file_key) VALUES(NULL,'需求文档','requirements'),(NULL,'用户手册','manual');
INSERT INTO assignments(member_id,title,file_key) VALUES(1,'A：网页jQuery实验助手（实验一附件）','a_plugin');
