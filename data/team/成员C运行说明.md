# 世界杯数据平台 · 成员 C 模块运行说明

> 适用版本：分支 `feature/member-c`。本说明只启动 C 的独立演示模块（球队、球员、名单、阵容、后台）。
> 正式部署需按《实现文档》第 7 节与 A/B 模块合并；合并后以团队总部署文档为准。

## 1 环境要求

| 项目 | 要求 |
|---|---|
| PHP | 8.1 及以上，CLI 与内置 Web 服务器 |
| PHP 扩展 | `pdo_sqlite`、`sqlite3`（演示库）；接 MySQL 时加 `pdo_mysql` |
| Python | 3.9+（仅运行自动化测试，可选） |
| 数据库 | 演示无需安装；正式环境 MySQL 8.0+ |
| Web 根目录 | 必须指向 `public/`，不要把 `src/`、`data/`、`var/` 暴露到 Web |

检查：

```bash
php -v
php -m | grep -Ei 'pdo_sqlite|sqlite3|pdo_mysql'
```

Windows 便携 PHP 可直接使用本机 `.tools/php/php.exe`，下文以 `php` 代称。

## 2 三分钟启动（SQLite 演示）

在仓库根目录执行：

```bash
# 1) 建库、建表、写入 12 场占位比赛、导入 5 个 CSV
php bin/setup.php demo

# 2) 创建管理员（密码至少 10 字节；只用于本地演示）
C_ADMIN_PASSWORD='local-test-only-123' php bin/setup.php admin

# 3) 启动
php -S 127.0.0.1:8771 -t public
```

打开 <http://127.0.0.1:8771/>，用 `admin` / `local-test-only-123` 登录后台。

`setup.php demo` 有重复执行保护：`var/demo.sqlite` 已存在时不会覆盖。
需要重置时先停止服务，再删除 `var/demo.sqlite`（会话文件在系统临时目录，可不处理）：

```bash
rm -f var/demo.sqlite
php bin/setup.php demo
```

`var/` 已被 `.gitignore` 排除，演示库不会进入提交。

## 3 演示路线

| 顺序 | 地址 | 看点 |
|---|---|---|
| 1 | `?p=teams` | 8 支球队、搜索、球队卡片 |
| 2 | `?p=team&id=1` | 巴西 23 人名单，按门将/后卫/中场/前锋分组，出生日期与年龄 |
| 3 | `?p=players&q=Neymar` | 姓名搜索 |
| 4 | `?p=players&team=1&position=FW` | 球队 + 位置筛选 |
| 5 | `?p=player&id=21` | 内马尔详情：资料、名单号码、首发记录 |
| 6 | `?p=lineup&match=1` | 双方阵型、球场示意图、首发与登场替补 |
| 7 | `?p=lineup&match=11` | “阵容未公布”空状态 |
| 8 | 登录 → `?p=admin&tab=team` | 后台球队增删改；删除有名单的球队会被拒绝 |
| 9 | `?p=admin&tab=lineup` | 选比赛与球队，勾选 11 名首发；少一人保存会报错 |
| 10 | `?p=admin&tab=import` | 先“校验（dry-run）”，再正式导入；任一行失败整批回滚 |

演示数据刻意保留的边界：比赛 11、12 没有阵容；12 名球员没有出生日期，页面显示“未收录”；
替补只包含数据源记录的登场替补。讲解时说明这是数据缺口，不是页面错误。

## 4 自动化测试

```bash
python tests/c_smoke.py /path/to/php
```

Windows 示例：

```bash
python tests/c_smoke.py "D:/互联网数据库开发/.tools/php/php.exe"
```

测试在仓库内创建临时 SQLite 库与临时会话目录，自动启动和关闭 PHP 服务器，
**不会修改** `var/demo.sqlite`。通过时输出：

```text
PASS: 公共页面、空阵容、搜索筛选、分页、权限、CSRF、球队/球员 CRUD、外键保护、名单号码唯一、阵容 11 人校验、撤下阵容、导入 dry-run 回滚、退出
```

覆盖：访客浏览、404、普通用户 403、管理员登录、错误 CSRF、球队/球员 CRUD、被引用数据删除保护、
名单号码唯一、阵容人数与门将校验、保存/撤下阵容、导入 dry-run 回滚、退出。

## 5 CLI 导入

```bash
php bin/import.php teams
php bin/import.php players --dry-run
```

可用类型：`teams`、`players`、`squads`、`lineups`、`lineup_players`。
导入顺序有依赖：球队 → 球员 → 名单 → 阵容 → 阵容球员；比赛（B 的 `matches`）必须先存在。
`--dry-run` 完整校验并写入后回滚，用于检查数据。字段与来源见 `data/import/README.md`。

## 6 切换到 MySQL

演示库之外，正式库需要先有成员 B 的 `competitions`、`matches` 和成员 A 的 `users`
（或按集成文档替换）。C 的表定义在 `data/install.sql`。

```bash
# 示例：先由团队安装脚本创建库与 A/B 表，再执行 C 的建表语句
mysql -u root -p worldcup < data/install.sql

export C_DSN='mysql:host=127.0.0.1;port=3306;dbname=worldcup;charset=utf8mb4'
export C_DB_USER='worldcup_app'
export C_DB_PASS='********'
php bin/import.php teams
php bin/import.php players
# squads/lineups/lineup_players 需在 B 的届次与比赛数据就绪后导入
```

注意：

- MySQL 下不能再执行 `bin/setup.php demo`（脚本检测到 `C_DSN` 会拒绝），管理员应由 A 的账号体系创建。
- `data/install.sql` 依赖 B 的 `competitions(id)`、`matches(id)` 为 `INT UNSIGNED`；
  若 B 使用其他类型，先统一主键类型再建外键。
- 集成后删除 C 的占位 `users`、登录页和 `setup.php admin` 逻辑，改用 A 的会话与角色。
- 生产环境设置 `display_errors=Off`、`log_errors=On`，数据库账号只授予 C 表所需的
  `SELECT/INSERT/UPDATE/DELETE`，不要使用 root。

## 7 安全与部署要点

- Web 根目录只指 `public/`；`data/`、`var/`、`src/` 不应可被 URL 直接访问。
- 所有写操作要求管理员角色 + CSRF；角色从数据库读取，不信任表单。
- SQL 全部参数化，输出统一 `c_h()` 转义；导入只读取 `data/import` 白名单文件，
  且限制单文件 2 MB、严格校验表头。
- 凭据只通过环境变量传入；仓库不保存密码、DSN 或会话文件。
- 会话 Cookie 为 `HttpOnly`、`SameSite=Lax`；HTTPS 下自动加 `Secure`。
- 生产环境建议由 Nginx/Apache 指向 `public/` 并启用 HTTPS；PHP 内置服务器只用于开发演示。

## 8 常见问题

| 现象 | 原因与处理 |
|---|---|
| `could not find driver` | 未启用 `pdo_sqlite`；在 `php.ini` 打开 `extension=pdo_sqlite` |
| `attempt to write a readonly database` | `var/` 无写权限；确认运行用户可写，或改 `C_DSN` 到可写路径 |
| `演示库已初始化，未覆盖` | 已有 `var/demo.sqlite`；确认后删除再执行 `setup.php demo` |
| 页面 403 | 未登录或不是管理员；用 `setup.php admin` 创建的账号登录 |
| 页面 500 / 空白 | 开发时打开 `display_errors=On` 看日志；生产写日志不要回显 |
| 阵容保存提示“首发必须正好 11 人” | 勾选人数不对，或缺少恰好 1 名门将；按提示补齐 |
| 导入提示外键不存在 | 先导入 `teams`/`players`，并确保 B 的届次与比赛已存在 |

## 9 目录速查

```
public/index.php        入口（Web 根目录 public/）
src/PlayerModel.php     模型与导入校验
src/PlayerController.php 路由、权限、CSRF
views/                  页面与后台
data/install.sql        C 的 MySQL 表
data/demo/              独立演示建表与占位比赛
data/import/            CSV 与数据说明
bin/setup.php           初始化演示库 / 创建管理员
bin/import.php          CLI 导入
tests/c_smoke.py        端到端测试
var/demo.sqlite         本地演示库（不提交）
```
