<?php
declare(strict_types=1);
require_once __DIR__.'/SessionRegistry.php';
final class Auth {
    public static function check(): bool {
        if (empty($_SESSION['authenticated'])) return false;
        $revision=(string)Database::getSetting('auth_revision','');
        if($revision!==''&&!hash_equals($revision,(string)($_SESSION['auth_revision']??''))){self::logout();session_id('');session_start();return false;}
        if (time()-(int)($_SESSION['last_activity']??0)>AUTH_SESSION_SECONDS) { self::logout();session_id('');session_start();return false; }
        if(!SessionRegistry::validate()){self::logout();session_id('');session_start();return false;}
        $_SESSION['last_activity']=time();
        if(!headers_sent()){ $params=session_get_cookie_params();unset($params['lifetime']);setcookie(session_name(),session_id(),array_merge($params,['expires'=>time()+AUTH_SESSION_SECONDS])); }
        return true;
    }
    public static function require(): void {
        if (!self::check()) {
            if (str_starts_with($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) Security::json(['ok'=>false,'error'=>'Session expired. Please sign in again.'],401);
            header('Location: /login.php'); exit;
        }
    }
    public static function attempt(string $password): bool {
        $db=Database::connection();
        $key=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));
        $db->exec('CREATE TABLE IF NOT EXISTS login_attempts (client TEXT PRIMARY KEY, failures INTEGER NOT NULL, started INTEGER NOT NULL)');
        $now=time();
        $db->prepare('DELETE FROM login_attempts WHERE started < ?')->execute([$now-900]);
        $s=$db->prepare('SELECT failures FROM login_attempts WHERE client=?');$s->execute([$key]);
        if((int)$s->fetchColumn()>=10) return false;
        $saved=(string)Database::getSetting('access_password_hash','');
        $hash=$saved!==''?$saved:APP_PASSWORD_HASH;
        $valid=$hash!==''?password_verify($password,$hash):(APP_ACCESS_PASSWORD!==''&&hash_equals(APP_ACCESS_PASSWORD,$password));
        if(!$valid){$db->prepare('INSERT INTO login_attempts(client,failures,started) VALUES(?,1,?) ON CONFLICT(client) DO UPDATE SET failures=failures+1')->execute([$key,$now]);return false;}
        $db->prepare('DELETE FROM login_attempts WHERE client=?')->execute([$key]);
        session_regenerate_id(true); $_SESSION['authenticated']=true; $_SESSION['auth_revision']=(string)Database::getSetting('auth_revision',''); $_SESSION['last_activity']=time();
        SessionRegistry::register(); Security::csrfToken(); return true;
    }
    public static function changePassword(string $password): void {
        if(strlen($password)<12||strlen($password)>72)throw new InvalidArgumentException('Use a password between 12 and 72 bytes.');
        $db=Database::connection();$db->beginTransaction();
        try{$revision=bin2hex(random_bytes(24));Database::setSetting('access_password_hash',password_hash($password,PASSWORD_DEFAULT));Database::setSetting('auth_revision',$revision);$db->commit();$_SESSION['auth_revision']=$revision;session_regenerate_id(true);SessionRegistry::passwordChanged();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    public static function logout(): void { SessionRegistry::logout(); $_SESSION=[]; if(!headers_sent()){ $params=session_get_cookie_params();unset($params['lifetime']);setcookie(session_name(),'',array_merge($params,['expires'=>time()-3600])); }session_destroy(); }
}
