<?php
/**
 * 成员 C | 视图：比赛列表，作为阵容页面的入口。
 *
 * 期望变量：$matches、$competition
 * 说明：正式项目中比赛列表由成员 B 的赛程模块提供，本页仅服务独立演示。
 */
?>
<p class="eyebrow">MATCHES / <?= c_h($competition['name'] ?? '演示届次') ?></p>
<h1>比赛与阵容</h1>
<p class="lead">选择一场比赛查看双方阵型、首发与替补。阵容未公布时页面显示空状态。</p>

<?php if (!$matches): ?>
  <p class="empty">暂无比赛数据。</p>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>日期（UTC）</th><th>阶段</th><th>主队</th><th>比分</th><th>客队</th><th>阵容</th></tr></thead>
      <tbody>
      <?php foreach ($matches as $match): ?>
        <tr>
          <td><?= c_h(substr($match['kickoff_utc'], 0, 10)) ?></td>
          <td><?= c_h($match['stage']) ?></td>
          <td><?= c_h($match['home_name']) ?></td>
          <td><?= c_score($match) ?></td>
          <td><?= c_h($match['away_name']) ?></td>
          <td>
            <a href="<?= c_h(c_url(['p' => 'lineup', 'match' => $match['id']])) ?>">查看阵容</a>
            <?php if ((int) $match['lineup_count'] === 0): ?>
              <span class="muted">（未公布）</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
