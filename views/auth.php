<?php
/**
 * 成员 C | 视图：独立演示用的管理员登录页。
 *
 * 说明：账号与会话最终由成员 A 的账号模块提供；此页仅用于 C 模块的本地演示与测试。
 */
?>
<p class="eyebrow">ADMIN / LOGIN</p>
<h1>管理员登录</h1>
<p class="lead">球队、球员、名单与阵容的写操作仅对管理员开放。普通访客可以浏览全部公共页面。</p>

<?php if ($me): ?>
  <p role="status">当前已登录：<?= c_h($me['username']) ?>（<?= $me['role'] === 'admin' ? '管理员' : '普通用户' ?>）。</p>
  <p><a href="<?= c_h(c_url(['p' => 'teams'])) ?>">返回球队列表</a></p>
<?php else: ?>
  <form method="post" class="auth-form">
    <?= c_csrf_input() ?>
    <input type="hidden" name="action" value="login">
    <label>用户名<input name="username" required autocomplete="username" maxlength="48"></label>
    <label>密码<input type="password" name="password" required autocomplete="current-password" maxlength="72"></label>
    <button type="submit">登录</button>
  </form>
  <p class="muted">独立演示不开放网页注册；管理员账号由 <code>php bin/setup.php admin</code> 创建。
     联调时请改用成员 A 的注册/登录页面。</p>
<?php endif; ?>
