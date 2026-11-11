<?php
/** 成员 A | 模型：参数查询、收藏读取与持久化。 */
class Model {
 public PDO $db;
 function __construct(){
 $this->db=new PDO(getenv('A_DSN')?:'sqlite:'.dirname(__DIR__).'/var/demo.sqlite',getenv('A_DB_USER')?:null,getenv('A_DB_PASS')?:null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 if($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')$this->db->exec('PRAGMA foreign_keys=ON');}
 function q($sql,$args=[]){$s=$this->db->prepare($sql);$s->execute($args);return $s;}
 function rows($sql,$args=[]){return $this->q($sql,$args)->fetchAll();}
 function row($sql,$args=[]){return $this->q($sql,$args)->fetch();}
 function map($t){return match($t){'team'=>['a_team_catalog','favorite_teams','team_id'],'match'=>['a_match_catalog','favorite_matches','match_id'],default=>throw new DomainException('收藏类型无效')};}
 function catalog($t,$uid,$only=false){[$v,$f,$k]=$this->map($t);return $this->rows("SELECT c.id,c.title,f.user_id AS saved FROM $v c LEFT JOIN $f f ON f.$k=c.id AND f.user_id=? ".($only?'WHERE f.user_id IS NOT NULL ':'').'ORDER BY c.id',[$uid]);}
 function favorite($uid,$t,$id,$save){[$v,$f,$k]=$this->map($t);
 if(!$save){$this->q("DELETE FROM $f WHERE user_id=? AND $k=?",[$uid,$id]);return;}
 if(!$this->row("SELECT id FROM $v WHERE id=?",[$id]))throw new DomainException('条目不存在');
 try{$this->q("INSERT INTO $f(user_id,$k) VALUES(?,?)",[$uid,$id]);}catch(PDOException $e){if(!$this->row("SELECT user_id FROM $f WHERE user_id=? AND $k=?",[$uid,$id]))throw $e;}}
}
