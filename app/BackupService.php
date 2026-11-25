<?php
declare(strict_types=1);
final class BackupService {
    private const AAD='PrivateMailBackup:v1:pbkdf2-sha256:310000';
    private const PREFS=['refresh_interval','message_limit','density','email_body_theme','theme','custom_theme'];
    public static function pack(array $payload,string $password):string {
        self::password($password);$salt=random_bytes(16);$iv=random_bytes(12);$key=hash_pbkdf2('sha256',$password,$salt,310000,32,true);$tag='';
        $cipher=openssl_encrypt(json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,self::AAD);if($cipher===false)throw new RuntimeException('Unable to encrypt backup.');
        $result=json_encode(['format'=>'PrivateMailEncrypted','version'=>1,'kdf'=>'pbkdf2-sha256','iterations'=>310000,'salt'=>base64_encode($salt),'iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'data'=>base64_encode($cipher)],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);if(strlen($result)>10485760)throw new RuntimeException('Backup is larger than 10 MiB. Reduce exported aliases before migrating.');return $result;
    }
    public static function password(string $password):void {if(strlen($password)<12||strlen($password)>256)throw new InvalidArgumentException('Use a backup password of 12–256 bytes.');if(!function_exists('openssl_encrypt'))throw new RuntimeException('PHP OpenSSL is required.');}
    public static function decode(string $bytes,string $password):array {
        if(strlen($bytes)>10485760)throw new InvalidArgumentException('Backup exceeds the 10 MiB limit.');
        try{$doc=json_decode($bytes,true,64,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new InvalidArgumentException('Invalid backup file.');}
        if(!is_array($doc))throw new InvalidArgumentException('Invalid backup file.');
        if(($doc['format']??'')==='PrivateMailEncrypted'){
            self::password($password);if(($doc['version']??null)!==1||($doc['kdf']??'')!=='pbkdf2-sha256'||($doc['iterations']??null)!==310000)throw new InvalidArgumentException('Unsupported backup format.');
            $salt=base64_decode((string)($doc['salt']??''),true);$iv=base64_decode((string)($doc['iv']??''),true);$tag=base64_decode((string)($doc['tag']??''),true);$data=base64_decode((string)($doc['data']??''),true);
            if($salt===false||strlen($salt)!==16||$iv===false||strlen($iv)!==12||$tag===false||strlen($tag)!==16||$data===false)throw new InvalidArgumentException('Damaged encrypted backup.');
            $plain=openssl_decrypt($data,'aes-256-gcm',hash_pbkdf2('sha256',$password,$salt,310000,32,true),OPENSSL_RAW_DATA,$iv,$tag,self::AAD);
            if($plain===false)throw new InvalidArgumentException('Incorrect backup password or modified backup.');
            try{$doc=json_decode($plain,true,64,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new InvalidArgumentException('Invalid encrypted contents.');}
            return self::validate($doc);
        }
        if(($doc['kind']??'')!=='settings')throw new InvalidArgumentException('Migration backups must be password encrypted.');return self::validate($doc);
    }
    private static function fields(array $config):array {
        if(!preg_match('/^\{([^:}]+):(\d+)([^}]*)\}(.*)$/',(string)($config['mailbox']??''),$m))throw new RuntimeException('A mailbox address cannot be exported. Update it in Settings first.');
        $tls=str_contains(strtolower($m[3]),'/ssl')?'ssl':(str_contains(strtolower($m[3]),'/tls')?'tls':'');if($tls==='')throw new RuntimeException('Only TLS-protected IMAP connections can be exported.');
        return ['host'=>strtolower($m[1]),'port'=>(int)$m[2],'tls'=>$tls,'folder'=>$m[4],'username'=>(string)($config['username']??''),'password'=>(string)($config['password']??''),'trash_mailbox'=>(string)($config['trash_mailbox']??''),'disable_gssapi'=>(($config['options']['DISABLE_AUTHENTICATOR']??'')==='GSSAPI')];
    }
    public static function snapshot(bool $secrets):array {
        $db=Database::connection();$own=!$db->inTransaction();if($own)$db->beginTransaction();try{
        $accounts=[];foreach(MailboxRegistry::all() as $a){$fields=self::fields(MailboxRegistry::config((int)$a['id']));if(!$secrets)unset($fields['password']);$accounts[]=array_merge($fields,['source_id'=>(int)$a['id'],'name'=>$a['name'],'selected'=>(bool)$a['selected']]);}
        $domains=[];foreach(MailboxRegistry::domains() as $d)$domains[]=['domain'=>$d['domain'],'account'=>$d['account_id']!==null?(int)$d['account_id']:null,'active'=>(bool)$d['active']];
        $aliases=array_map(fn($a)=>['local'=>$a['local_part'],'domain'=>$a['domain'],'created_at'=>$a['created_at']],Database::aliases('old'));
        $prefs=[];foreach(self::PREFS as $key){$v=Database::getSetting($key,null);if($v!==null)$prefs[$key]=$v;}
        $payload=['format'=>'PrivateMailPortable','version'=>1,'kind'=>$secrets?'migration':'settings','created_at'=>gmdate('c'),'accounts'=>$accounts,'domains'=>$domains,'aliases'=>$aliases,'preferences'=>$prefs];self::validate($payload);if($own)$db->commit();return $payload;
        }catch(Throwable $e){if($own&&$db->inTransaction())$db->rollBack();throw $e;}
    }
    public static function validate(mixed $doc):array {
        if(!is_array($doc)||($doc['format']??'')!=='PrivateMailPortable'||($doc['version']??null)!==1||!in_array($doc['kind']??'',['settings','migration'],true))throw new InvalidArgumentException('Unsupported PrivateMail backup.');
        foreach(['accounts'=>100,'domains'=>2000,'aliases'=>50000] as $key=>$max)if(!isset($doc[$key])||!is_array($doc[$key])||count($doc[$key])>$max)throw new InvalidArgumentException('Invalid or oversized '.$key.' list.');
        $accounts=[];$ids=[];foreach($doc['accounts'] as $a){
            if(!is_array($a)||!is_int($a['source_id']??null)||$a['source_id']<1||isset($ids[$a['source_id']]))throw new InvalidArgumentException('Invalid account references.');
            foreach(['name','host','tls','username','folder','password','trash_mailbox'] as $field)if(isset($a[$field])&&!is_string($a[$field]))throw new InvalidArgumentException('Invalid mailbox field.');if(!is_int($a['port']??null))throw new InvalidArgumentException('Invalid mailbox port.');foreach(['selected','disable_gssapi'] as $field)if(isset($a[$field])&&!is_bool($a[$field]))throw new InvalidArgumentException('Invalid mailbox flag.');
            $name=trim((string)($a['name']??''));if($name===''||mb_strlen($name)>60)throw new InvalidArgumentException('Invalid mailbox name.');
            $password=$doc['kind']==='migration'?(string)($a['password']??''):'';
            $config=SettingsService::connection(['host'=>$a['host']??'','port'=>$a['port']??0,'tls'=>$a['tls']??'','username'=>$a['username']??'','folder'=>$a['folder']??'INBOX','imap_password'=>$password!==''?$password:'validation-placeholder'],[]);
            $config['password']=$password;if(!empty($a['disable_gssapi']))$config['options']=['DISABLE_AUTHENTICATOR'=>'GSSAPI'];$trash=(string)($a['trash_mailbox']??'');if(strlen($trash)>200||preg_match('/[\x00-\x1f{}]/',$trash))throw new InvalidArgumentException('Invalid Trash folder.');if($trash!=='')$config['trash_mailbox']=$trash;
            $ids[$a['source_id']]=true;
            $accounts[]=['source_id'=>$a['source_id'],'name'=>$name,'config'=>$config,'selected'=>$password!==''&&!empty($a['selected'])];
        }
        $domains=[];$names=[];foreach($doc['domains'] as $d){if(!is_array($d)||!is_string($d['domain']??null)||(isset($d['active'])&&!is_bool($d['active'])))throw new InvalidArgumentException('Invalid domain.');$name=strtolower(trim((string)($d['domain']??'')));$account=$d['account']??null;if(!filter_var('x@'.$name,FILTER_VALIDATE_EMAIL)||isset($names[$name])||($account!==null&&(!is_int($account)||!isset($ids[$account]))))throw new InvalidArgumentException('Invalid or duplicate domain mapping.');$names[$name]=true;$domains[]=['domain'=>$name,'account'=>$account,'active'=>$account!==null&&!empty($d['active'])];}
        $aliases=[];$seen=[];foreach($doc['aliases'] as $a){if(!is_array($a)||!is_string($a['local']??null)||!is_string($a['domain']??null)||(isset($a['created_at'])&&!is_string($a['created_at'])))throw new InvalidArgumentException('Invalid alias.');$local=strtolower((string)($a['local']??''));$domain=strtolower((string)($a['domain']??''));if(!preg_match('/^[a-z0-9](?:[a-z0-9._-]{0,62}[a-z0-9])?$/D',$local)||str_contains($local,'..')||!filter_var('x@'.$domain,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Invalid alias in backup.');$email=$local.'@'.$domain;if(isset($seen[$email]))throw new InvalidArgumentException('Duplicate alias in backup.');$seen[$email]=true;$date=(string)($a['created_at']??'');if($date!==''&&(!preg_match('/^\d{4}-\d\d-\d\d[ T]\d\d:\d\d:\d\d(?:Z|[+-]\d\d:\d\d)?$/D',$date)||strtotime($date)===false))throw new InvalidArgumentException('Invalid alias date.');$aliases[]=['email'=>$email,'local'=>$local,'domain'=>$domain,'created_at'=>$date?:gmdate('Y-m-d H:i:s')];}
        $prefs=[];if(!is_array($doc['preferences']??[]))throw new InvalidArgumentException('Invalid preferences.');foreach((array)($doc['preferences']??[]) as $key=>$value){if(!in_array($key,self::PREFS,true))continue;if(!is_string($value))throw new InvalidArgumentException('Invalid preference value.');$valid=match($key){'refresh_interval'=>ctype_digit($value)&&((int)$value===0||((int)$value>=10&&(int)$value<=86400)),'message_limit'=>$value==='all'||(ctype_digit($value)&&(int)$value>=1&&(int)$value<=100000),'density'=>in_array($value,['comfortable','compact'],true),'email_body_theme'=>in_array($value,['light','dark'],true),'theme'=>in_array($value,['dark','light','system','custom'],true),'custom_theme'=>true,default=>false};if(!$valid)throw new InvalidArgumentException('Invalid '.$key.' preference.');if($key==='custom_theme'){try{$value=json_encode(SettingsService::theme(json_decode($value,true,16,JSON_THROW_ON_ERROR)),JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new InvalidArgumentException('Invalid custom theme.');}}$prefs[$key]=$value;}
        return ['kind'=>$doc['kind'],'accounts'=>$accounts,'domains'=>$domains,'aliases'=>$aliases,'preferences'=>$prefs];
    }
    private static function identity(array $config):string {$f=self::fields($config);return hash('sha256',json_encode([$f['host'],$f['port'],$f['tls'],$f['folder'],$f['username']],JSON_THROW_ON_ERROR));}
    public static function preview(array $data):array {
        $existing=[];foreach(MailboxRegistry::all() as $a)$existing[self::identity(MailboxRegistry::config((int)$a['id']))]=(int)$a['id'];
        $counts=['new_accounts'=>0,'existing_accounts'=>0,'new_domains'=>0,'existing_domains'=>0,'new_aliases'=>0,'existing_aliases'=>0,'passwords_missing'=>0];
        foreach($data['accounts'] as $a){$identity=self::identity($a['config']);$counts[isset($existing[$identity])?'existing_accounts':'new_accounts']++;$existing[$identity]=0;if($a['config']['password']==='')$counts['passwords_missing']++;}
        $domains=array_column(MailboxRegistry::domains(),'domain');foreach($data['domains'] as $d)$counts[in_array($d['domain'],$domains,true)?'existing_domains':'new_domains']++;
        $aliases=array_flip(array_column(Database::aliases(),'email'));foreach($data['aliases'] as $a)$counts[isset($aliases[$a['email']])?'existing_aliases':'new_aliases']++;
        return $counts;
    }
    public static function stage(array $data,string $mode,string $password=''):array {
        if(!function_exists('openssl_encrypt'))throw new RuntimeException('PHP OpenSSL is required for import.');if(!in_array($mode,['merge','replace'],true))throw new InvalidArgumentException('Invalid import mode.');if($mode==='replace')self::password($password);$data['_recovery_password']=$mode==='replace'?$password:'';$db=Database::connection();$db->beginTransaction();try{$counts=self::preview($data);$state=self::stateToken();$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}$key=random_bytes(32);$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt(json_encode($data,JSON_THROW_ON_ERROR),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'PrivateMailImportStage');if($cipher===false)throw new RuntimeException('Unable to stage import.');
        $file='import-'.bin2hex(random_bytes(16)).'.stage';self::privateWrite($file,$iv.$tag.$cipher);return ['file'=>$file,'key'=>base64_encode($key),'expires'=>time()+600,'mode'=>$mode,'view'=>$state,'counts'=>$counts,'kind'=>$data['kind']];
    }
    public static function staged(array $stage):array {
        if(($stage['expires']??0)<time()||!preg_match('/^import-[a-f0-9]{32}\.stage$/D',(string)($stage['file']??'')))throw new InvalidArgumentException('Preview expired. Upload the backup again.');
        $bytes=@file_get_contents(__DIR__.'/../storage/'.$stage['file']);$key=base64_decode((string)($stage['key']??''),true);if($bytes===false||strlen($bytes)<29||$key===false||strlen($key)!==32)throw new RuntimeException('Preview is unavailable. Upload again.');$plain=openssl_decrypt(substr($bytes,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($bytes,0,12),substr($bytes,12,16),'PrivateMailImportStage');if($plain===false)throw new RuntimeException('Preview could not be verified.');return json_decode($plain,true,64,JSON_THROW_ON_ERROR);
    }
    public static function discard(?array $stage):void {if($stage&&preg_match('/^import-[a-f0-9]{32}\.stage$/D',(string)($stage['file']??'')))@unlink(__DIR__.'/../storage/'.$stage['file']);}
    private static function privateWrite(string $name,string $bytes):void {$path=__DIR__.'/../storage/'.$name;$h=@fopen($path,'x');if(!$h)throw new RuntimeException('Private storage must be writable.');try{chmod($path,0600);$written=0;while($written<strlen($bytes)){$n=fwrite($h,substr($bytes,$written));if($n===false||$n===0)throw new RuntimeException('Unable to write protected file.');$written+=$n;}if(!fflush($h))throw new RuntimeException('Unable to flush protected file.');}catch(Throwable $e){fclose($h);@unlink($path);throw $e;}finally{if(is_resource($h))fclose($h);}}
    public static function apply(array $data,string $mode,string $recoveryPassword,string $expectedView):?string {
        if(!in_array($mode,['merge','replace'],true))throw new InvalidArgumentException('Invalid import mode.');$locks=[];$recovery=null;$db=Database::connection();
        try{
            $ids=array_map(fn($a)=>(int)$a['id'],MailboxRegistry::all());sort($ids);foreach($ids as $id)$locks[]=MailboxRegistry::acquire($id);
            if(!hash_equals($expectedView,self::stateToken()))throw new RuntimeException('Mailbox settings changed since preview. Upload again.');
            if($mode==='replace'){self::password($recoveryPassword);$recovery='rollback-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(6)).'.pmb';self::privateWrite($recovery,self::pack(self::snapshot(true),$recoveryPassword));}
            $db->beginTransaction();$db->exec('UPDATE mail_accounts SET selected=selected WHERE id=-1');$current=array_map(fn($a)=>(int)$a['id'],MailboxRegistry::all());sort($current);if($current!==$ids||!hash_equals($expectedView,self::stateToken()))throw new RuntimeException('Settings changed. Upload again.');
            $map=[];$existing=[];foreach(MailboxRegistry::all() as $a)$existing[self::identity(MailboxRegistry::config((int)$a['id']))]=(int)$a['id'];
            if($mode==='replace'){$db->exec('DELETE FROM messages');$db->exec('DELETE FROM aliases');$db->exec('DELETE FROM managed_domains');$db->exec('DELETE FROM mail_accounts');foreach($ids as $id){$prefix='account_'.$id.'_';$db->prepare('DELETE FROM app_settings WHERE substr(key,1,?)=?')->execute([strlen($prefix),$prefix]);}$existing=[];}
            foreach($data['accounts'] as $a){$identity=self::identity($a['config']);if($mode==='merge'&&isset($existing[$identity])){$map[$a['source_id']]=$existing[$identity];continue;}$enc=MailboxRegistry::protect($a['config']);$db->prepare('INSERT INTO mail_accounts(name,config_enc,legacy,selected) VALUES(?,?,0,?)')->execute([$a['name'],$enc,$a['selected']?1:0]);$id=(int)$db->lastInsertId();$map[$a['source_id']]=$id;$existing[$identity]=$id;}
            foreach($data['domains'] as $d)$db->prepare('INSERT OR IGNORE INTO managed_domains(domain,account_id,active) VALUES(?,?,?)')->execute([$d['domain'],$d['account']!==null?($map[$d['account']]??null):null,$d['active']?1:0]);
            foreach($data['aliases'] as $a)$db->prepare('INSERT OR IGNORE INTO aliases(email,local_part,domain,created_at) VALUES(?,?,?,?)')->execute([$a['email'],$a['local'],$a['domain'],$a['created_at']]);
            foreach($data['preferences'] as $key=>$value)Database::setSetting($key,$value);Database::setSetting('mailbox_view_revision',bin2hex(random_bytes(12)));$db->commit();if($mode==='replace'){$files=glob(__DIR__.'/../storage/rollback-*.pmb')?:[];rsort($files);foreach(array_slice($files,5) as $file)@unlink($file);}return $recovery;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}finally{foreach(array_reverse($locks) as $lock)MailboxRegistry::release($lock);}
    }
    public static function stateToken():string {
        $prefs=[];foreach(self::PREFS as $key)$prefs[$key]=Database::getSetting($key,null);
        return hash('sha256',json_encode([MailboxRegistry::all(),MailboxRegistry::domains(),Database::aliases(),$prefs],JSON_THROW_ON_ERROR));
    }
    public static function cleanup():void {foreach(glob(__DIR__.'/../storage/import-*.stage')?:[] as $file)if(filemtime($file)<time()-1800)@unlink($file);}
    public static function recoveries():array {$files=glob(__DIR__.'/../storage/rollback-*.pmb')?:[];rsort($files);return array_map('basename',array_slice($files,0,5));}
}
