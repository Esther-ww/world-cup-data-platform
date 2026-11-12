<?php
/**
 * 成员 C | 视图：球员详情（MVC 三层中的视图交付文件）。
 *
 * 期望变量：$player、$squadInfo、$appearances、$competition
 * 展示：姓名、国籍、出生日期、位置、届次号码/球队，以及本次演示届次的阵容出场记录。
 */
?>
<p class="eyebrow">PLAYER / <?= c_h($player['position']) ?></p>
<h1><?= c_h($player['name']) ?></h1>
<p class="lead">
  <?= c_h($player['nationality']) ?> · <?= c_position_label($player['position']) ?>
  <?php if ($squadInfo): ?>
    · <a href="<?= c_h(c_url(['p' => 'team', 'id' => $squadInfo['team_id']])) ?>"><?= c_h($squadInfo['team_name']) ?></a>
  <?php endif; ?>
</p>

<div class="grid detail-grid">
  <article>
    <h2>资料</h2>
    <dl class="facts">
      <dt>英文名</dt><dd><?= c_h($player['name_en'] ?: $player['name']) ?></dd>
      <dt>国籍</dt><dd><?= c_h($player['nationality']) ?></dd>
      <dt>出生日期</dt><dd><?= c_date($player['birth_date']) ?><?= $player['birth_date'] ? '' : '（数据源未收录）' ?></dd>
      <dt>年龄</dt><dd><?= c_age($player['birth_date']) ?>（<?= c_h($competition['name'] ?? '本届') ?>）</dd>
      <dt>位置</dt><dd><span class="badge <?= c_position_class($player['position']) ?>"><?= c_position_label($player['position']) ?></span></dd>
    </dl>
  </article>
  <article>
    <h2>届次名单</h2>
    <?php if ($squadInfo): ?>
      <dl class="facts">
        <dt>届次</dt><dd><?= c_h($squadInfo['competition_name']) ?></dd>
        <dt>球队</dt><dd><a href="<?= c_h(c_url(['p' => 'team', 'id' => $squadInfo['team_id']])) ?>"><?= c_h($squadInfo['team_name']) ?></a></dd>
        <dt>号码</dt><dd class="num"><?= (int) $squadInfo['shirt_number'] ?></dd>
      </dl>
    <?php else: ?>
      <p class="empty">该球员未进入当前演示届次的球队名单。</p>
    <?php endif; ?>
  </article>
</div>

<h2>阵容出场记录</h2>
<?php if (!$appearances): ?>
  <p class="empty">暂无阵容记录。可能原因：该场阵容未公布，或球员未进入首发/替补名单。</p>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>日期（UTC）</th><th>比赛</th><th>身份</th><th>阵容位置</th><th>阵型</th></tr></thead>
      <tbody>
      <?php foreach ($appearances as $row): ?>
        <tr>
          <td><?= c_h(substr($row['kickoff_utc'], 0, 10)) ?></td>
          <td><a href="<?= c_h(c_url(['p' => 'lineup', 'match' => $row['match_id']])) ?>">
            <?= c_h($row['home_name']) ?> vs <?= c_h($row['away_name']) ?>
          </a></td>
          <td><?= (int) $row['is_starter'] === 1 ? '首发' : '替补' ?></td>
          <td><span class="badge <?= c_position_class($row['lineup_position']) ?>"><?= c_position_label($row['lineup_position']) ?></span></td>
          <td><?= c_h($row['formation']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<p><a href="<?= c_h(c_url(['p' => 'players'])) ?>">← 返回球员列表</a></p>
