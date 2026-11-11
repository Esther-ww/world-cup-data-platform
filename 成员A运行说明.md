# 看球日历 · 成员 A 交付
运行条件：PHP 8.1+、PDO。演示启用 pdo_sqlite；整合启用 pdo_mysql。Web 根目录必须是 public，不要公开 data、src 或 var。
## 本地运行（PowerShell，在本目录执行）
```powershell
php bin/setup.php demo
php -S 127.0.0.1:8770 -t public
```
本机若没有全局 php，用已有便携版路径替代 php：`& 'C:/Users/16484/Documents/Codex/2026-09-16/w-x/work/runtime/php/php.exe'`。访问 http://127.0.0.1:8770/ 。演示库不会覆盖已有数据；默认不创建账号。
## 创建管理员（只在本机执行一次）
```powershell
$pw = Read-Host '管理员密码（至少10字节）' -AsSecureString
$env:A_ADMIN_PASSWORD = [Net.NetworkCredential]::new('', $pw).Password
try { php bin/setup.php admin } finally { Remove-Item Env:A_ADMIN_PASSWORD }
```
用户名 admin。不要提交 var 中的数据库或密码。管理员不能从网页注册获取。
## 接入团队项目
A 交付七张表和独立 MVC；这不是全队完整数据库。B/C 先建立 matches(id)、teams(id)，确定主键类型，再由 B 汇编 data/install.sql。正式版要求 MySQL 8 + utf8mb4，外键两端类型一致。勿将 data/demo 中的表导入正式库。
B、C 各自提供只读视图 a_match_catalog(id,title)、a_team_catalog(id,title)，id 必须指向真实主键，title 分别为可读对阵与队名。通过 A_DSN（如 mysql:host=localhost;dbname=cup;charset=utf8mb4）、A_DB_USER、A_DB_PASS 配置。不要给 Web 数据库用户管理员建库权限。
B/C 的 PHP 详情页可在同一会话内向本模块入口 POST：action=favorite、type=match 或 team、id=业务主键、save=1 或 0、csrf=当前会话 csrf。用户编号永远取服务端会话，不接收 user_id 参数。未共用会话前先使用入口 ?p=catalog 独立演示。
## 文件
public/index.php → src/Controller.php → src/Model.php → views/page.php。data/team 含需求和手册；data/personal/A 放已有实验一插件附件，正式汇总时改为学号目录，补全三人最终报告与附件后更新 src/files.php 清单。不包含任何人的未验收报告。测试见 tests/smoke.py。
## 待联调
真实球队和赛程视图、比赛/球队详情跳转、最终材料下载与真实队名；B 的筛选与详情交叉验收仍待其交付。请通过各自 Git 账号提交并互审，不能把演示用例写成真实合并记录。
