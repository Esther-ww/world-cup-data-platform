<?php
/**
 * 成员 C | CLI 安装脚本：初始化 SQLite 演示库、导入 CSV、创建管理员。
 *
 * 用法：
 *   php bin/setup.php demo     # 建表 + 占位比赛 + 导入 5 个 CSV（只在空库执行）
 *   php bin/setup.php admin    # 用 C_ADMIN_PASSWORD 创建管理员（至少 10 字节）
 *
 * 正式 MySQL 部署不执行 demo；由成员 B 汇编 data/install.sql 后统一导入。
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/src/PlayerModel.php';

$cmd = $argv[1] ?? '';

if ($cmd === 'demo') {
    if (getenv('C_DSN')) {
        exit("demo 只用于默认 SQLite 演示库，请先取消 C_DSN。\n");
    }
    if (!is_dir($root . '/var') && !mkdir($root . '/var', 0700, true) && !is_dir($root . '/var')) {
        exit("无法创建 var 目录。\n");
    }
    $m = new PlayerModel();
    if ($m->row("SELECT name FROM sqlite_master WHERE type = 'table' LIMIT 1")) {
        exit("演示库已初始化，未覆盖。需要重置请删除 var/demo.sqlite 后重试。\n");
    }
    try {
        $m->db->exec(file_get_contents($root . '/data/demo/schema.sql'));
    } catch (Throwable $e) {
        exit("建表失败：" . $e->getMessage() . "\n");
    }

    // 先导入球队，再写入占位比赛（matches 外键引用 teams），最后导入名单与阵容。
    $order = ['teams', 'players', 'squads', 'lineups', 'lineup_players'];
    foreach ($order as $kind) {
        $file = PlayerModel::IMPORT_FILES[$kind];
        try {
            $result = $m->importFile($kind, $root . '/data/import/' . $file);
            echo sprintf("%-16s %3d 行\n", $file, $result['inserted']);
        } catch (CImportException $e) {
            fwrite(STDERR, $file . " 导入失败：\n  " . implode("\n  ", $e->errors) . "\n");
            exit(1);
        } catch (Throwable $e) {
            fwrite(STDERR, $file . ' 导入失败：' . $e->getMessage() . "\n");
            exit(1);
        }
        if ($kind === 'players') {
            try {
                $m->db->exec(file_get_contents($root . '/data/demo/stub.sql'));
                echo "stub.sql            12 场\n";
            } catch (Throwable $e) {
                fwrite(STDERR, '占位比赛写入失败：' . $e->getMessage() . "\n");
                exit(1);
            }
        }
    }
    echo "OK：演示库已就绪。下一步 php -S 127.0.0.1:8771 -t public\n";
    exit(0);
}

if ($cmd === 'admin') {
    $password = getenv('C_ADMIN_PASSWORD');
    $username = getenv('C_ADMIN_USER') ?: 'admin';
    if (!$password || strlen($password) < 10) {
        exit("请设置至少 10 字节的 C_ADMIN_PASSWORD。\n");
    }
    $m = new PlayerModel();
    if ($m->row('SELECT id FROM users WHERE username = ?', [$username])) {
        exit("用户 {$username} 已存在，未覆盖。\n");
    }
    $m->q("INSERT INTO users(username, password_hash, role) VALUES(?,?,'admin')",
        [$username, password_hash($password, PASSWORD_DEFAULT)]);
    echo "OK：管理员 {$username} 已创建。\n";
    exit(0);
}

exit("用法：php bin/setup.php demo | admin\n");
