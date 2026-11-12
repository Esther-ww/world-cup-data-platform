<?php
/**
 * 成员 C | 视图（公共布局）：页头、导航、提示与页脚。
 *
 * 项目：世界杯数据平台（课程团队大作业）
 * 说明：本文件只负责 HTML 结构与展示辅助函数，不访问数据库。
 *       所有动态文本通过 c_h() 转义；表单写操作携带 CSRF 令牌。
 */

function c_csrf_input(): string
{
    return '<input type="hidden" name="csrf" value="' . c_h($_SESSION['csrf'] ?? '') . '">';
}

function c_position_label(string $code): string
{
    return ['GK' => '门将', 'DF' => '后卫', 'MF' => '中场', 'FW' => '前锋'][$code] ?? $code;
}

function c_position_class(string $code): string
{
    return 'pos-' . strtolower(in_array($code, ['GK', 'DF', 'MF', 'FW'], true) ? $code : 'other');
}

function c_date(?string $date): string
{
    return $date ? c_h($date) : '未收录';
}

function c_age(?string $birthDate, string $on = '2014-06-12'): string
{
    if (!$birthDate) {
        return '—';
    }
    try {
        $birth = new DateTime($birthDate);
        $at = new DateTime($on);
        return $at->format('Y') - $birth->format('Y')
            - (($at->format('md') < $birth->format('md')) ? 1 : 0) . ' 岁';
    } catch (Throwable $e) {
        return '—';
    }
}

function c_score(array $match): string
{
    if ($match['status'] !== 'finished' || $match['home_score'] === null || $match['away_score'] === null) {
        return '<span class="muted">未开始</span>';
    }
    return c_h($match['home_score'] . ' : ' . $match['away_score']);
}

function c_page_head(string $title, ?array $me, bool $admin, string $active): void
{
    $nav = ['teams' => '球队', 'players' => '球员', 'matches' => '比赛阵容'];
    ?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= c_h($title) ?> · 世界杯数据平台</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header>
  <a class="brand" href="<?= c_h(c_url(['p' => 'teams'])) ?>"><strong>世界杯数据平台</strong><span>球队 · 球员 · 阵容</span></a>
  <nav>
    <?php foreach ($nav as $key => $label): ?>
      <a href="<?= c_h(c_url(['p' => $key])) ?>"<?= $active === $key ? ' class="active"' : '' ?>><?= c_h($label) ?></a>
    <?php endforeach; ?>
    <?php if ($admin): ?>
      <a href="<?= c_h(c_url(['p' => 'admin', 'tab' => 'team'])) ?>"<?= $active === 'admin' ? ' class="active"' : '' ?>>管理后台</a>
    <?php endif; ?>
  </nav>
  <div class="account">
    <?php if ($me): ?>
      <span><?= c_h($me['username']) ?> · <?= $admin ? '管理员' : '普通用户' ?></span>
      <form method="post" class="inline">
        <?= c_csrf_input() ?>
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="link-button">退出</button>
      </form>
    <?php else: ?>
      <a href="<?= c_h(c_url(['p' => 'auth'])) ?>">管理员登录</a>
    <?php endif; ?>
  </div>
</header>
<main>
<?php if (!empty($GLOBALS['flash'])): ?>
  <p role="status" class="flash"><?= c_h($GLOBALS['flash']) ?></p>
<?php endif; ?>
<?php
}

function c_page_foot(): void
{
    ?>
</main>
<footer>
  <p>成员 C 独立模块：球队、球员、届次名单与阵容。页面数据为 2014 年巴西世界杯 A/B 组演示子集（8 队、12 场），
     不是赛事全量数据；比赛时间统一显示为 UTC。</p>
  <p>数据来源：<a href="https://github.com/openfootball/world-cup" rel="noreferrer">openfootball/world-cup</a>、
     <a href="https://github.com/openfootball/worldcup.json" rel="noreferrer">openfootball/worldcup.json</a>、
     <a href="https://github.com/openfootball/players" rel="noreferrer">openfootball/players</a>（CC0）。
     字段覆盖与采集日期见 <code>data/import/README.md</code>。</p>
</footer>
</body>
</html>
<?php
}
