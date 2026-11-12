<?php
/**
 * 成员 C | 视图：后台届次名单管理。
 *
 * 期望变量：$data['teams']、$data['team_id']、$data['team']、$data['squad']、
 *           $data['available']、$data['competition_id']
 */
?>
<h2>选择球队</h2>
<form method="get" class="filters compact-filters">
  <input type="hidden" name="p" value="admin">
  <input type="hidden" name="tab" value="squad">
  <label>球队
    <select name="team" onchange="this.form.submit()">
      <?php foreach ($data['teams'] as $team): ?>
        <option value="<?= (int) $team['id'] ?>"<?= $data['team_id'] === (int) $team['id'] ? ' selected' : '' ?>>
          <?= c_h($team['name']) ?>（<?= c_h($team['country_code']) ?>）
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <noscript><button type="submit">切换</button></noscript>
</form>

<?php if (!$data['team']): ?>
  <p class="empty">请先创建球队。</p>
<?php else: ?>
  <h2><?= c_h($data['team']['name']) ?> · 当前名单（<?= count($data['squad']) ?> 人）</h2>
  <?php if (!$data['squad']): ?>
    <p class="empty">名单为空。请从下方添加球员。</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>号码</th><th>球员</th><th>位置</th><th>国籍</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($data['squad'] as $player): ?>
          <tr>
            <td class="num"><?= (int) $player['shirt_number'] ?></td>
            <td><?= c_h($player['name']) ?></td>
            <td><span class="badge <?= c_position_class($player['position']) ?>"><?= c_position_label($player['position']) ?></span></td>
            <td><?= c_h($player['nationality']) ?></td>
            <td class="actions">
              <form method="post" class="inline" onsubmit="return confirm('确认将该球员移出名单？已有阵容记录时会被拒绝。')">
                <?= c_csrf_input() ?>
                <input type="hidden" name="action" value="squadDelete">
                <input type="hidden" name="competition_id" value="<?= (int) $data['competition_id'] ?>">
                <input type="hidden" name="team_id" value="<?= (int) $data['team_id'] ?>">
                <input type="hidden" name="player_id" value="<?= (int) $player['id'] ?>">
                <button type="submit" class="link-button danger">移出名单</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <h2>添加球员</h2>
  <?php if (!$data['available']): ?>
    <p class="empty">没有可添加的球员：所有球员都已在本届名单中，或尚未创建球员。</p>
  <?php else: ?>
    <form method="post" class="admin-form">
      <?= c_csrf_input() ?>
      <input type="hidden" name="action" value="squadAdd">
      <input type="hidden" name="competition_id" value="<?= (int) $data['competition_id'] ?>">
      <input type="hidden" name="team_id" value="<?= (int) $data['team_id'] ?>">
      <label>球员
        <select name="player_id" required>
          <?php foreach ($data['available'] as $player): ?>
            <option value="<?= (int) $player['id'] ?>">
              <?= c_h($player['name']) ?>（<?= c_position_label($player['position']) ?> · <?= c_h($player['nationality']) ?>）
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>球衣号码（1–99）<input type="number" name="shirt_number" min="1" max="99" required></label>
      <button type="submit">加入名单</button>
    </form>
  <?php endif; ?>
<?php endif; ?>
