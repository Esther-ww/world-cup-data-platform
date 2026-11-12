<?php
/**
 * 成员 C | 视图：后台 CSV 导入校验。
 *
 * 期望变量：$data['files']、$data['errors']
 * 安全：只允许导入 data/import 下白名单中的 5 个文件，路径不接受用户输入。
 */
?>
<h2>CSV 导入</h2>
<p class="lead">导入前逐行校验主键、外键、号码范围、日期格式和阵容人数。任一行失败则整批回滚，不写入半成品数据。</p>
<p class="muted">演示库已由 <code>php bin/setup.php demo</code> 导入一次；重复导入会因主键/唯一键重复而被拒绝。
   需要重置时删除 <code>var/demo.sqlite</code> 后重新执行安装命令。</p>

<?php if (!empty($data['errors'])): ?>
  <div class="alert" role="alert">
    <strong>导入未写入，发现以下问题：</strong>
    <ul>
      <?php foreach ($data['errors'] as $error): ?><li><?= c_h($error) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="table-wrap">
  <table>
    <thead><tr><th>顺序</th><th>文件</th><th>大小</th><th>操作</th></tr></thead>
    <tbody>
    <?php $order = 0; foreach ($data['files'] as $kind => $file): $order++; ?>
      <tr>
        <td><?= $order ?></td>
        <td><code>data/import/<?= c_h($file['name']) ?></code></td>
        <td><?= $file['exists'] ? number_format($file['size']) . ' 字节' : '<span class="muted">文件缺失</span>' ?></td>
        <td class="actions">
          <?php if ($file['exists']): ?>
            <form method="post" class="inline">
              <?= c_csrf_input() ?>
              <input type="hidden" name="action" value="import">
              <input type="hidden" name="kind" value="<?= c_h($kind) ?>">
              <input type="hidden" name="dry" value="1">
              <button type="submit" class="link-button">仅校验</button>
            </form>
            <form method="post" class="inline" onsubmit="return confirm('确认导入该文件？')">
              <?= c_csrf_input() ?>
              <input type="hidden" name="action" value="import">
              <input type="hidden" name="kind" value="<?= c_h($kind) ?>">
              <button type="submit" class="link-button">导入</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<h2>导入顺序与来源</h2>
<ol>
  <li><code>teams.csv</code>：8 支球队，含国家代码。</li>
  <li><code>players.csv</code>：184 名球员，出生日期缺失时留空并明确显示“未收录”。</li>
  <li><code>squads.csv</code>：每队 23 人的届次名单与号码。</li>
  <li><code>lineups.csv</code>：10 场比赛的 20 份双方阵容；最后两场刻意不导入，用于演示空阵容。</li>
  <li><code>lineup_players.csv</code>：首发 11 人与数据源记录的登场替补。</li>
</ol>
<p>完整来源、采集日期、字段说明与已知缺口见 <code>data/import/README.md</code>。</p>
