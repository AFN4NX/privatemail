<?php
declare(strict_types=1);
final class MailboxRegistry {
    public static function init(): void {
        $db=Database::connection();
        $db->exec('CREATE TABLE IF NOT EXISTS mail_accounts(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,config_enc TEXT,legacy INTEGER NOT NULL DEFAULT 0,selected INTEGER NOT NULL DEFAULT 1)');
        $db->exec('CREATE TABLE IF NOT EXISTS managed_domains(id INTEGER PRIMARY KEY AUTOINCREMENT,domain TEXT NOT NULL UNIQUE,account_id INTEGER,active INTEGER NOT NULL DEFAULT 1)');
        if(Database::getSetting('multi_imported','')==='1')return;
        $db->beginTransaction();try{
            if(Database::getSetting('multi_imported','')==='1'){$db->commit();return;}
            if(is_file(__DIR__.'/../config/imap.php'))$db->exec("INSERT OR IGNORE INTO mail_accounts(id,name,legacy) VALUES(1,'Primary mailbox',1)");
            $file=__DIR__.'/../config/domains.php';$domains=is_file($file)?require $file:[];
            foreach($domains as $domain){$domain=strtolower(trim((string)$domain));if(filter_var('x@'.$domain,FILTER_VALIDATE_EMAIL))$db->prepare('INSERT OR IGNORE INTO managed_domains(domain,account_id) VALUES(?,?)')->execute([$domain,self::find(1)?1:null]);}
            foreach(['mailbox_identity','last_sync','poll_uidnext','history_cursor','history_complete','otp_parser_version'] as $key){$value=Database::getSetting($key,null);if($value!==null)Database::setSetting('account_1_'.$key,(string)$value);}
            Database::setSetting('multi_imported','1');$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    public static function all(bool $selected=false):array {return Database::connection()->query('SELECT * FROM mail_accounts'.($selected?' WHERE selected=1':'').' ORDER BY id')->fetchAll();}
    public static function find(int $id):?array {$q=Database::connection()->prepare('SELECT * FROM mail_accounts WHERE id=?');$q->execute([$id]);return $q->fetch()?:null;}
    public static function selectedIds():array {return array_map(fn($a)=>(int)$a['id'],self::all(true));}
    public static function scope():string {$ids=self::selectedIds();return 'account_id IN ('.($ids?implode(',',$ids):'0').')';}
    public static function acquire(int $id,bool $shared=false) {
        $lock=@fopen(__DIR__.'/../storage/sync-'.$id.'.lock','c');
        if(!$lock)throw new RuntimeException('Unable to lock mailbox.');if(!flock($lock,($shared?LOCK_SH:LOCK_EX)|LOCK_NB)){fclose($lock);throw new RuntimeException('Mailbox is busy. Try again shortly.');}return $lock;
    }
    public static function release($lock):void {if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}
    public static function viewToken():string {return substr(hash('sha256',implode(',',self::selectedIds()).'|'.(string)Database::getSetting('mailbox_view_revision','0')),0,24);}
    private static function changed():void {Database::setSetting('mailbox_view_revision',bin2hex(random_bytes(12)));}
    private static function key(bool $create=false):string {
        $file=__DIR__.'/../storage/accounts.key';
        if($create&&!is_file($file)){
            $lock=@fopen(__DIR__.'/../storage/accounts-key.lock','c');if(!$lock||!flock($lock,LOCK_EX)){if(is_resource($lock))fclose($lock);throw new RuntimeException('Unable to lock mailbox key.');}
            try{if(!is_file($file)){
                if((int)Database::connection()->query("SELECT COUNT(*) FROM mail_accounts WHERE legacy=0 AND config_enc IS NOT NULL")->fetchColumn()>0)throw new RuntimeException('The original accounts key is missing. Restore storage/accounts.key before adding or editing accounts.');
                $temp=tempnam(dirname($file),'.key-');if($temp===false)throw new RuntimeException('Unable to create mailbox key.');
                try{if(!chmod($temp,0600)||file_put_contents($temp,random_bytes(32),LOCK_EX)!==32||!rename($temp,$file))throw new RuntimeException('Unable to save mailbox key.');}finally{if(is_file($temp))unlink($temp);}
            }}finally{flock($lock,LOCK_UN);fclose($lock);}
        }
        $key=@file_get_contents($file);if($key===false||strlen($key)!==32)throw new RuntimeException('Mailbox key is missing or unreadable. Restore storage/accounts.key from backup.');return $key;
    }
    private static function encrypt(array $config):string {
        if(!function_exists('openssl_encrypt'))throw new RuntimeException('PHP OpenSSL is required for mailbox credentials.');
        $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt(json_encode($config,JSON_THROW_ON_ERROR),'aes-256-gcm',self::key(true),OPENSSL_RAW_DATA,$iv,$tag);
        if($cipher===false)throw new RuntimeException('Unable to protect mailbox credentials.');return base64_encode($iv.$tag.$cipher);
    }
    public static function protect(array $config):string {return self::encrypt($config);}
    public static function config(int $id):array {
        $a=self::find($id);if(!$a)throw new RuntimeException('Mailbox account not found.');
        if((int)$a['legacy']===1)return SettingsService::imapConfig();
        if(!function_exists('openssl_decrypt'))throw new RuntimeException('PHP OpenSSL is required.');
        $bytes=base64_decode((string)$a['config_enc'],true);if($bytes===false||strlen($bytes)<29)throw new RuntimeException('Mailbox credentials are unreadable.');
        $plain=openssl_decrypt(substr($bytes,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($bytes,0,12),substr($bytes,12,16));if($plain===false)throw new RuntimeException('Mailbox credentials could not be decrypted. Restore the original accounts key.');return json_decode($plain,true,512,JSON_THROW_ON_ERROR);
    }
    public static function save(int $id,string $name,array $config):int {
        $name=trim($name);if($name===''||mb_strlen($name)>60)throw new InvalidArgumentException('Mailbox name must contain 1–60 characters.');
        $lock=$id>0?self::acquire($id):null;
        try{
            if($id>0&&!self::find($id))throw new InvalidArgumentException('Mailbox account not found.');$encrypted=self::encrypt($config);
            $db=Database::connection();$db->beginTransaction();try{
                if($id>0)$db->prepare('UPDATE mail_accounts SET name=?,config_enc=?,legacy=0 WHERE id=?')->execute([$name,$encrypted,$id]);
                else {$db->prepare('INSERT INTO mail_accounts(name,config_enc,legacy,selected) VALUES(?,?,0,1)')->execute([$name,$encrypted]);$id=(int)$db->lastInsertId();}
                if($id>0)Database::setSetting('account_'.$id.'_last_sync','0');self::changed();$db->commit();return $id;
            }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        }finally{self::release($lock);}
    }
    public static function remove(int $id):void {
        $lock=self::acquire($id);try{
        if(!self::find($id))throw new InvalidArgumentException('Mailbox not found.');$db=Database::connection();$db->beginTransaction();try{
            $q=$db->prepare('SELECT COUNT(*) FROM managed_domains WHERE account_id=?');$q->execute([$id]);if((int)$q->fetchColumn()>0)throw new InvalidArgumentException('Reassign or remove this mailbox’s domains first.');
            $db->prepare('DELETE FROM messages WHERE account_id=?')->execute([$id]);$prefix='account_'.$id.'_';$db->prepare('DELETE FROM app_settings WHERE substr(key,1,?)=?')->execute([strlen($prefix),$prefix]);$db->prepare('DELETE FROM mail_accounts WHERE id=?')->execute([$id]);self::changed();$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        }finally{self::release($lock);}
    }
    public static function select(array $ids):void {
        $ids=array_values(array_unique(array_map('intval',$ids)));$all=array_column(self::all(),'id');foreach($ids as $id)if(!in_array($id,array_map('intval',$all),true))throw new InvalidArgumentException('Unknown mailbox selection.');
        $db=Database::connection();$db->beginTransaction();try{$db->exec('UPDATE mail_accounts SET selected=0');$q=$db->prepare('UPDATE mail_accounts SET selected=1 WHERE id=?');foreach($ids as $id)$q->execute([$id]);self::changed();$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    public static function domains(bool $active=false):array {return Database::connection()->query('SELECT d.*,a.name AS account_name FROM managed_domains d LEFT JOIN mail_accounts a ON a.id=d.account_id'.($active?' WHERE d.active=1 AND a.id IS NOT NULL':'').' ORDER BY d.domain')->fetchAll();}
    public static function activeDomains():array {return array_column(self::domains(true),'domain');}
    public static function saveDomain(int $id,string $domain,int $account,bool $active):void {
        $domain=strtolower(trim($domain));if(!filter_var('x@'.$domain,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid domain.');
        $lock=self::acquire($account);try{
            $db=Database::connection();$db->beginTransaction();try{
                if(!self::find($account))throw new InvalidArgumentException('Choose a valid receiving mailbox.');
                if($id>0){$q=$db->prepare('SELECT id FROM managed_domains WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Domain not found.');$db->prepare('UPDATE managed_domains SET domain=?,account_id=?,active=? WHERE id=?')->execute([$domain,$account,$active?1:0,$id]);}
                else $db->prepare('INSERT INTO managed_domains(domain,account_id,active) VALUES(?,?,?)')->execute([$domain,$account,$active?1:0]);
                $db->commit();
            }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        }finally{self::release($lock);}
    }
}
