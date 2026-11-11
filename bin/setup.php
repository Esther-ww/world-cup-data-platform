<?php
/** 成员 A | CLI 安装演示数据库、创建管理员。 */
if(PHP_SAPI!=='cli')exit(1);
$r=dirname(__DIR__);require "$r/src/Model.php";
if(($argv[1]??'')==='demo'){
 if(getenv('A_DSN'))exit("请先取消 A_DSN。\n");
 if(!is_dir("$r/var"))mkdir("$r/var",0700,true);
 $m=new Model;if($m->row("SELECT name FROM sqlite_master WHERE type='table' LIMIT 1"))exit("已初始化，未覆盖。\n");
 $m->db->beginTransaction();foreach(['catalog','schema'] as $n)$m->db->exec(file_get_contents("$r/data/demo/$n.sql"));$m->db->commit();echo "OK\n";
}elseif(($argv[1]??'')==='admin'){
 $pw=getenv('A_ADMIN_PASSWORD');if(!$pw||strlen($pw)<10)exit("请设置至少10字节的 A_ADMIN_PASSWORD。\n");
 (new Model)->q("INSERT INTO users(username,password_hash,role) VALUES(?,?,'admin')",['admin',password_hash($pw,PASSWORD_DEFAULT)]);echo "OK\n";
}else exit("用法：setup.php demo 或 admin\n");
