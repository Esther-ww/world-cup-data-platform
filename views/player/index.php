<?php
/**
 * 成员 C | 视图：球员列表、姓名搜索与球队/位置筛选。
 *
 * 期望变量：$players、$teams、$q、$teamId、$position、$total、$pages、$current、$competition
 */
$query = ['p' => 'players'];
if ($q !== '') $query['q'] = $q;
if ($teamId > 0) $query['team'] = $teamId;
if ($position !== '') $query['position'] = $position;
?>
<p class="eyebrow">PLAYERS / <?= c_h($competition['name'] ?? '演示届次') ?></p>
<h1>球员</h1>
<p class="lead">支持姓名搜索、球队筛选与位置筛选；共 <?= (int) $total ?> 名球员。出生日期未收录时明确标注，不填造数值。</p>

<form method="get" class="filters">
  <input type="hidden" name="p" value="players">
  <label>姓名
    <input name="q" value="<?= c_h($q) ?>" placeholder="例如 Neymar" maxlength="50">
  </label>
  <label>球队
    <select name="team">
      <option value="0">全部球队</option>
      <?php foreach ($teams as $team): ?>
        <option value="<?= (int) $team['id'] ?>"<?= $teamId === (int) $team['id'] ? ' selected' : '' ?>>
          <?= c_h($team['name']) ?>（<?= c_h($team['country_code']) ?>）
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>位置
    <select name="position">
      <option value="">全部位置</option>
      <?php foreach (['GK', 'DF', 'MF', 'FW'] as $code): ?>
        <option value="<?= $code ?>"<?= $position === $code ? ' selected' : '' ?>><?= c_position_label($code) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button type="submit">筛选</button>
  <?php if ($q !== '' || $teamId > 0 || $position !== ''): ?>
    <a href="<?= c_h(c_url(['p' => 'players'])) ?>">清除</a>
  <?php endif; ?>
</form>

<?php if (!$players): ?>
  <p class="empty">没有匹配的球员。请调整姓名、球队或位置条件。</p>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>球员</th><th>球队</th><th>号码</th><th>位置</th><th>国籍</th><th>出生日期</th></tr></thead>
      <tbody>
      <?php foreach ($players as $player): ?>
        <tr>
          <td><a href="<?= c_h(c_url(['p' => 'player', 'id' => $player['id']])) ?>"><?= c_h($player['name']) ?></a></td>
          <td><?= $player['team_name'] ? c_h($player['team_name']) : '<span class="muted">未入名单</span>' ?></td>
          <td class="num"><?= $player['shirt_number'] !== null ? (int) $player['shirt_number'] : '—' ?></td>
          <td><span class="badge <?= c_position_class($player['position']) ?>"><?= c_position_label($player['position']) ?></span></td>
          <td><?= c_h($player['nationality']) ?></td>
          <td><?= c_date($player['birth_date']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="分页">
      <?php if ($current > 1): ?>
        <a href="<?= c_h(c_url($query + ['page' => $current - 1])) ?>">← 上一页</a>
      <?php endif; ?>
      <span>第 <?= (int) $current ?> / <?= (int) $pages ?> 页</span>
      <?php if ($current < $pages): ?>
        <a href="<?= c_h(c_url($query + ['page' => $current + 1])) ?>">下一页 →</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
