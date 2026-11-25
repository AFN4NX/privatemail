<?php
declare(strict_types=1);
final class SetupService {
    public static function configured():bool {return (string)Database::getSetting('access_password_hash','')!==''||APP_PASSWORD_HASH!==''||APP_ACCESS_PASSWORD!=='';}
    public static function needed():bool {return !self::configured()&&Database::getSetting('setup_complete','0')!=='1';}
    public static function tokenFile():string{return __DIR__.'/../storage/setup-token.php';}
    public static function ensureToken():void {
        $file=self::tokenFile();if(is_file($file))return;$lock=@fopen(__DIR__.'/../storage/setup.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Unable to lock setup.');
        try{if(!is_file($file)){$temp=tempnam(dirname($file),'.setup-');try{chmod($temp,0600);$contents="<?php\nhttp_response_code(404);\nreturn '".bin2hex(random_bytes(24))."';\n";if(file_put_contents($temp,$contents,LOCK_EX)!==strlen($contents)||!rename($temp,$file))throw new RuntimeException('Unable to create setup token.');}finally{if(is_file($temp))unlink($temp);}}}finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function claim(string $token,string $password):void {
        $lock=@fopen(__DIR__.'/../storage/setup.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Unable to lock setup.');
        try{if(!self::needed())throw new RuntimeException('Setup is already locked. Sign in instead.');
            $raw=(string)@file_get_contents(self::tokenFile());preg_match("/return '([a-f0-9]{48})';/",$raw,$match);$stored=$match[1]??'';if($stored===''||!hash_equals($stored,trim($token)))throw new InvalidArgumentException('Incorrect setup token. Read storage/setup-token.php using your hosting File Manager.');
            Auth::changePassword($password);Database::setSetting('setup_complete','1');if(!Auth::attempt($password))throw new RuntimeException('Password saved. Sign in with your new password.');
            $_SESSION['setup_wizard_step']=2;@unlink(self::tokenFile());
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
