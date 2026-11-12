<?php
/**
 * 成员 C | CLI 数据导入：按白名单导入 data/import 下的 CSV。
 *
 * 用法：
 *   php bin/import.php teams
 *   php bin/import.php players --dry-run
 *
 * 可用类型：teams / players / squads / lineups / lineup_players
 * 写入前逐行校验；任一行失败则整批回滚。后台“数据导入”页复用同一模型方法。
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/src/PlayerModel.php';

$kind = $argv[1] ?? '';
$dry = in_array('--dry-run', array_slice($argv, 2), true);
if (!isset(PlayerModel::IMPORT_FILES[$kind])) {
    fwrite(STDERR, "用法：php bin/import.php <" . implode('|', array_keys(PlayerModel::IMPORT_FILES))
        . "> [--dry-run]\n");
    exit(1);
}

$file = $root . '/data/import/' . PlayerModel::IMPORT_FILES[$kind];
$m = new PlayerModel();
try {
    $result = $m->importFile($kind, $file, $dry);
    echo sprintf("%s：%d 行%s。\n", PlayerModel::IMPORT_FILES[$kind], $result['rows'],
        $dry ? '校验通过（未写入）' : '导入成功，写入 ' . $result['inserted'] . ' 行');
    exit(0);
} catch (CImportException $e) {
    fwrite(STDERR, "导入未写入，发现以下问题：\n  " . implode("\n  ", $e->errors) . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, '导入失败：' . $e->getMessage() . "\n");
    exit(1);
}
