<?php
/**
 * 成员 C | 视图：一场比赛的双方阵容。
 *
 * 期望变量：$match、$sides、$competition
 * $sides 每项包含 team_id、name、lineup、players；players 含首发与替补。
 * 说明：阵型按首发位置推算时会在页面明确标注；替补仅覆盖数据源记录的登场替补。
 */
?>
<p class="eyebrow">LINEUP / <?= c_h($match['stage']) ?></p>
<h1><?= c_h($match['home_name']) ?> <span class="muted">vs</span> <?= c_h($match['away_name']) ?></h1>
<p class="lead">
  <?= c_h(substr($match['kickoff_utc'], 0, 16)) ?> UTC · <?= c_score($match) ?>
  <?php if ($match['venue']): ?> · <?= c_h($match['venue']) ?><?php endif; ?>
</p>

<div class="lineup-grid">
<?php foreach ($sides as $side): ?>
  <?php
    $starters = array_values(array_filter($side['players'], fn($p) => (int) $p['is_starter'] === 1));
    $bench = array_values(array_filter($side['players'], fn($p) => (int) $p['is_starter'] === 0));
  ?>
  <section class="lineup-side">
    <h2>
      <a href="<?= c_h(c_url(['p' => 'team', 'id' => $side['team_id']])) ?>"><?= c_h($side['name']) ?></a>
    </h2>
    <?php if (!$side['lineup']): ?>
      <p class="empty">阵容未公布。比赛开始前或数据未录入时，这里不会填充任何球员。</p>
    <?php else: ?>
      <p class="formation">
        阵型 <strong><?= c_h($side['lineup']['formation']) ?></strong>
        <span class="muted">（按首发位置推算，仅作展示）</span>
        · 首发 <?= count($starters) ?> 人 · 替补 <?= count($bench) ?> 人
      </p>

      <div class="pitch" aria-label="<?= c_h($side['name']) ?> 首发阵型图">
        <?php foreach ($starters as $player): ?>
          <?php if ($player['x'] === null || $player['y'] === null) continue; ?>
          <a class="pitch-player <?= c_position_class($player['position']) ?>"
             style="left:<?= (int) $player['x'] ?>%;top:<?= (int) $player['y'] ?>%"
             href="<?= c_h(c_url(['p' => 'player', 'id' => $player['id']])) ?>"
             title="<?= c_h($player['name'] . ' · ' . c_position_label($player['position'])) ?>">
            <span class="shirt"><?= (int) $player['shirt_number'] ?></span>
            <span class="pname"><?= c_h(trim((string) preg_replace('/^.*\s/u', '', $player['name']))) ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="table-wrap">
        <table class="compact">
          <thead><tr><th>号码</th><th>首发</th><th>位置</th></tr></thead>
          <tbody>
          <?php foreach ($starters as $player): ?>
            <tr>
              <td class="num"><?= (int) $player['shirt_number'] ?></td>
              <td><a href="<?= c_h(c_url(['p' => 'player', 'id' => $player['id']])) ?>"><?= c_h($player['name']) ?></a></td>
              <td><span class="badge <?= c_position_class($player['position']) ?>"><?= c_position_label($player['position']) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h3>替补</h3>
      <?php if (!$bench): ?>
        <p class="empty small">数据源只记录登场替补；本场没有可展示的替补记录。</p>
      <?php else: ?>
        <ul class="bench-list">
          <?php foreach ($bench as $player): ?>
            <li>
              <span class="num"><?= (int) $player['shirt_number'] ?></span>
              <a href="<?= c_h(c_url(['p' => 'player', 'id' => $player['id']])) ?>"><?= c_h($player['name']) ?></a>
              <span class="badge <?= c_position_class($player['position']) ?>"><?= c_position_label($player['position']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>

<p><a href="<?= c_h(c_url(['p' => 'matches'])) ?>">← 返回比赛列表</a></p>
