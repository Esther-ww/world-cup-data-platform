<?php
/**
 * 成员 C | 视图：后台标签导航。
 *
 * 期望变量：$tab、$data
 */
$tabs = [
    'team' => '球队管理',
    'player' => '球员管理',
    'squad' => '名单管理',
    'lineup' => '阵容管理',
    'import' => '数据导入',
];
?>
<p class="eyebrow">ADMIN / <?= c_h(strtoupper($tab)) ?></p>
<h1>管理后台</h1>
<nav class="tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a href="<?= c_h(c_url(['p' => 'admin', 'tab' => $key])) ?>"<?= $tab === $key ? ' class="active"' : '' ?>><?= c_h($label) ?></a>
  <?php endforeach; ?>
</nav>
<p class="muted">演示届次：<?= c_h($data['competition']['name'] ?? '未配置') ?>。所有写操作均校验管理员权限与 CSRF 令牌。</p>
