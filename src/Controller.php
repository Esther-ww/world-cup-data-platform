<?php
/** 成员 A | 控制器：会话、权限、请求校验、审核及文件交付。 */
require __DIR__.'/Model.php';
session_start(['use_strict_mode'=>1,'cookie_httponly'=>true,'cookie_samesite'=>'Lax','cookie_secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);
$_SESSION['csrf']??=bin2hex(random_bytes(32));
function val($k,$max,$empty=false){$v=$_POST[$k]??'';if(!is_string($v)||strlen($v)>$max||(!$empty&&trim($v)===''))throw new DomainException('输入为空或过长');return trim($v);}
function fail($n,$msg){http_response_code($n);exit($msg);}
function need($ok){if(!$ok)fail(403,'无权执行此操作');}
$m=new Model;$me=$m->row('SELECT id,username,role FROM users WHERE id=?',[$_SESSION['uid']??0]);$uid=$me['id']??0;$admin=($me['role']??'')==='admin';
$files=require __DIR__.'/files.php';$page=$_GET['p']??'team';$notice=$_SESSION['notice']??'';unset($_SESSION['notice']);
try{
if($_SERVER['REQUEST_METHOD']==='POST'){
 need(is_string($_POST['csrf']??null)&&hash_equals($_SESSION['csrf'],$_POST['csrf']));
 $action=val('action',32);$dest='team';$id=(int)($_POST['id']??0);
 switch($action){
 case 'register':case 'login':
 $name=val('username',48);$pw=$_POST['password']??'';if(!is_string($pw)||strlen($pw)>72)throw new DomainException('密码长度不符合要求');
 if($action==='register'){if(!preg_match('/^[a-zA-Z0-9_]{3,32}$/D',$name)||strlen($pw)<10||strtolower($name)==='admin')throw new DomainException('用户名需为3–32位字母、数字或下划线，不能使用 admin；密码至少10字节');
 try{$m->q('INSERT INTO users(username,password_hash) VALUES(?,?)',[$name,password_hash($pw,PASSWORD_DEFAULT)]);}catch(PDOException $e){if($m->row('SELECT id FROM users WHERE username=?',[$name]))throw new DomainException('用户名已存在');throw $e;}}
 $u=$m->row('SELECT * FROM users WHERE username=?',[$name]);if(!$u||!password_verify($pw,$u['password_hash']))throw new DomainException('账号或密码错误');
 session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(32));$dest='profile';break;
 case 'logout':$_SESSION=[];session_regenerate_id(true);break;
 case 'favorite':need($uid);$m->favorite($uid,val('type',5),$id,val('save',1)==='1');$dest=($_POST['from']??'')==='catalog'?'catalog':'profile';break;
 case 'message':need($uid);$m->q('INSERT INTO messages(user_id,body) VALUES(?,?)',[$uid,val('body',1800)]);break;
 case 'approve':case 'delete':need($admin);$m->q($action==='approve'?"UPDATE messages SET status='approved' WHERE id=?":"DELETE FROM messages WHERE id=?",[$id]);$dest='admin';break;
 case 'updateTeam':need($admin);$m->q('UPDATE team_profiles SET name=?,intro=?,contact=? WHERE id=1',[val('name',80),val('intro',2400),val('contact',120,true)]);$dest='admin';break;
 case 'updateMember':need($admin);$m->q('UPDATE team_members SET intro=? WHERE id=?',[val('intro',1200),$id]);$dest='admin';break;
 case 'publish':need($admin);$key=val('file_key',64);if(!isset($files[$key])||!is_file(dirname(__DIR__).'/data/'.$files[$key]))throw new DomainException('文件未就绪');
 $member=($_POST['member_id']??'')===''?null:(int)$_POST['member_id'];if($member!==null&&!$m->row('SELECT id FROM team_members WHERE id=?',[$member]))throw new DomainException('成员不存在');
 $title=val('title',100);if($m->row('SELECT id FROM assignments WHERE file_key=?',[$key]))$m->q('UPDATE assignments SET member_id=?,title=? WHERE file_key=?',[$member,$title,$key]);else $m->q('INSERT INTO assignments(member_id,title,file_key) VALUES(?,?,?)',[$member,$title,$key]);$dest='admin';break;
 case 'unpublish':need($admin);$m->q('DELETE FROM assignments WHERE id=?',[$id]);$dest='admin';break;
 default:fail(400,'未知操作');}
 $_SESSION['notice']=$action==='message'?'留言已提交，审核后公开。':'操作已完成。';header('Location: ?p='.$dest,true,303);exit;
}
if($page==='file'){
 $a=$m->row('SELECT file_key FROM assignments WHERE id=?',[(int)($_GET['id']??0)]);$rel=$files[$a['file_key']??'']??null;
 if(!$rel||!is_file($path=dirname(__DIR__).'/data/'.$rel))fail(404,'资料不存在或已撤下');
 header('Content-Type: application/octet-stream');header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode(basename($rel)));readfile($path);exit;
}
if(!in_array($page,['team','auth','profile','admin','catalog','downloads'],true))fail(404,'页面不存在');
if($page==='admin')need($admin);if($page==='profile')need($uid);
require dirname(__DIR__).'/views/page.php';
}catch(DomainException $e){http_response_code(422);echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').' · <a href="?">返回首页</a>';}
catch(PDOException $e){error_log($e->getMessage());fail(500,'数据库操作失败，请检查安装配置。');}
