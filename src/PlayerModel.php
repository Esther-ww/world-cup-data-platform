<?php
/**
 * 成员 C | 模型（Model）：球队、球员、届次名单与阵容的数据访问。
 *
 * 项目：世界杯数据平台（课程团队大作业）
 * 职责：所有 SQL 集中在本文件；控制器只处理参数与权限，视图只负责呈现。
 * 约定：使用 PDO 参数化查询；MySQL 与 SQLite 双兼容；写操作由调用方放在事务中。
 *
 * 主要数据表：teams / players / squads / lineups / lineup_players
 * 依赖：competitions / matches（成员 B），users（成员 A，仅独立演示占位）
 */

class CImportException extends RuntimeException
{
    /** @var string[] 逐行校验错误 */
    public array $errors;

    public function __construct(array $errors)
    {
        parent::__construct("导入校验失败");
        $this->errors = $errors;
    }
}

class PlayerModel
{
    public PDO $db;

    public function __construct()
    {
        $dsn = getenv('C_DSN') ?: 'sqlite:' . dirname(__DIR__) . '/var/demo.sqlite';
        $user = getenv('C_DB_USER');
        $pass = getenv('C_DB_PASS');
        $this->db = new PDO($dsn, $user === false ? null : $user, $pass === false ? null : $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->db->exec('PRAGMA foreign_keys = ON');
        }
    }

    /* ------------------------------------------------------------------
     * 基础查询封装
     * ------------------------------------------------------------------ */

    public function q(string $sql, array $args = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);
        return $stmt;
    }

    public function rows(string $sql, array $args = []): array
    {
        return $this->q($sql, $args)->fetchAll();
    }

    public function row(string $sql, array $args = []): ?array
    {
        $row = $this->q($sql, $args)->fetch();
        return $row === false ? null : $row;
    }

    public function scalar(string $sql, array $args = [])
    {
        $value = $this->q($sql, $args)->fetchColumn();
        return $value === false ? null : $value;
    }

    /* ------------------------------------------------------------------
     * 公共读取：届次、球队、名单
     * ------------------------------------------------------------------ */

    /** 独立演示只加载一个届次；联调时可改为从 B 的赛事模块传入选中的届次。 */
    public function currentCompetition(): ?array
    {
        return $this->row('SELECT * FROM competitions ORDER BY id LIMIT 1');
    }

    public function competitionFind(int $id): ?array
    {
        return $this->row('SELECT * FROM competitions WHERE id = ?', [$id]);
    }

    public function teamList(int $competitionId, string $q = ''): array
    {
        $like = '%' . $q . '%';
        $where = '';
        $args = [$competitionId];
        if ($q !== '') {
            $where = 'WHERE t.name LIKE ? OR t.name_en LIKE ? OR t.country_code LIKE ?';
            array_push($args, $like, $like, $like);
        }
        return $this->rows(
            "SELECT t.id, t.name, t.name_en, t.country_code,
                    COUNT(s.player_id) AS squad_count
             FROM teams t
             LEFT JOIN squads s ON s.team_id = t.id AND s.competition_id = ?
             $where
             GROUP BY t.id, t.name, t.name_en, t.country_code
             ORDER BY t.name",
            $args
        );
    }

    public function teamFind(int $id): ?array
    {
        return $this->row('SELECT * FROM teams WHERE id = ?', [$id]);
    }

    public function teamSquad(int $competitionId, int $teamId): array
    {
        return $this->rows(
            "SELECT p.id, p.name, p.name_en, p.birth_date, p.nationality, p.position,
                    s.shirt_number
             FROM squads s
             JOIN players p ON p.id = s.player_id
             WHERE s.competition_id = ? AND s.team_id = ?
             ORDER BY CASE p.position WHEN 'GK' THEN 1 WHEN 'DF' THEN 2 WHEN 'MF' THEN 3 ELSE 4 END,
                      s.shirt_number",
            [$competitionId, $teamId]
        );
    }

    public function teamMatches(int $competitionId, int $teamId): array
    {
        return $this->rows(
            "SELECT m.*, h.name AS home_name, h.name_en AS home_name_en,
                    a.name AS away_name, a.name_en AS away_name_en
             FROM matches m
             JOIN teams h ON h.id = m.home_team_id
             JOIN teams a ON a.id = m.away_team_id
             WHERE m.competition_id = ? AND (m.home_team_id = ? OR m.away_team_id = ?)
             ORDER BY m.kickoff_utc",
            [$competitionId, $teamId, $teamId]
        );
    }

    /* ------------------------------------------------------------------
     * 公共读取：球员搜索、球员详情
     * ------------------------------------------------------------------ */

    private function playerWhere(int $competitionId, string $q, int $teamId, string $position): array
    {
        $where = [];
        $args = [$competitionId];
        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.name_en LIKE ?)';
            $like = '%' . $q . '%';
            array_push($args, $like, $like);
        }
        if ($teamId > 0) {
            $where[] = 't.id = ?';
            $args[] = $teamId;
        }
        if ($position !== '') {
            $where[] = 'p.position = ?';
            $args[] = $position;
        }
        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $args];
    }

    public function playerSearchCount(int $competitionId, string $q, int $teamId, string $position): int
    {
        [$where, $args] = $this->playerWhere($competitionId, $q, $teamId, $position);
        $sql = "SELECT COUNT(*)
                FROM players p
                LEFT JOIN squads s ON s.player_id = p.id AND s.competition_id = ?
                LEFT JOIN teams t ON t.id = s.team_id
                $where";
        return (int) $this->scalar($sql, $args);
    }

    public function playerSearch(int $competitionId, string $q, int $teamId, string $position,
                                 int $limit, int $offset): array
    {
        [$where, $args] = $this->playerWhere($competitionId, $q, $teamId, $position);
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        return $this->rows(
            "SELECT p.id, p.name, p.name_en, p.birth_date, p.nationality, p.position,
                    s.shirt_number, t.id AS team_id, t.name AS team_name, t.country_code
             FROM players p
             LEFT JOIN squads s ON s.player_id = p.id AND s.competition_id = ?
             LEFT JOIN teams t ON t.id = s.team_id
             $where
             ORDER BY p.name
             LIMIT $limit OFFSET $offset",
            $args
        );
    }

    public function playerFind(int $id): ?array
    {
        return $this->row('SELECT * FROM players WHERE id = ?', [$id]);
    }

    public function playerSquadInfo(int $playerId, int $competitionId): ?array
    {
        return $this->row(
            "SELECT s.shirt_number, t.id AS team_id, t.name AS team_name,
                    t.name_en AS team_name_en, t.country_code, c.id AS competition_id,
                    c.name AS competition_name
             FROM squads s
             JOIN teams t ON t.id = s.team_id
             JOIN competitions c ON c.id = s.competition_id
             WHERE s.player_id = ? AND s.competition_id = ?",
            [$playerId, $competitionId]
        );
    }

    public function playerAppearances(int $playerId): array
    {
        return $this->rows(
            "SELECT lp.is_starter, lp.position AS lineup_position, l.formation,
                    m.id AS match_id, m.kickoff_utc, m.stage,
                    m.home_team_id, m.away_team_id, m.home_score, m.away_score,
                    h.name AS home_name, a.name AS away_name,
                    lt.id AS lineup_team_id, lt.name AS lineup_team_name
             FROM lineup_players lp
             JOIN lineups l ON l.id = lp.lineup_id
             JOIN matches m ON m.id = l.match_id
             JOIN teams h ON h.id = m.home_team_id
             JOIN teams a ON a.id = m.away_team_id
             JOIN teams lt ON lt.id = l.team_id
             WHERE lp.player_id = ?
             ORDER BY m.kickoff_utc DESC",
            [$playerId]
        );
    }

    /* ------------------------------------------------------------------
     * 公共读取：比赛与阵容
     * ------------------------------------------------------------------ */

    public function matchList(int $competitionId): array
    {
        return $this->rows(
            "SELECT m.*, h.name AS home_name, h.name_en AS home_name_en,
                    a.name AS away_name, a.name_en AS away_name_en,
                    (SELECT COUNT(*) FROM lineups l WHERE l.match_id = m.id) AS lineup_count
             FROM matches m
             JOIN teams h ON h.id = m.home_team_id
             JOIN teams a ON a.id = m.away_team_id
             WHERE m.competition_id = ?
             ORDER BY m.kickoff_utc",
            [$competitionId]
        );
    }

    public function matchFind(int $id): ?array
    {
        return $this->row(
            "SELECT m.*, h.name AS home_name, h.name_en AS home_name_en,
                    a.name AS away_name, a.name_en AS away_name_en
             FROM matches m
             JOIN teams h ON h.id = m.home_team_id
             JOIN teams a ON a.id = m.away_team_id
             WHERE m.id = ?",
            [$id]
        );
    }

    public function lineupFor(int $matchId, int $teamId): ?array
    {
        return $this->row('SELECT * FROM lineups WHERE match_id = ? AND team_id = ?',
            [$matchId, $teamId]);
    }

    public function lineupPlayers(int $lineupId, int $competitionId, int $teamId): array
    {
        return $this->rows(
            "SELECT lp.*, p.id, p.name, p.name_en, p.nationality, s.shirt_number
             FROM lineup_players lp
             JOIN players p ON p.id = lp.player_id
             LEFT JOIN squads s ON s.competition_id = ? AND s.team_id = ? AND s.player_id = lp.player_id
             WHERE lp.lineup_id = ?
             ORDER BY lp.is_starter DESC,
                      CASE lp.position WHEN 'GK' THEN 1 WHEN 'DF' THEN 2 WHEN 'MF' THEN 3 ELSE 4 END,
                      s.shirt_number, p.name",
            [$competitionId, $teamId, $lineupId]
        );
    }

    /* ------------------------------------------------------------------
     * 后台：球队 CRUD
     * ------------------------------------------------------------------ */

    public function adminTeamList(): array
    {
        return $this->rows(
            "SELECT t.*, (SELECT COUNT(*) FROM squads s WHERE s.team_id = t.id) AS squad_count
             FROM teams t ORDER BY t.id"
        );
    }

    public function teamSave(int $id, string $name, string $nameEn, string $code): int
    {
        $dup = $this->row('SELECT id FROM teams WHERE country_code = ? AND id <> ?', [$code, $id]);
        if ($dup) {
            throw new DomainException('国家代码已被其他球队使用');
        }
        if ($id > 0) {
            if (!$this->teamFind($id)) {
                throw new DomainException('球队不存在');
            }
            $this->q('UPDATE teams SET name = ?, name_en = ?, country_code = ? WHERE id = ?',
                [$name, $nameEn, $code, $id]);
            return $id;
        }
        $this->q('INSERT INTO teams(name, name_en, country_code) VALUES(?,?,?)',
            [$name, $nameEn, $code]);
        return (int) $this->db->lastInsertId();
    }

    /** 有关联数据的球队禁止删除，避免破坏名单、阵容和赛程。 */
    public function teamDelete(int $id): void
    {
        if (!$this->teamFind($id)) {
            throw new DomainException('球队不存在');
        }
        $refs = (int) $this->scalar('SELECT COUNT(*) FROM squads WHERE team_id = ?', [$id])
            + (int) $this->scalar('SELECT COUNT(*) FROM lineups WHERE team_id = ?', [$id])
            + (int) $this->scalar(
                'SELECT COUNT(*) FROM matches WHERE home_team_id = ? OR away_team_id = ?', [$id, $id]
            );
        if ($refs > 0) {
            throw new DomainException('该球队仍被名单、阵容或比赛引用，不能删除');
        }
        $this->q('DELETE FROM teams WHERE id = ?', [$id]);
    }

    /* ------------------------------------------------------------------
     * 后台：球员 CRUD
     * ------------------------------------------------------------------ */

    public function playerSave(int $id, string $name, string $nameEn, ?string $birthDate,
                               string $nationality, string $position): int
    {
        if ($id > 0) {
            if (!$this->playerFind($id)) {
                throw new DomainException('球员不存在');
            }
            $this->q('UPDATE players SET name = ?, name_en = ?, birth_date = ?, nationality = ?, position = ?
                      WHERE id = ?',
                [$name, $nameEn, $birthDate, $nationality, $position, $id]);
            return $id;
        }
        $this->q('INSERT INTO players(name, name_en, birth_date, nationality, position)
                  VALUES(?,?,?,?,?)',
            [$name, $nameEn, $birthDate, $nationality, $position]);
        return (int) $this->db->lastInsertId();
    }

    public function playerDelete(int $id): void
    {
        if (!$this->playerFind($id)) {
            throw new DomainException('球员不存在');
        }
        $refs = (int) $this->scalar('SELECT COUNT(*) FROM squads WHERE player_id = ?', [$id])
            + (int) $this->scalar('SELECT COUNT(*) FROM lineup_players WHERE player_id = ?', [$id]);
        if ($refs > 0) {
            throw new DomainException('该球员仍被届次名单或阵容引用，不能删除');
        }
        $this->q('DELETE FROM players WHERE id = ?', [$id]);
    }

    /* ------------------------------------------------------------------
     * 后台：届次名单
     * ------------------------------------------------------------------ */

    public function squadAvailablePlayers(int $competitionId): array
    {
        return $this->rows(
            "SELECT p.id, p.name, p.position, p.nationality
             FROM players p
             WHERE NOT EXISTS (
                 SELECT 1 FROM squads s WHERE s.player_id = p.id AND s.competition_id = ?
             )
             ORDER BY p.name",
            [$competitionId]
        );
    }

    public function squadAdd(int $competitionId, int $teamId, int $playerId, int $number): void
    {
        if (!$this->competitionFind($competitionId) || !$this->teamFind($teamId)
            || !$this->playerFind($playerId)) {
            throw new DomainException('届次、球队或球员不存在');
        }
        if ($number < 1 || $number > 99) {
            throw new DomainException('球衣号码必须在 1–99 之间');
        }
        if ($this->row('SELECT 1 FROM squads WHERE competition_id = ? AND player_id = ?',
            [$competitionId, $playerId])) {
            throw new DomainException('该球员已在本届其他球队名单中');
        }
        if ($this->row('SELECT 1 FROM squads WHERE competition_id = ? AND team_id = ? AND shirt_number = ?',
            [$competitionId, $teamId, $number])) {
            throw new DomainException('该号码已被本队其他球员使用');
        }
        $this->q('INSERT INTO squads(competition_id, team_id, player_id, shirt_number) VALUES(?,?,?,?)',
            [$competitionId, $teamId, $playerId, $number]);
    }

    public function squadDelete(int $competitionId, int $teamId, int $playerId): void
    {
        $lineupRefs = (int) $this->scalar(
            "SELECT COUNT(*)
             FROM lineup_players lp
             JOIN lineups l ON l.id = lp.lineup_id
             JOIN matches m ON m.id = l.match_id
             WHERE lp.player_id = ? AND l.team_id = ? AND m.competition_id = ?",
            [$playerId, $teamId, $competitionId]
        );
        if ($lineupRefs > 0) {
            throw new DomainException('该球员已有阵容记录，请先撤下相关阵容');
        }
        $this->q('DELETE FROM squads WHERE competition_id = ? AND team_id = ? AND player_id = ?',
            [$competitionId, $teamId, $playerId]);
    }

    /* ------------------------------------------------------------------
     * 后台：阵容编辑
     * ------------------------------------------------------------------ */

    /**
     * 保存一场比赛一支球队的阵容。
     * @param array<int,string> $roles     player_id => '' | starter | bench
     * @param array<int,string> $positions player_id => GK|DF|MF|FW
     */
    public function lineupSave(int $matchId, int $teamId, string $formation,
                               array $roles, array $positions): int
    {
        $match = $this->matchFind($matchId);
        if (!$match) {
            throw new DomainException('比赛不存在');
        }
        if ($teamId !== (int) $match['home_team_id'] && $teamId !== (int) $match['away_team_id']) {
            throw new DomainException('该球队不参加这场比赛');
        }
        $competitionId = (int) $match['competition_id'];
        $squad = [];
        foreach ($this->teamSquad($competitionId, $teamId) as $p) {
            $squad[(int) $p['id']] = $p;
        }
        if (!$squad) {
            throw new DomainException('该队尚未建立届次名单，不能录入阵容');
        }

        $starters = [];
        $bench = [];
        foreach ($roles as $pid => $role) {
            $pid = (int) $pid;
            if (!isset($squad[$pid])) {
                throw new DomainException('阵容中有球员不在本队本届名单中');
            }
            if ($role === '') {
                continue;
            }
            if ($role !== 'starter' && $role !== 'bench') {
                throw new DomainException('球员角色无效');
            }
            $position = $positions[$pid] ?? $squad[$pid]['position'];
            if (!in_array($position, ['GK', 'DF', 'MF', 'FW'], true)) {
                throw new DomainException('球员位置无效');
            }
            $entry = ['player_id' => $pid, 'position' => $position];
            if ($role === 'starter') {
                $starters[] = $entry;
            } else {
                $bench[] = $entry;
            }
        }
        if (count($starters) !== 11) {
            throw new DomainException('首发必须正好 11 人，当前为 ' . count($starters) . ' 人');
        }
        if (count(array_filter($starters, fn($p) => $p['position'] === 'GK')) !== 1) {
            throw new DomainException('首发必须有且只有 1 名门将');
        }
        if (count($bench) > 12) {
            throw new DomainException('替补最多 12 人');
        }

        $formation = trim($formation);
        if ($formation === '') {
            $formation = $this->deriveFormation($starters);
        } elseif (!$this->validFormation($formation)) {
            throw new DomainException('阵型格式应为 4-3-3 或 4-2-3-1 之类的数字组合，且人数合计为 10');
        }

        $coords = $this->starterCoordinates($starters);
        $now = date('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            $existing = $this->lineupFor($matchId, $teamId);
            if ($existing) {
                $lineupId = (int) $existing['id'];
                $this->q('UPDATE lineups SET formation = ? WHERE id = ?', [$formation, $lineupId]);
                $this->q('DELETE FROM lineup_players WHERE lineup_id = ?', [$lineupId]);
            } else {
                $this->q('INSERT INTO lineups(match_id, team_id, formation, created_at) VALUES(?,?,?,?)',
                    [$matchId, $teamId, $formation, $now]);
                $lineupId = (int) $this->db->lastInsertId();
            }
            $insert = 'INSERT INTO lineup_players(lineup_id, player_id, is_starter, position, x, y)
                       VALUES(?,?,?,?,?,?)';
            foreach ($starters as $p) {
                [$x, $y] = $coords[$p['player_id']];
                $this->q($insert, [$lineupId, $p['player_id'], 1, $p['position'], $x, $y]);
            }
            foreach ($bench as $p) {
                $this->q($insert, [$lineupId, $p['player_id'], 0, $p['position'], null, null]);
            }
            $this->db->commit();
            return $lineupId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function lineupDelete(int $matchId, int $teamId): void
    {
        $this->q('DELETE FROM lineups WHERE match_id = ? AND team_id = ?', [$matchId, $teamId]);
    }

    private function validFormation(string $formation): bool
    {
        if (!preg_match('/^\d{1,2}(?:-\d{1,2}){2,3}$/D', $formation)) {
            return false;
        }
        $sum = array_sum(array_map('intval', explode('-', $formation)));
        return $sum === 10;
    }

    private function deriveFormation(array $starters): string
    {
        $counts = ['DF' => 0, 'MF' => 0, 'FW' => 0];
        foreach ($starters as $p) {
            if (isset($counts[$p['position']])) {
                $counts[$p['position']]++;
            }
        }
        return $counts['DF'] . '-' . $counts['MF'] . '-' . $counts['FW'];
    }

    /** 给首发一个粗略的球场坐标（0–100），仅用于展示，不参与业务判断。 */
    private function starterCoordinates(array $starters): array
    {
        $groups = ['GK' => [], 'DF' => [], 'MF' => [], 'FW' => []];
        foreach ($starters as $p) {
            $groups[$p['position']][] = $p['player_id'];
        }
        $ys = ['GK' => 92, 'DF' => 70, 'MF' => 46, 'FW' => 22];
        $out = [];
        foreach ($groups as $position => $ids) {
            $n = count($ids);
            foreach ($ids as $i => $pid) {
                $out[$pid] = [(int) round(($i + 1) * 100 / ($n + 1)), $ys[$position]];
            }
        }
        return $out;
    }

    /* ------------------------------------------------------------------
     * CSV 导入（后台和 bin/setup.php 共用）
     * ------------------------------------------------------------------ */

    public const IMPORT_FILES = [
        'teams' => 'teams.csv',
        'players' => 'players.csv',
        'squads' => 'squads.csv',
        'lineups' => 'lineups.csv',
        'lineup_players' => 'lineup_players.csv',
    ];

    private const IMPORT_HEADERS = [
        'teams' => ['id', 'name', 'name_en', 'country_code'],
        'players' => ['id', 'name', 'name_en', 'birth_date', 'nationality', 'position'],
        'squads' => ['competition_id', 'team_id', 'player_id', 'shirt_number'],
        'lineups' => ['id', 'match_id', 'team_id', 'formation'],
        'lineup_players' => ['lineup_id', 'player_id', 'is_starter', 'position', 'x', 'y'],
    ];

    /**
     * 按固定表头读取并校验 CSV，全部通过后写入；任一行失败则整体回滚。
     * @return array{rows:int, inserted:int, dry:bool}
     */
    public function importFile(string $kind, string $path, bool $dryRun = false): array
    {
        if (!isset(self::IMPORT_FILES[$kind])) {
            throw new DomainException('未知的导入类型');
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new DomainException('导入文件不存在');
        }
        if (filesize($path) > 2 * 1024 * 1024) {
            throw new DomainException('导入文件不能超过 2 MB');
        }

        [$headers, $rows, $errors] = $this->readCsv($path, self::IMPORT_HEADERS[$kind]);
        if ($errors) {
            throw new CImportException($errors);
        }

        $errors = [];
        $this->db->beginTransaction();
        try {
            $inserted = match ($kind) {
                'teams' => $this->importTeams($rows),
                'players' => $this->importPlayers($rows),
                'squads' => $this->importSquads($rows),
                'lineups' => $this->importLineups($rows),
                'lineup_players' => $this->importLineupPlayers($rows),
            };
            if ($errors) {
                throw new CImportException($errors);
            }
            if ($dryRun) {
                $this->db->rollBack();
            } else {
                $this->db->commit();
            }
            return ['rows' => count($rows), 'inserted' => $inserted, 'dry' => $dryRun];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** @return array{0:string[],1:array<int,array<string,string>>,2:string[]} */
    private function readCsv(string $path, array $expected): array
    {
        $fh = fopen($path, 'rb');
        if (!$fh) {
            return [[], [], ['无法打开文件']];
        }
        $headers = fgetcsv($fh);
        if ($headers === false) {
            fclose($fh);
            return [[], [], ['文件为空']];
        }
        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }
        $headers = array_map('trim', $headers);
        if ($headers !== $expected) {
            fclose($fh);
            return [$headers, [], ['表头应为：' . implode(',', $expected)]];
        }
        $rows = [];
        $errors = [];
        $line = 1;
        while (($data = fgetcsv($fh)) !== false) {
            $line++;
            if (count($data) === 1 && trim((string) $data[0]) === '') {
                continue;
            }
            if (count($data) !== count($expected)) {
                $errors[] = "第 {$line} 行：列数应为 " . count($expected) . '，实际为 ' . count($data);
                continue;
            }
            $rows[$line] = array_combine($expected, array_map('trim', $data));
        }
        fclose($fh);
        if (!$rows && !$errors) {
            $errors[] = '文件没有数据行';
        }
        return [$headers, $rows, $errors];
    }

    private function importTeams(array $rows): int
    {
        $n = 0;
        $seenIds = [];
        $seenCodes = [];
        foreach ($rows as $line => $r) {
            $id = filter_var($r['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new CImportException(["第 {$line} 行：id 必须是正整数"]);
            }
            if (isset($seenIds[$id])) {
                throw new CImportException(["第 {$line} 行：文件内 id 重复"]);
            }
            $seenIds[$id] = true;
            if ($r['name'] === '' || mb_strlen($r['name']) > 80) {
                throw new CImportException(["第 {$line} 行：球队名称必填且不超过 80 字"]);
            }
            if (!preg_match('/^[A-Z]{3}$/D', $r['country_code'])) {
                throw new CImportException(["第 {$line} 行：国家代码必须是 3 位大写字母"]);
            }
            if (isset($seenCodes[$r['country_code']])
                || $this->row('SELECT id FROM teams WHERE country_code = ?', [$r['country_code']])) {
                throw new CImportException(["第 {$line} 行：国家代码重复"]);
            }
            $seenCodes[$r['country_code']] = true;
            if ($this->teamFind($id)) {
                throw new CImportException(["第 {$line} 行：球队 id 已存在"]);
            }
            $this->q('INSERT INTO teams(id, name, name_en, country_code) VALUES(?,?,?,?)',
                [$id, $r['name'], $r['name_en'], $r['country_code']]);
            $n++;
        }
        return $n;
    }

    private function importPlayers(array $rows): int
    {
        $n = 0;
        $seen = [];
        foreach ($rows as $line => $r) {
            $id = filter_var($r['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new CImportException(["第 {$line} 行：id 必须是正整数"]);
            }
            if (isset($seen[$id])) {
                throw new CImportException(["第 {$line} 行：文件内 id 重复"]);
            }
            $seen[$id] = true;
            if ($r['name'] === '' || mb_strlen($r['name']) > 100) {
                throw new CImportException(["第 {$line} 行：球员姓名必填且不超过 100 字"]);
            }
            if ($r['nationality'] === '' || mb_strlen($r['nationality']) > 60) {
                throw new CImportException(["第 {$line} 行：国籍必填"]);
            }
            if (!in_array($r['position'], ['GK', 'DF', 'MF', 'FW'], true)) {
                throw new CImportException(["第 {$line} 行：位置必须是 GK/DF/MF/FW"]);
            }
            $dob = null;
            if ($r['birth_date'] !== '') {
                $dt = DateTime::createFromFormat('!Y-m-d', $r['birth_date']);
                if (!$dt || $dt->format('Y-m-d') !== $r['birth_date'] || $dt > new DateTime('today')) {
                    throw new CImportException(["第 {$line} 行：出生日期格式应为 YYYY-MM-DD 且不能晚于今天"]);
                }
                $dob = $r['birth_date'];
            }
            if ($this->playerFind($id)) {
                throw new CImportException(["第 {$line} 行：球员 id 已存在"]);
            }
            $this->q('INSERT INTO players(id, name, name_en, birth_date, nationality, position)
                      VALUES(?,?,?,?,?,?)',
                [$id, $r['name'], $r['name_en'], $dob, $r['nationality'], $r['position']]);
            $n++;
        }
        return $n;
    }

    private function importSquads(array $rows): int
    {
        $n = 0;
        $seenPlayer = [];
        $seenNumber = [];
        foreach ($rows as $line => $r) {
            $comp = $this->positiveInt($r['competition_id'], $line, '届次 id ');
            $team = $this->positiveInt($r['team_id'], $line, '球队 id ');
            $player = $this->positiveInt($r['player_id'], $line, '球员 id ');
            $num = $this->positiveInt($r['shirt_number'], $line, '号码');
            if (!$this->competitionFind($comp) || !$this->teamFind($team) || !$this->playerFind($player)) {
                throw new CImportException(["第 {$line} 行：届次、球队或球员不存在"]);
            }
            if ($num < 1 || $num > 99) {
                throw new CImportException(["第 {$line} 行：号码必须在 1–99 之间"]);
            }
            if (isset($seenPlayer["$comp:$player"])
                || $this->row('SELECT 1 FROM squads WHERE competition_id = ? AND player_id = ?',
                    [$comp, $player])) {
                throw new CImportException(["第 {$line} 行：该球员在本届重复出现"]);
            }
            if (isset($seenNumber["$comp:$team:$num"])
                || $this->row('SELECT 1 FROM squads WHERE competition_id = ? AND team_id = ? AND shirt_number = ?',
                    [$comp, $team, $num])) {
                throw new CImportException(["第 {$line} 行：本队号码重复"]);
            }
            $seenPlayer["$comp:$player"] = true;
            $seenNumber["$comp:$team:$num"] = true;
            $this->q('INSERT INTO squads(competition_id, team_id, player_id, shirt_number) VALUES(?,?,?,?)',
                [$comp, $team, $player, $num]);
            $n++;
        }
        return $n;
    }

    private function importLineups(array $rows): int
    {
        $n = 0;
        $seen = [];
        foreach ($rows as $line => $r) {
            $id = $this->positiveInt($r['id'], $line, '阵容 id ');
            $matchId = $this->positiveInt($r['match_id'], $line, '比赛 id ');
            $teamId = $this->positiveInt($r['team_id'], $line, '球队 id ');
            $match = $this->matchFind($matchId);
            if (!$match) {
                throw new CImportException(["第 {$line} 行：比赛不存在"]);
            }
            if ($teamId !== (int) $match['home_team_id'] && $teamId !== (int) $match['away_team_id']) {
                throw new CImportException(["第 {$line} 行：球队不参加该场比赛"]);
            }
            if (!$this->validFormation($r['formation'])) {
                throw new CImportException(["第 {$line} 行：阵型格式不正确"]);
            }
            $key = "$matchId:$teamId";
            if (isset($seen[$key])
                || $this->lineupFor($matchId, $teamId)
                || $this->row('SELECT id FROM lineups WHERE id = ?', [$id])) {
                throw new CImportException(["第 {$line} 行：阵容或 id 重复"]);
            }
            $seen[$key] = true;
            $this->q('INSERT INTO lineups(id, match_id, team_id, formation) VALUES(?,?,?,?)',
                [$id, $matchId, $teamId, $r['formation']]);
            $n++;
        }
        return $n;
    }

    private function importLineupPlayers(array $rows): int
    {
        $n = 0;
        $seen = [];
        $starters = [];
        $bench = [];
        foreach ($rows as $line => $r) {
            $lineupId = $this->positiveInt($r['lineup_id'], $line, '阵容 id ');
            $playerId = $this->positiveInt($r['player_id'], $line, '球员 id ');
            $lineup = $this->row('SELECT * FROM lineups WHERE id = ?', [$lineupId]);
            if (!$lineup) {
                throw new CImportException(["第 {$line} 行：阵容不存在"]);
            }
            $match = $this->matchFind((int) $lineup['match_id']);
            $inSquad = $this->row(
                'SELECT 1 FROM squads WHERE competition_id = ? AND team_id = ? AND player_id = ?',
                [(int) $match['competition_id'], (int) $lineup['team_id'], $playerId]
            );
            if (!$inSquad) {
                throw new CImportException(["第 {$line} 行：球员不属于该阵容所在球队的届次名单"]);
            }
            if (!in_array($r['position'], ['GK', 'DF', 'MF', 'FW'], true)) {
                throw new CImportException(["第 {$line} 行：位置无效"]);
            }
            if (!in_array($r['is_starter'], ['0', '1'], true)) {
                throw new CImportException(["第 {$line} 行：is_starter 只能是 0 或 1"]);
            }
            foreach (['x', 'y'] as $axis) {
                if ($r[$axis] !== '' && (!ctype_digit($r[$axis]) || (int) $r[$axis] > 100)) {
                    throw new CImportException(["第 {$line} 行：{$axis} 必须是 0–100 的整数或留空"]);
                }
            }
            if (isset($seen["$lineupId:$playerId"])) {
                throw new CImportException(["第 {$line} 行：同一阵容内球员重复"]);
            }
            $seen["$lineupId:$playerId"] = true;
            if ($r['is_starter'] === '1') {
                $starters[$lineupId][] = $r['position'];
            } else {
                $bench[$lineupId] = ($bench[$lineupId] ?? 0) + 1;
            }
            $this->q(
                'INSERT INTO lineup_players(lineup_id, player_id, is_starter, position, x, y)
                 VALUES(?,?,?,?,?,?)',
                [$lineupId, $playerId, (int) $r['is_starter'], $r['position'],
                 $r['x'] === '' ? null : (int) $r['x'], $r['y'] === '' ? null : (int) $r['y']]
            );
            $n++;
        }
        foreach (array_unique(array_merge(array_keys($starters), array_keys($bench))) as $lineupId) {
            $positions = $starters[$lineupId] ?? [];
            if (count($positions) !== 11) {
                throw new CImportException(["阵容 {$lineupId}：首发必须正好 11 人"]);
            }
            if (count(array_filter($positions, fn($p) => $p === 'GK')) !== 1) {
                throw new CImportException(["阵容 {$lineupId}：首发必须有且只有 1 名门将"]);
            }
            if (($bench[$lineupId] ?? 0) > 12) {
                throw new CImportException(["阵容 {$lineupId}：替补最多 12 人"]);
            }
        }
        return $n;
    }

    /** 导入时严格要求正整数，避免 (int) 把 "1abc" 悄悄当成 1。 */
    private function positiveInt(string $value, int $line, string $label): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new CImportException(["第 {$line} 行：{$label}必须是正整数"]);
        }
        return (int) $id;
    }
}
