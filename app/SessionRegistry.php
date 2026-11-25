<?php
declare(strict_types=1);
final class SessionRegistry {
    private static bool $ready=false;
    private static function db():PDO {
        $db=Database::connection();
        if(!self::$ready){
            $db->exec('CREATE TABLE IF NOT EXISTS active_sessions (id TEXT PRIMARY KEY, token_hash TEXT NOT NULL UNIQUE, created INTEGER NOT NULL, last_seen INTEGER NOT NULL, ip TEXT NOT NULL, agent TEXT NOT NULL, revision TEXT NOT NULL, revoked INTEGER NOT NULL DEFAULT 0)');
            self::$ready=true;
        }
        return $db;
    }
    private static function token():string{return (string)($_SESSION['device_session_token']??'');}
    public static function validate():bool {
        $db=self::db();$token=self::token();
        if($token===''){self::register();return true;}
        $q=$db->prepare('SELECT id,last_seen,revoked FROM active_sessions WHERE token_hash=?');$q->execute([hash('sha256',$token)]);$row=$q->fetch();
        if(!$row||(int)$row['revoked']===1||time()-(int)$row['last_seen']>AUTH_SESSION_SECONDS)return false;
        if(time()-(int)$row['last_seen']>=60){$q=$db->prepare('UPDATE active_sessions SET last_seen=?,ip=?,agent=? WHERE id=? AND revoked=0');$q->execute([time(),self::ip(),self::agent(),$row['id']]);}
        return true;
    }
    public static function register():void {
        $db=self::db();
        if(self::token()!=='')return;
        $token=bin2hex(random_bytes(32));$id=bin2hex(random_bytes(16));$now=time();
        $db->prepare('INSERT INTO active_sessions(id,token_hash,created,last_seen,ip,agent,revision) VALUES(?,?,?,?,?,?,?)')->execute([$id,hash('sha256',$token),$now,$now,self::ip(),self::agent(),(string)Database::getSetting('auth_revision','')]);
        $_SESSION['device_session_token']=$token;
        $db->prepare('DELETE FROM active_sessions WHERE last_seen<?')->execute([$now-AUTH_SESSION_SECONDS]);
    }
    private static function ip():string{$ip=(string)($_SERVER['REMOTE_ADDR']??'');return filter_var($ip,FILTER_VALIDATE_IP)?$ip:'Unknown';}
    private static function agent():string{return substr((string)($_SERVER['HTTP_USER_AGENT']??'Unknown browser'),0,512);}
    public static function currentId():string {
        $q=self::db()->prepare('SELECT id FROM active_sessions WHERE token_hash=?');$q->execute([hash('sha256',self::token())]);return (string)$q->fetchColumn();
    }
    public static function sessions():array {
        $q=self::db()->prepare('SELECT id,created,last_seen,ip,agent FROM active_sessions WHERE revoked=0 AND last_seen>=? AND revision=? ORDER BY last_seen DESC');$q->execute([time()-AUTH_SESSION_SECONDS,(string)Database::getSetting('auth_revision','')]);return $q->fetchAll();
    }
    public static function revoke(string $id):void {
        if(!preg_match('/^[a-f0-9]{32}$/D',$id)||hash_equals(self::currentId(),$id))throw new InvalidArgumentException('Choose another session to sign out.');
        self::db()->prepare('UPDATE active_sessions SET revoked=1 WHERE id=?')->execute([$id]);
    }
    public static function revokeOthers():void {self::db()->prepare('UPDATE active_sessions SET revoked=1 WHERE id<>?')->execute([self::currentId()]);}
    public static function passwordChanged():void {
        self::revokeOthers();self::db()->prepare('UPDATE active_sessions SET revision=? WHERE id=?')->execute([(string)Database::getSetting('auth_revision',''),self::currentId()]);
    }
    public static function logout():void {if(self::token()!=='')self::db()->prepare('UPDATE active_sessions SET revoked=1 WHERE token_hash=?')->execute([hash('sha256',self::token())]);}
    public static function device(string $ua):string {
        $os=preg_match('/Android/i',$ua)?'Android':(preg_match('/iPhone|iPad/i',$ua)?'iOS':(preg_match('/Windows/i',$ua)?'Windows':(preg_match('/Macintosh|Mac OS X/i',$ua)?'macOS':(preg_match('/Linux/i',$ua)?'Linux':'Unknown device'))));
        $browser=preg_match('/Edg\//i',$ua)?'Edge':(preg_match('/OPR\//i',$ua)?'Opera':(preg_match('/Firefox|FxiOS/i',$ua)?'Firefox':(preg_match('/Chrome|CriOS/i',$ua)?'Chrome':(preg_match('/Safari/i',$ua)?'Safari':'Browser'))));
        return $os.' · '.$browser;
    }
}
