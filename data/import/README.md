# 成员 C 演示数据说明

本目录是成员 C 负责的“球队、球员、届次名单、阵容”演示数据。数据不是赛事全量数据，
只覆盖 **2014 年巴西世界杯 A、B 两组**，用于演示 F05（阵容与阵型）和 F06（球队与球员）。

## 1 来源与采集日期

| 数据 | 来源 | 用途 | 许可 |
|---|---|---|---|
| 球队、23 人名单、号码、位置 | [openfootball/world-cup · 2014--brazil/squads.txt](https://github.com/openfootball/world-cup/blob/master/2014--brazil/squads.txt) | `teams.csv`、`squads.csv`、`players.csv` 的位置与姓名 | CC0 1.0 |
| 比赛结果、日期、球场 | [openfootball/worldcup.json · 2014/worldcup-full.json](https://github.com/openfootball/worldcup.json/blob/master/2014/worldcup-full.json) | `data/demo/stub.sql` 的 12 场占位比赛 | CC0 1.0 |
| 首发与登场替补 | 同上 `worldcup-full.json` 的 `lineup` 字段 | `lineups.csv`、`lineup_players.csv` | CC0 1.0 |
| 出生日期 | [openfootball/players](https://github.com/openfootball/players)（按国家分文件） | `players.csv.birth_date` | CC0 1.0 |

采集日期：**2026-11-12**。抓取后按姓名 + 位置做连接，未做人工逐条核对；
演示前如用于正式答辩，请再用官方名单抽查。

覆盖范围：

- 8 支球队：巴西、克罗地亚、墨西哥、喀麦隆（A 组）；西班牙、荷兰、智利、澳大利亚（B 组）。
- 每队 23 人，共 **184 名球员**。
- 12 场小组赛；其中 **10 场**导入双方阵容（20 份），最后 2 场刻意留空用于演示“阵容未公布”。
- 阵容球员记录 **278 条**（首发 220 条 + 数据源记录的登场替补 58 条）。

## 2 文件与字段

### teams.csv（8 行）

| 字段 | 说明 |
|---|---|
| id | 稳定的演示主键，1–8 |
| name | 中文队名（人工映射） |
| name_en | 英文队名（源数据） |
| country_code | 3 位大写国家代码（人工映射，UNIQUE） |

### players.csv（184 行）

| 字段 | 说明 |
|---|---|
| id | 稳定主键，按球队分组连续编号 |
| name | 源名单姓名（拉丁字母，保留重音） |
| name_en | 与 name 相同，预留英文检索 |
| birth_date | `YYYY-MM-DD`；连接不到时**留空**，页面显示“未收录” |
| nationality | 中文国籍（随所属球队） |
| position | `GK` / `DF` / `MF` / `FW`（源名单位置） |

出生日期缺口（12 人，页面明确显示“未收录”，不填造）：
Marcelo、Paulinho、José de Jesús Corona、Francisco Javier Rodríguez、Carlos Peña、
José Juan Vázquez、Henri Bedimo、Allan Nyom、Alex Song、Jean Makoun、Mathew Ryan、Mathew Leckie。

### squads.csv（184 行）

| 字段 | 说明 |
|---|---|
| competition_id | 固定为 1（`data/demo/stub.sql` 的演示届次） |
| team_id | 对应 `teams.id` |
| player_id | 对应 `players.id` |
| shirt_number | 官方名单号码，1–99 |

约束：同一届次内 `player_id` 唯一；同一球队内 `shirt_number` 唯一。

### lineups.csv（20 行）

| 字段 | 说明 |
|---|---|
| id | 阵容主键 |
| match_id | 对应 `data/demo/stub.sql` 的比赛 id |
| team_id | 参赛球队 |
| formation | 阵型字符串 |

**阵型是推算值，不是官方阵型**：按首发 11 人在名单中的位置统计“后卫-中场-前锋”，
例如 4-3-3。页面会标注“按首发位置推算，仅作展示”。真实 4-2-3-1 等细分阵型无法从该数据源得到。

### lineup_players.csv（278 行）

| 字段 | 说明 |
|---|---|
| lineup_id | 对应 `lineups.id` |
| player_id | 必须属于该阵容所在球队的届次名单 |
| is_starter | 1 首发 / 0 替补 |
| position | 本场位置，取名单位置 |
| x, y | 0–100 的展示坐标；首发按位置分组自动排布，替补留空 |

**替补覆盖不完整**：源数据只记录“实际登场”的替补，不包含完整替补席。
页面因此写“数据源只记录登场替补”，不把未登场球员伪造成替补。

## 3 未包含的数据

- 比赛统计（控球率、射门、角球等）和比赛事件（进球、牌、换人）属于成员 B 的范围，本目录不提供。
- 主教练、裁判、球场坐标、球员身高体重、俱乐部等字段不在 C 的表结构中。
- 2014 年世界杯其余 24 支球队、淘汰赛阶段未纳入演示子集。
- 中文球员译名未录入；当前显示源数据拉丁字母姓名，避免错误翻译。

## 4 导入方式

```bash
# 初始化演示库（建表 + 占位比赛 + 依次导入 5 个 CSV）
php bin/setup.php demo

# 单独导入某个文件；任一行失败整批回滚
php bin/import.php teams
php bin/import.php players --dry-run

# 后台“数据导入”页复用同一套校验逻辑
```

导入校验包括：表头完全匹配、主键/唯一键、外键存在性、位置枚举、日期格式、
号码 1–99、阵容首发 11 人且恰好 1 名门将、替补最多 12 人、球员属于对应球队届次名单、坐标 0–100。
