<?php
/**
 * 成员 C | 控制器（Controller）：球队、球员、届次名单与阵容的路由、权限与校验。
 *
 * 项目：世界杯数据平台（课程团队大作业）
 * 入口：public/index.php
 * 约定：
 *   * 所有写操作必须携带会话 CSRF 令牌，并且只有管理员可以执行；
 *   * 参数在此处集中校验，SQL 全部交给 PlayerModel；
 *   * 输出统一由视图的 c_h() 转义。
 *
 * 独立演示中的 users 登录是占位实现，联调时替换为成员 A 的账号模块；
 * 本控制器只依赖 $_SESSION['uid'] 与 users.role。
 */

require_once __DIR__ . '/PlayerModel.php';

session_start([
    'use_strict_mode' => 1,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

/* --------------------------------------------------------------------------
 * 小型请求/校验/渲染辅助函数（c_ 前缀避免与其他成员模块合并时重名）
 * -------------------------------------------------------------------------- */

function c_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function c_url(array $params): string
{
    return '?' . http_build_query($params);
}

function c_get_str(string $key, int $max = 100): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
}

function c_get_int(string $key, int $default = 0): int
{
    $value = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT);
    return $value === false ? $default : (int) $value;
}

function c_post_str(string $key, int $max, bool $required = true, string $label = ''): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        throw new DomainException(($label ?: $key) . '格式不正确');
    }
    $value = trim($value);
    if ($required && $value === '') {
        throw new DomainException(($label ?: $key) . '不能为空');
    }
    if (mb_strlen($value) > $max) {
        throw new DomainException(($label ?: $key) . '长度超过限制');
    }
    return $value;
}

function c_post_int(string $key, int $min = 0, bool $required = true, string $label = ''): int
{
    $raw = $_POST[$key] ?? '';
    if ($raw === '' && !$required) {
        return 0;
    }
    $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min]]);
    if ($value === false) {
        throw new DomainException(($label ?: $key) . '必须是大于等于 ' . $min . ' 的整数');
    }
    return (int) $value;
}

function c_need(bool $ok): void
{
    if (!$ok) {
        c_fail(403, '无权执行此操作，请使用管理员账号登录。');
    }
}

function c_fail(int $status, string $message): void
{
    global $m, $me, $admin, $page;
    http_response_code($status);
    require_once dirname(__DIR__) . '/views/layout.php';
    c_page_head('操作未完成', $me, $admin, $page);
    echo '<h1>操作未完成</h1><p role="alert">' . c_h($message) . '</p>';
    echo '<p><a href="' . c_h(c_url(['p' => 'teams'])) . '">返回球队列表</a></p>';
    c_page_foot();
    exit;
}

function c_redirect(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

/* --------------------------------------------------------------------------
 * 初始化与当前用户
 * -------------------------------------------------------------------------- */

$m = new PlayerModel();
$me = null;
if (!empty($_SESSION['uid'])) {
    $me = $m->row('SELECT id, username, role FROM users WHERE id = ?', [$_SESSION['uid']]);
}
$admin = ($me['role'] ?? '') === 'admin';
$page = preg_match('/^[a-z_]+$/D', (string) ($_GET['p'] ?? '')) ? $_GET['p'] : 'teams';
$tab = preg_match('/^[a-z_]+$/D', (string) ($_GET['tab'] ?? '')) ? $_GET['tab'] : 'team';
$flash = $_SESSION['flash'] ?? null;
$importErrors = $_SESSION['import_errors'] ?? [];
unset($_SESSION['flash'], $_SESSION['import_errors']);
// 视图在函数作用域中渲染，通过全局变量传递一次性提示。
$GLOBALS['flash'] = $flash;

try {
    /* ----------------------------------------------------------------------
     * POST：先校验 CSRF，再按 action 分发
     * ---------------------------------------------------------------------- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            c_fail(403, 'CSRF 令牌无效，请刷新页面后重试。');
        }
        $action = c_post_str('action', 32, true, '操作');
        $dest = ['p' => 'teams'];

        switch ($action) {
            case 'login':
                $username = c_post_str('username', 48, true, '用户名');
                $password = $_POST['password'] ?? '';
                if (!is_string($password) || strlen($password) > 72) {
                    throw new DomainException('密码格式不正确');
                }
                $user = $m->row('SELECT * FROM users WHERE username = ?', [$username]);
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    throw new DomainException('用户名或密码错误');
                }
                session_regenerate_id(true);
                $_SESSION['uid'] = (int) $user['id'];
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                $_SESSION['flash'] = '已登录：' . $user['username'];
                $dest = $user['role'] === 'admin' ? ['p' => 'admin', 'tab' => 'team'] : ['p' => 'teams'];
                c_redirect(c_url($dest));
                break;

            case 'logout':
                $_SESSION = [];
                session_regenerate_id(true);
                c_redirect(c_url(['p' => 'teams']));
                break;

            case 'teamSave':
                c_need($admin);
                $id = c_post_int('id', 0, false);
                $name = c_post_str('name', 80, true, '球队名称');
                $nameEn = c_post_str('name_en', 80, false);
                $code = strtoupper(c_post_str('country_code', 3, true, '国家代码'));
                if (!preg_match('/^[A-Z]{3}$/D', $code)) {
                    throw new DomainException('国家代码必须是 3 位大写字母，例如 BRA');
                }
                $m->teamSave($id, $name, $nameEn, $code);
                $_SESSION['flash'] = $id > 0 ? '球队已更新。' : '球队已创建。';
                c_redirect(c_url(['p' => 'admin', 'tab' => 'team']));
                break;

            case 'teamDelete':
                c_need($admin);
                $m->teamDelete(c_post_int('id', 1, true, '球队'));
                $_SESSION['flash'] = '球队已删除。';
                c_redirect(c_url(['p' => 'admin', 'tab' => 'team']));
                break;

            case 'playerSave':
                c_need($admin);
                $id = c_post_int('id', 0, false);
                $name = c_post_str('name', 100, true, '球员姓名');
                $nameEn = c_post_str('name_en', 100, false);
                $dob = c_post_str('birth_date', 10, false);
                if ($dob !== '') {
                    $dt = DateTime::createFromFormat('!Y-m-d', $dob);
                    if (!$dt || $dt->format('Y-m-d') !== $dob || $dt > new DateTime('today')) {
                        throw new DomainException('出生日期格式应为 YYYY-MM-DD，且不能晚于今天');
                    }
                }
                $nationality = c_post_str('nationality', 60, true, '国籍');
                $position = c_post_str('position', 2, true, '位置');
                if (!in_array($position, ['GK', 'DF', 'MF', 'FW'], true)) {
                    throw new DomainException('位置必须是 GK/DF/MF/FW');
                }
                $m->playerSave($id, $name, $nameEn, $dob === '' ? null : $dob, $nationality, $position);
                $_SESSION['flash'] = $id > 0 ? '球员已更新。' : '球员已创建。';
                c_redirect(c_url(['p' => 'admin', 'tab' => 'player']));
                break;

            case 'playerDelete':
                c_need($admin);
                $m->playerDelete(c_post_int('id', 1, true, '球员'));
                $_SESSION['flash'] = '球员已删除。';
                c_redirect(c_url(['p' => 'admin', 'tab' => 'player']));
                break;

            case 'squadAdd':
                c_need($admin);
                $comp = c_post_int('competition_id', 1, true, '届次');
                $team = c_post_int('team_id', 1, true, '球队');
                $player = c_post_int('player_id', 1, true, '球员');
                $number = c_post_int('shirt_number', 1, true, '号码');
                $m->squadAdd($comp, $team, $player, $number);
                $_SESSION['flash'] = '球员已加入届次名单。';
                c_redirect(c_url(['p' => 'admin', 'tab' => 'squad', 'team' => $team, 'comp' => $comp]));
                break;

            case 'squadDelete':
                c_need($admin);
                $comp = c_post_int('competition_id', 1, true, '届次');
                $team = c_post_int('team_id', 1, true, '球队');
                $player = c_post_int('player_id', 1, true, '球员');
                $m->squadDelete($comp, $team, $player);
                $_SESSION['flash'] = '球员已移出届次名单。';
                c_redirect(c_url(['p' => 'admin', 'tab' => 'squad', 'team' => $team, 'comp' => $comp]));
                break;

            case 'lineupSave':
                c_need($admin);
                $matchId = c_post_int('match_id', 1, true, '比赛');
                $teamId = c_post_int('team_id', 1, true, '球队');
                $formation = c_post_str('formation', 16, false);
                $roles = is_array($_POST['role'] ?? null) ? $_POST['role'] : [];
                $positions = is_array($_POST['pos'] ?? null) ? $_POST['pos'] : [];
                $cleanRoles = [];
                foreach ($roles as $pid => $role) {
                    $pid = (int) $pid;
                    if ($pid > 0 && is_string($role)) {
                        $cleanRoles[$pid] = $role;
                    }
                }
                $cleanPositions = [];
                foreach ($positions as $pid => $pos) {
                    $pid = (int) $pid;
                    if ($pid > 0 && is_string($pos)) {
                        $cleanPositions[$pid] = $pos;
                    }
                }
                $m->lineupSave($matchId, $teamId, $formation, $cleanRoles, $cleanPositions);
                $_SESSION['flash'] = '阵容已保存。';
                c_redirect(c_url(['p' => 'admin', 'tab' => 'lineup', 'match' => $matchId, 'team' => $teamId]));
                break;

            case 'lineupDelete':
                c_need($admin);
                $matchId = c_post_int('match_id', 1, true, '比赛');
                $teamId = c_post_int('team_id', 1, true, '球队');
                $m->lineupDelete($matchId, $teamId);
                $_SESSION['flash'] = '阵容已撤下，前台将显示未公布。';
                c_redirect(c_url(['p' => 'admin', 'tab' => 'lineup', 'match' => $matchId, 'team' => $teamId]));
                break;

            case 'import':
                c_need($admin);
                $kind = c_post_str('kind', 32, true, '导入类型');
                $dry = ($_POST['dry'] ?? '') === '1';
                if (!isset(PlayerModel::IMPORT_FILES[$kind])) {
                    throw new DomainException('未知的导入类型');
                }
                $path = dirname(__DIR__) . '/data/import/' . PlayerModel::IMPORT_FILES[$kind];
                try {
                    $result = $m->importFile($kind, $path, $dry);
                    $_SESSION['flash'] = sprintf(
                        '%s：%d 行%s。',
                        PlayerModel::IMPORT_FILES[$kind],
                        $result['rows'],
                        $dry ? '校验通过（未写入）' : '导入成功'
                    );
                } catch (CImportException $e) {
                    $_SESSION['import_errors'] = $e->errors;
                    $_SESSION['flash'] = '导入未写入，请修正以下问题后重试。';
                }
                c_redirect(c_url(['p' => 'admin', 'tab' => 'import']));
                break;

            default:
                c_fail(400, '未知操作');
        }
    }

    /* ----------------------------------------------------------------------
     * GET：公共页面
     * ---------------------------------------------------------------------- */
    require_once dirname(__DIR__) . '/views/layout.php';

    switch ($page) {
        case 'teams':
            $competition = $m->currentCompetition();
            $competitionId = (int) ($competition['id'] ?? 1);
            $q = c_get_str('q', 50);
            $teams = $m->teamList($competitionId, $q);
            c_page_head('球队', $me, $admin, $page);
            require dirname(__DIR__) . '/views/team/index.php';
            c_page_foot();
            break;

        case 'team':
            $competition = $m->currentCompetition();
            $competitionId = (int) ($competition['id'] ?? 1);
            $team = $m->teamFind(c_get_int('id'));
            if (!$team) {
                c_fail(404, '球队不存在。');
            }
            $squad = $m->teamSquad($competitionId, (int) $team['id']);
            $teamMatches = $m->teamMatches($competitionId, (int) $team['id']);
            c_page_head($team['name'], $me, $admin, $page);
            require dirname(__DIR__) . '/views/team/detail.php';
            c_page_foot();
            break;

        case 'players':
            $competition = $m->currentCompetition();
            $competitionId = (int) ($competition['id'] ?? 1);
            $q = c_get_str('q', 50);
            $teamId = c_get_int('team');
            $position = c_get_str('position', 2);
            if (!in_array($position, ['', 'GK', 'DF', 'MF', 'FW'], true)) {
                $position = '';
            }
            $perPage = 20;
            $total = $m->playerSearchCount($competitionId, $q, $teamId, $position);
            $pages = max(1, (int) ceil($total / $perPage));
            $current = min(max(1, c_get_int('page', 1)), $pages);
            $players = $m->playerSearch($competitionId, $q, $teamId, $position,
                $perPage, ($current - 1) * $perPage);
            $teams = $m->teamList($competitionId);
            c_page_head('球员', $me, $admin, $page);
            require dirname(__DIR__) . '/views/player/index.php';
            c_page_foot();
            break;

        case 'player':
            $competition = $m->currentCompetition();
            $competitionId = (int) ($competition['id'] ?? 1);
            $player = $m->playerFind(c_get_int('id'));
            if (!$player) {
                c_fail(404, '球员不存在。');
            }
            $squadInfo = $m->playerSquadInfo((int) $player['id'], $competitionId);
            $appearances = $m->playerAppearances((int) $player['id']);
            c_page_head($player['name'], $me, $admin, $page);
            require dirname(__DIR__) . '/views/player/detail.php';
            c_page_foot();
            break;

        case 'matches':
            $competition = $m->currentCompetition();
            $competitionId = (int) ($competition['id'] ?? 1);
            $matches = $m->matchList($competitionId);
            c_page_head('比赛阵容', $me, $admin, $page);
            require dirname(__DIR__) . '/views/match/index.php';
            c_page_foot();
            break;

        case 'lineup':
            $competition = $m->currentCompetition();
            $competitionId = (int) ($competition['id'] ?? 1);
            $match = $m->matchFind(c_get_int('match'));
            if (!$match) {
                c_fail(404, '比赛不存在。');
            }
            $sides = [];
            foreach ([
                ['team_id' => (int) $match['home_team_id'], 'name' => $match['home_name']],
                ['team_id' => (int) $match['away_team_id'], 'name' => $match['away_name']],
            ] as $side) {
                $lineup = $m->lineupFor((int) $match['id'], $side['team_id']);
                $side['lineup'] = $lineup;
                $side['players'] = $lineup
                    ? $m->lineupPlayers((int) $lineup['id'], $competitionId, $side['team_id'])
                    : [];
                $sides[] = $side;
            }
            c_page_head('比赛阵容', $me, $admin, $page);
            require dirname(__DIR__) . '/views/match/lineup.php';
            c_page_foot();
            break;

        case 'auth':
            c_page_head('登录', $me, $admin, $page);
            require dirname(__DIR__) . '/views/auth.php';
            c_page_foot();
            break;

        /* ------------------------------------------------------------------
         * GET：管理后台
         * ------------------------------------------------------------------ */
        case 'admin':
            c_need($admin);
            $competition = $m->currentCompetition();
            $competitionId = (int) ($competition['id'] ?? 1);
            $data = ['competition' => $competition, 'competition_id' => $competitionId];
            if ($tab === 'team') {
                $data['teams'] = $m->adminTeamList();
                $data['edit'] = c_get_int('edit') ? $m->teamFind(c_get_int('edit')) : null;
            } elseif ($tab === 'player') {
                $q = c_get_str('q', 50);
                $total = $m->playerSearchCount($competitionId, $q, 0, '');
                $perPage = 15;
                $pages = max(1, (int) ceil($total / $perPage));
                $current = min(max(1, c_get_int('page', 1)), $pages);
                $data['players'] = $m->playerSearch($competitionId, $q, 0, '',
                    $perPage, ($current - 1) * $perPage);
                $data['total'] = $total;
                $data['pages'] = $pages;
                $data['current'] = $current;
                $data['q'] = $q;
                $data['edit'] = c_get_int('edit') ? $m->playerFind(c_get_int('edit')) : null;
            } elseif ($tab === 'squad') {
                $data['teams'] = $m->adminTeamList();
                $teamId = c_get_int('team');
                if (!$teamId && $data['teams']) {
                    $teamId = (int) $data['teams'][0]['id'];
                }
                $data['team_id'] = $teamId;
                $data['team'] = $teamId ? $m->teamFind($teamId) : null;
                $data['squad'] = $teamId ? $m->teamSquad($competitionId, $teamId) : [];
                $data['available'] = $m->squadAvailablePlayers($competitionId);
            } elseif ($tab === 'lineup') {
                $data['matches'] = $m->matchList($competitionId);
                $matchId = c_get_int('match');
                if (!$matchId && $data['matches']) {
                    $matchId = (int) $data['matches'][0]['id'];
                }
                $data['match_id'] = $matchId;
                $data['match'] = $matchId ? $m->matchFind($matchId) : null;
                $teamId = c_get_int('team');
                if ($data['match']) {
                    $valid = [(int) $data['match']['home_team_id'], (int) $data['match']['away_team_id']];
                    if (!in_array($teamId, $valid, true)) {
                        $teamId = $valid[0];
                    }
                }
                $data['team_id'] = $teamId;
                $data['squad'] = $data['match'] ? $m->teamSquad($competitionId, $teamId) : [];
                $data['lineup'] = $data['match'] ? $m->lineupFor($matchId, $teamId) : null;
                $data['selected'] = [];
                if ($data['lineup']) {
                    foreach ($m->lineupPlayers((int) $data['lineup']['id'], $competitionId, $teamId) as $p) {
                        $data['selected'][(int) $p['player_id']] = $p;
                    }
                }
            } elseif ($tab === 'import') {
                $data['files'] = [];
                foreach (PlayerModel::IMPORT_FILES as $kind => $file) {
                    $path = dirname(__DIR__) . '/data/import/' . $file;
                    $data['files'][$kind] = [
                        'name' => $file,
                        'exists' => is_file($path),
                        'size' => is_file($path) ? filesize($path) : 0,
                    ];
                }
                $data['errors'] = $importErrors;
            } else {
                c_fail(404, '后台页面不存在');
            }
            c_page_head('管理后台', $me, $admin, 'admin');
            require dirname(__DIR__) . '/views/admin/nav.php';
            require dirname(__DIR__) . '/views/admin/' . $tab . '.php';
            c_page_foot();
            break;

        default:
            c_fail(404, '页面不存在。');
    }
} catch (DomainException $e) {
    c_fail(422, $e->getMessage());
} catch (PDOException $e) {
    error_log('[member-c] ' . $e->getMessage());
    c_fail(500, '数据库操作失败，请检查安装配置或联系管理员。');
} catch (Throwable $e) {
    error_log('[member-c] ' . $e->getMessage());
    c_fail(500, '服务器内部错误。');
}
