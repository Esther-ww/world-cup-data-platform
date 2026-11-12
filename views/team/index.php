<?php
/**
 * 成员 C | 视图：球队列表与搜索。
 *
 * 期望变量：$teams、$q、$competition（由 PlayerController 提供）
 */
?>
<p class="eyebrow">TEAMS / <?= c_h($competition['name'] ?? '演示届次') ?></p>
<h1>球队</h1>
<p class="lead">按球队查看该届次名单；点击球队进入详情。当前演示覆盖 2014 年世界杯 A、B 两组共 8 支球队。</p>

<form method="get" class="filters" role="search">
  <input type="hidden" name="p" value="teams">
  <label>搜索球队
    <input name="q" value="<?= c_h($q) ?>" placeholder="队名、英文名或 3 位代码" maxlength="50">
  </label>
  <button type="submit">筛选</button>
  <?php if ($q !== ''): ?><a href="<?= c_h(c_url(['p' => 'teams'])) ?>">清除</a><?php endif; ?>
</form>

<?php if (!$teams): ?>
  <p class="empty">没有匹配的球队。</p>
<?php else: ?>
  <div class="grid team-grid">
    <?php foreach ($teams as $team): ?>
      <article class="team-card">
        <p class="code"><?= c_h($team['country_code']) ?></p>
        <h2><a href="<?= c_h(c_url(['p' => 'team', 'id' => $team['id']])) ?>"><?= c_h($team['name']) ?></a></h2>
        <p class="muted"><?= c_h($team['name_en']) ?></p>
        <p><?= (int) $team['squad_count'] ?> 名球员<?= (int) $team['squad_count'] === 0 ? ' · 名单未录入' : '' ?></p>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
