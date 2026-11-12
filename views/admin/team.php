<?php
/**
 * 成员 C | 视图：后台球队管理。
 *
 * 期望变量：$data['teams']、$data['edit']
 */
$edit = $data['edit'] ?? null;
?>
<h2><?= $edit ? '编辑球队' : '新增球队' ?></h2>
<form method="post" class="admin-form">
  <?= c_csrf_input() ?>
  <input type="hidden" name="action" value="teamSave">
  <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
  <label>名称（中文）<input name="name" required maxlength="80" value="<?= c_h($edit['name'] ?? '') ?>"></label>
  <label>英文名<input name="name_en" maxlength="80" value="<?= c_h($edit['name_en'] ?? '') ?>"></label>
  <label>国家代码（3 位大写字母）<input name="country_code" required maxlength="3" pattern="[A-Za-z]{3}" value="<?= c_h($edit['country_code'] ?? '') ?>"></label>
  <button type="submit"><?= $edit ? '保存修改' : '创建球队' ?></button>
  <?php if ($edit): ?><a href="<?= c_h(c_url(['p' => 'admin', 'tab' => 'team'])) ?>">取消编辑</a><?php endif; ?>
</form>

<h2>球队列表</h2>
<?php if (!$data['teams']): ?>
  <p class="empty">尚无球队。</p>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>名称</th><th>英文名</th><th>代码</th><th>名单人数</th><th>操作</th></tr></thead>
      <tbody>
      <?php foreach ($data['teams'] as $team): ?>
        <tr>
          <td><?= (int) $team['id'] ?></td>
          <td><?= c_h($team['name']) ?></td>
          <td><?= c_h($team['name_en']) ?></td>
          <td><?= c_h($team['country_code']) ?></td>
          <td><?= (int) $team['squad_count'] ?></td>
          <td class="actions">
            <a href="<?= c_h(c_url(['p' => 'admin', 'tab' => 'team', 'edit' => $team['id']])) ?>">编辑</a>
            <form method="post" class="inline" onsubmit="return confirm('确认删除该球队？有关联数据时会被拒绝。')">
              <?= c_csrf_input() ?>
              <input type="hidden" name="action" value="teamDelete">
              <input type="hidden" name="id" value="<?= (int) $team['id'] ?>">
              <button type="submit" class="link-button danger">删除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
