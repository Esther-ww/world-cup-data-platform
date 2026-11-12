<?php
/**
 * 成员 C | 视图：球队详情与届次名单。
 *
 * 期望变量：$team、$squad、$teamMatches、$competition
 */
$groups = ['GK' => [], 'DF' => [], 'MF' => [], 'FW' => []];
foreach ($squad as $player) {
    $groups[$player['position']][] = $player;
}
?>
<p class="eyebrow">TEAM / <?= c_h($team['country_code']) ?></p>
<h1><?= c_h($team['name']) ?> <span class="muted"><?= c_h($team['name_en']) ?></span></h1>
<p class="lead"><?= c_h($competition['name'] ?? '') ?> 名单 · 共 <?= count($squad) ?> 人</p>

<h2>届次名单</h2>
<?php if (!$squad): ?>
  <p class="empty">该届次名单尚未录入。管理员可在“管理后台 → 名单管理”中添加球员。</p>
<?php else: ?>
  <div class="squad-groups">
    <?php foreach ($groups as $position => $players): ?>
      <?php if (!$players) continue; ?>
      <section>
        <h3><span class="badge <?= c_position_class($position) ?>"><?= c_position_label($position) ?></span> <?= count($players) ?> 人</h3>
        <div class="table-wrap">
          <table>
            <thead><tr><th>号码</th><th>球员</th><th>出生日期</th><th>年龄</th></tr></thead>
            <tbody>
            <?php foreach ($players as $player): ?>
              <tr>
                <td class="num"><?= (int) $player['shirt_number'] ?></td>
                <td><a href="<?= c_h(c_url(['p' => 'player', 'id' => $player['id']])) ?>"><?= c_h($player['name']) ?></a></td>
                <td><?= c_date($player['birth_date']) ?></td>
                <td><?= c_age($player['birth_date']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2>本届比赛</h2>
<?php if (!$teamMatches): ?>
  <p class="empty">暂无比赛记录。</p>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>日期（UTC）</th><th>阶段</th><th>对阵</th><th>比分</th><th>阵容</th></tr></thead>
      <tbody>
      <?php foreach ($teamMatches as $match): ?>
        <tr>
          <td><?= c_h(substr($match['kickoff_utc'], 0, 10)) ?></td>
          <td><?= c_h($match['stage']) ?></td>
          <td><?= c_h($match['home_name']) ?> vs <?= c_h($match['away_name']) ?></td>
          <td><?= c_score($match) ?></td>
          <td><a href="<?= c_h(c_url(['p' => 'lineup', 'match' => $match['id']])) ?>">查看</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<p><a href="<?= c_h(c_url(['p' => 'teams'])) ?>">← 返回球队列表</a></p>
