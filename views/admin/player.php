<?php
/**
 * 成员 C | 视图：后台球员管理。
 *
 * 期望变量：$data['players']、$data['edit']、$data['q']、$data['pages']、$data['current']、$data['total']
 */
$edit = $data['edit'] ?? null;
$query = ['p' => 'admin', 'tab' => 'player'];
if ($data['q'] !== '') $query['q'] = $data['q'];
?>
<h2><?= $edit ? '编辑球员' : '新增球员' ?></h2>
<form method="post" class="admin-form">
  <?= c_csrf_input() ?>
  <input type="hidden" name="action" value="playerSave">
  <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
  <label>姓名<input name="name" required maxlength="100" value="<?= c_h($edit['name'] ?? '') ?>"></label>
  <label>英文名<input name="name_en" maxlength="100" value="<?= c_h($edit['name_en'] ?? '') ?>"></label>
  <label>出生日期（可留空）<input type="date" name="birth_date" value="<?= c_h($edit['birth_date'] ?? '') ?>"></label>
  <label>国籍<input name="nationality" required maxlength="60" value="<?= c_h($edit['nationality'] ?? '') ?>"></label>
  <label>位置
    <select name="position" required>
      <?php foreach (['GK', 'DF', 'MF', 'FW'] as $code): ?>
        <option value="<?= $code ?>"<?= ($edit['position'] ?? '') === $code ? ' selected' : '' ?>><?= c_position_label($code) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button type="submit"><?= $edit ? '保存修改' : '创建球员' ?></button>
  <?php if ($edit): ?><a href="<?= c_h(c_url($query)) ?>">取消编辑</a><?php endif; ?>
</form>

<h2>球员列表（<?= (int) $data['total'] ?> 人）</h2>
<form method="get" class="filters compact-filters">
  <input type="hidden" name="p" value="admin">
  <input type="hidden" name="tab" value="player">
  <label>姓名搜索<input name="q" value="<?= c_h($data['q']) ?>" maxlength="50"></label>
  <button type="submit">搜索</button>
  <?php if ($data['q'] !== ''): ?><a href="<?= c_h(c_url(['p' => 'admin', 'tab' => 'player'])) ?>">清除</a><?php endif; ?>
</form>

<?php if (!$data['players']): ?>
  <p class="empty">没有匹配的球员。</p>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>姓名</th><th>球队</th><th>号码</th><th>位置</th><th>出生日期</th><th>操作</th></tr></thead>
      <tbody>
      <?php foreach ($data['players'] as $player): ?>
        <tr>
          <td><?= (int) $player['id'] ?></td>
          <td><?= c_h($player['name']) ?></td>
          <td><?= $player['team_name'] ? c_h($player['team_name']) : '<span class="muted">未入名单</span>' ?></td>
          <td class="num"><?= $player['shirt_number'] !== null ? (int) $player['shirt_number'] : '—' ?></td>
          <td><span class="badge <?= c_position_class($player['position']) ?>"><?= c_position_label($player['position']) ?></span></td>
          <td><?= c_date($player['birth_date']) ?></td>
          <td class="actions">
            <a href="<?= c_h(c_url(['p' => 'admin', 'tab' => 'player', 'edit' => $player['id']])) ?>">编辑</a>
            <form method="post" class="inline" onsubmit="return confirm('确认删除该球员？有关联数据时会被拒绝。')">
              <?= c_csrf_input() ?>
              <input type="hidden" name="action" value="playerDelete">
              <input type="hidden" name="id" value="<?= (int) $player['id'] ?>">
              <button type="submit" class="link-button danger">删除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($data['pages'] > 1): ?>
    <nav class="pagination" aria-label="分页">
      <?php if ($data['current'] > 1): ?>
        <a href="<?= c_h(c_url($query + ['page' => $data['current'] - 1])) ?>">← 上一页</a>
      <?php endif; ?>
      <span>第 <?= (int) $data['current'] ?> / <?= (int) $data['pages'] ?> 页</span>
      <?php if ($data['current'] < $data['pages']): ?>
        <a href="<?= c_h(c_url($query + ['page' => $data['current'] + 1])) ?>">下一页 →</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
