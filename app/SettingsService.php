<?php
declare(strict_types=1);
final class SettingsService {
    public static function themeDefaults(): array {return ['name'=>'My theme','accent'=>'#A3E635','background'=>'#09090B','surface'=>'#121215','text'=>'#FAFAFA'];}
    public static function theme(array $input): array {
        $out=self::themeDefaults();$out['name']=trim((string)($input['name']??$out['name']));
        if($out['name']===''||mb_strlen($out['name'])>40)throw new InvalidArgumentException('Theme name must contain 1–40 characters.');
        foreach(['accent','background','surface','text'] as $key){$value=(string)($input[$key]??$out[$key]);if(!preg_match('/^#[0-9a-fA-F]{6}$/D',$value))throw new InvalidArgumentException('Use a valid six-digit theme colour.');$out[$key]=strtoupper($value);}
        return $out;
    }
    public static function savedTheme(): array {try{return self::theme(json_decode((string)Database::getSetting('custom_theme','{}'),true)?:[]);}catch(Throwable $e){return self::themeDefaults();}}
    public static function themeStyle(array $theme): string {
        $t=self::theme($theme);$rgb=sscanf($t['accent'],'#%02x%02x%02x');
        return 'color-scheme:'.(self::luminance($t['background'])>.35?'light':'dark').';--bg:'.$t['background'].';--surface:'.$t['surface'].';--surface2:'.$t['surface'].';--surface3:'.$t['surface'].';--text:'.$t['text'].';--muted:'.$t['text'].';--accent:'.$t['accent'].';--accent-hover:'.$t['accent'].';--selected-border:'.$t['accent'].';--soft:rgba('.implode(',',$rgb).',.12);--soft-text:'.$t['accent'].';--border-color:color-mix(in srgb,'.$t['text'].' 16%,'.$t['surface'].');--on-accent:'.(self::luminance($t['accent'])>.35?'#09090B':'#FFFFFF').';';
    }
    private static function luminance(string $hex): float {$rgb=sscanf($hex,'#%02x%02x%02x');$c=array_map(function($v){$v/=255;return $v<=.04045?$v/12.92:pow(($v+.055)/1.055,2.4);},$rgb);return .2126*$c[0]+.7152*$c[1]+.0722*$c[2];}
    public static function imapConfig(): array {$p=__DIR__.'/../config/imap.php';return is_file($p)?require $p:[];}
    public static function connection(array $input,?array $base=null): array {
        $old=$base??self::imapConfig();$host=trim((string)($input['host']??''));$port=filter_var($input['port']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);$tls=(string)($input['tls']??'ssl');
        if(!preg_match('/^(?=.{1,253}$)[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?$/D',$host)||!$port||!in_array($tls,['ssl','tls'],true))throw new InvalidArgumentException('Enter a valid IMAP host, port and encryption mode.');
        $user=trim((string)($input['username']??''));$pass=(string)($input['imap_password']??'');
        if($pass===''&&!empty($old['password'])){preg_match('/^\{([^:}]+)/',(string)($old['mailbox']??''),$previous);if(strcasecmp($host,(string)($previous[1]??''))!==0||$user!==(string)($old['username']??''))throw new InvalidArgumentException('Enter the mailbox password again when changing the host or username.');$pass=(string)$old['password'];}
        if($user===''||strlen($user)>320||strpbrk($user,"\r\n\0")!==false||$pass==='')throw new InvalidArgumentException('IMAP username and password are required.');
        $folder=(string)($input['folder']??'INBOX');if($folder===''||strlen($folder)>200||preg_match('/[\x00-\x1f{}]/',$folder))throw new InvalidArgumentException('Invalid mailbox folder.');
        $old['mailbox']='{'.$host.':'.$port.'/imap/'.$tls.'}'.$folder;$old['username']=$user;$old['password']=$pass;return $old;
    }
    public static function test(array $config): void {
        if(!function_exists('imap_open'))throw new RuntimeException('The PHP IMAP extension is unavailable.');
        foreach([IMAP_OPENTIMEOUT,IMAP_READTIMEOUT,IMAP_WRITETIMEOUT,IMAP_CLOSETIMEOUT] as $kind)imap_timeout($kind,8);
        $imap=@imap_open($config['mailbox'],$config['username'],$config['password'],OP_READONLY,1,$config['options']??[]);
        if(!$imap){imap_errors();throw new RuntimeException('Connection failed. Check the host, encryption, username and mailbox password. Existing settings were kept.');}
        imap_close($imap);imap_errors();
    }
    public static function saveImap(array $config): void {
        $dir=__DIR__.'/../config';$temp=@tempnam($dir,'.imap-');if($temp===false)throw new RuntimeException('The config folder must be writable.');
        try{chmod($temp,0600);$bytes="<?php\ndeclare(strict_types=1);\nreturn ".var_export($config,true).";\n";if(file_put_contents($temp,$bytes,LOCK_EX)!==strlen($bytes)||!rename($temp,$dir.'/imap.php'))throw new RuntimeException('Unable to save IMAP settings. Check config folder permissions.');if(function_exists('opcache_invalidate'))opcache_invalidate($dir.'/imap.php',true);}finally{if(is_file($temp))unlink($temp);}
    }
}
