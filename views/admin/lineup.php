<?php
/**
 * 成员 C | 视图：后台阵容录入与撤下。
 *
 * 期望变量：$data['matches']、$data['match_id']、$data['match']、$data['team_id']、
 *           $data['squad']、$data['lineup']、$data['selected']
 */
?>
<h2>选择比赛与球队</h2>
<form method="get" class="filters compact-filters">
  <input type="hidden" name="p" value="admin">
  <input type="hidden" name="tab" value="lineup">
  <label>比赛
    <select name="match" onchange="this.form.submit()">
      <?php foreach ($data['matches'] as $match): ?>
        <option value="<?= (int) $match['id'] ?>"<?= $data['match_id'] === (int) $match['id'] ? ' selected' : '' ?>>
          <?= c_h(substr($match['kickoff_utc'], 0, 10)) ?> · <?= c_h($match['home_name']) ?> vs <?= c_h($match['away_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php if ($data['match']): ?>
    <label>球队
      <select name="team" onchange="this.form.submit()">
        <?php foreach ([[$data['match']['home_team_id'], $data['match']['home_name']],
                       [$data['match']['away_team_id'], $data['match']['away_name']]] as [$tid, $tname]): ?>
          <option value="<?= (int) $tid ?>"<?= $data['team_id'] === (int) $tid ? ' selected' : '' ?>><?= c_h($tname) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
  <noscript><button type="submit">切换</button></noscript>
</form>

<?php if (!$data['match']): ?>
  <p class="empty">暂无比赛数据。</p>
<?php elseif (!$data['squad']): ?>
  <p class="empty">该队尚未建立届次名单，请先在“名单管理”中添加球员。</p>
<?php else: ?>
  <p class="lead">
    当前状态：<?= $data['lineup'] ? '已公布（' . c_h($data['lineup']['formation']) . '）' : '<span class="muted">未公布</span>' ?>
    <?php if ($data['lineup']): ?>
      · <a href="<?= c_h(c_url(['p' => 'lineup', 'match' => $data['match_id']])) ?>">查看前台页面</a>
    <?php endif; ?>
  </p>

  <form method="post" class="lineup-form">
    <?= c_csrf_input() ?>
    <input type="hidden" name="action" value="lineupSave">
    <input type="hidden" name="match_id" value="<?= (int) $data['match_id'] ?>">
    <input type="hidden" name="team_id" value="<?= (int) $data['team_id'] ?>">
    <label>阵型（留空则按首发位置自动推算）
      <input name="formation" maxlength="16" placeholder="例如 4-3-3"
             value="<?= c_h($data['lineup']['formation'] ?? '') ?>">
    </label>
    <p class="muted">规则：首发正好 11 人、其中 1 名门将、替补最多 12 人；球员必须属于本队本届名单。
       保存后前台立即生效；撤下后前台显示“阵容未公布”。</p>
    <div class="table-wrap">
      <table>
        <thead><tr><th>号码</th><th>球员</th><th>名单位置</th><th>本场角色</th><th>本场位置</th></tr></thead>
        <tbody>
        <?php foreach ($data['squad'] as $player): ?>
          <?php
            $pid = (int) $player['id'];
            $selected = $data['selected'][$pid] ?? null;
            $role = $selected ? ((int) $selected['is_starter'] === 1 ? 'starter' : 'bench') : '';
            $position = $selected['position'] ?? $player['position'];
          ?>
          <tr>
            <td class="num"><?= (int) $player['shirt_number'] ?></td>
            <td><?= c_h($player['name']) ?></td>
            <td><span class="badge <?= c_position_class($player['position']) ?>"><?= c_position_label($player['position']) ?></span></td>
            <td>
              <select name="role[<?= $pid ?>]">
                <option value=""<?= $role === '' ? ' selected' : '' ?>>未入选</option>
                <option value="starter"<?= $role === 'starter' ? ' selected' : '' ?>>首发</option>
                <option value="bench"<?= $role === 'bench' ? ' selected' : '' ?>>替补</option>
              </select>
            </td>
            <td>
              <select name="pos[<?= $pid ?>]">
                <?php foreach (['GK', 'DF', 'MF', 'FW'] as $code): ?>
                  <option value="<?= $code ?>"<?= $position === $code ? ' selected' : '' ?>><?= c_position_label($code) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <button type="submit">保存阵容</button>
  </form>

  <?php if ($data['lineup']): ?>
    <form method="post" onsubmit="return confirm('撤下后前台将显示阵容未公布，确认继续？')">
      <?= c_csrf_input() ?>
      <input type="hidden" name="action" value="lineupDelete">
      <input type="hidden" name="match_id" value="<?= (int) $data['match_id'] ?>">
      <input type="hidden" name="team_id" value="<?= (int) $data['team_id'] ?>">
      <button type="submit" class="danger-button">撤下该队阵容</button>
    </form>
  <?php endif; ?>
<?php endif; ?>
