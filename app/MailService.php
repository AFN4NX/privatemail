<?php
declare(strict_types=1);
final class MailService {
    private int $accountId=1;
    private function assertCurrentAccount():void {if(MailboxRegistry::config($this->accountId)!==$this->config)throw new RuntimeException('Mailbox settings changed. Refresh and retry.');}
    private function setting(string $key,mixed $default=null):mixed{return Database::getSetting('account_'.$this->accountId.'_'.$key,$default);}
    private function saveSetting(string $key,string $value):void{Database::setSetting('account_'.$this->accountId.'_'.$key,$value);}
    private string $renderChannel=''; private $imap; private array $config; private array $bodyCache=[]; private array $overviewCache=[];
    public function __construct(bool $readOnly=true,?int $accountId=null){
        $this->accountId=$accountId??(MailboxRegistry::selectedIds()[0]??0);
        if(!function_exists('imap_open'))throw new RuntimeException('PHP IMAP extension is not enabled.');
        $this->config=MailboxRegistry::config($this->accountId);$flags=$readOnly?OP_READONLY:0;
        foreach([IMAP_OPENTIMEOUT,IMAP_READTIMEOUT,IMAP_WRITETIMEOUT,IMAP_CLOSETIMEOUT] as $kind) imap_timeout($kind,10);
        if(preg_match('~/novalidate-cert|/notls~i',$this->config['mailbox'])) throw new RuntimeException('IMAP certificate validation and TLS are required.');
        $this->imap=@imap_open($this->config['mailbox'],$this->config['username'],$this->config['password'],$flags,1,$this->config['options']??[]);
        if(!$this->imap){throw new RuntimeException('Unable to connect to IMAP. Check the server configuration.');}
    }
    public function __destruct(){if($this->imap)@imap_close($this->imap);}
    public function pollStatus(): array {
        $status=imap_status($this->imap,$this->config['mailbox'],SA_UIDNEXT|SA_UNSEEN|SA_UIDVALIDITY);
        if(!$status)throw new RuntimeException('Mailbox status unavailable.');
        $identity=hash('sha256',$this->config['mailbox']."\n".$this->config['username']."\n".$status->uidvalidity);
        $lastNext=(int)$this->setting('poll_uidnext','0');
        if($this->setting('mailbox_identity','')!==$identity||$lastNext===0||(int)$status->uidnext>$lastNext){
            // An IMAP STATUS probe avoids listing/fetching messages on unchanged ticks.
            $this->synchronize(true);
        }
        return ['latest_id'=>(int)Database::connection()->query('SELECT COALESCE(MAX(id),0) FROM messages')->fetchColumn(),'unread_count'=>(int)$status->unseen];
    }
    public function latestUids(int $limit=50):array {
        $limit=max(1,min(200,$limit));$count=imap_num_msg($this->imap);
        if($count===0)return [];
        $first=max(1,$count-$limit+1);
        $overview=imap_fetch_overview($this->imap,$first.':'.$count,0);
        if($overview===false)throw new RuntimeException('Unable to list messages.');
        foreach($overview as $item)$this->overviewCache[(int)$item->uid]=$item;
        return array_reverse(array_map(fn($m)=>(int)$m->uid,$overview));
    }
    public function synchronize(bool $force=false):void {
        $lock=fopen(__DIR__.'/../storage/sync-'.$this->accountId.'.lock','c');
        if(!$lock)throw new RuntimeException('Unable to acquire mailbox lock.');
        if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return;}
        try {
            $this->assertCurrentAccount();
            $configured=(int)Database::getSetting('refresh_interval','0');$cooldown=$configured>0?max(10,$configured):SYNC_INTERVAL_SECONDS;
            if(!$force && time()-(int)$this->setting('last_sync','0')<$cooldown)return;
            $status=imap_status($this->imap,$this->config['mailbox'],SA_UIDVALIDITY|SA_UIDNEXT);
            if(!$status)throw new RuntimeException('Unable to read mailbox identity.');
            $identity=hash('sha256',$this->config['mailbox']."\n".$this->config['username']."\n".$status->uidvalidity);
            if($this->setting('mailbox_identity','')!==$identity){
                Database::connection()->prepare('DELETE FROM messages WHERE account_id=?')->execute([$this->accountId]);
                $this->saveSetting('mailbox_identity',$identity);$this->saveSetting('history_cursor','0');$this->saveSetting('history_complete','0');
            }
            $failed=false;$reclassify=$this->setting('otp_parser_version','')!=='2';
            foreach($this->latestUids(MAIL_PAGE_SIZE) as $uid){
                try{
                    if(!empty($this->overviewCache[$uid]->deleted)){Database::connection()->prepare('DELETE FROM messages WHERE account_id=? AND imap_uid=?')->execute([$this->accountId,$uid]);continue;}
                    $cached=Database::connection()->prepare('SELECT id FROM messages WHERE account_id=? AND imap_uid=?');$cached->execute([$this->accountId,$uid]);
                    if(!$cached->fetchColumn()||$reclassify)$this->fetchSummary($uid);
                    else {
                        $o=isset($this->overviewCache[$uid])?[$this->overviewCache[$uid]]:[];
                        if($o)Database::connection()->prepare('UPDATE messages SET is_read=? WHERE account_id=? AND imap_uid=?')->execute([!empty($o[0]->seen)?1:0,$this->accountId,$uid]);
                    }
                }catch(Throwable $e){$failed=true;error_log('PrivateMail: summary fetch failed for UID '.$uid);}
            }
            if($failed)throw new RuntimeException('Some messages could not be synchronized. Cached mail is available; retry refresh.');
            $this->saveSetting('poll_uidnext',(string)$status->uidnext);$this->saveSetting('otp_parser_version','2');$this->saveSetting('last_sync',(string)time());
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function backfillOlder(): void {
        $lock=fopen(__DIR__.'/../storage/sync-'.$this->accountId.'.lock','c');if(!$lock)throw new RuntimeException('Unable to lock mailbox.');
        if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);throw new RuntimeException('Mailbox is busy. Try again shortly.');}
        try{
            $this->verifyCachedMailbox();
            $cursor=(int)$this->setting('history_cursor','0');if($cursor===0)$cursor=(int)Database::connection()->query('SELECT COALESCE(MIN(imap_uid),0) FROM messages WHERE account_id='.$this->accountId)->fetchColumn();
            if($cursor<=1){$this->saveSetting('history_complete','1');return;}
            $uids=imap_search($this->imap,'UNDELETED UID 1:'.($cursor-1),SE_UID);
            if($uids===false){$errors=imap_errors();if($errors)throw new RuntimeException('Unable to list older mail.');$this->saveSetting('history_complete','1');return;}
            rsort($uids,SORT_NUMERIC);$batch=array_slice($uids,0,25);$start=microtime(true);$done=0;
            foreach($batch as $uid){
                $cached=Database::connection()->prepare('SELECT id FROM messages WHERE account_id=? AND imap_uid=?');$cached->execute([$this->accountId,(int)$uid]);if(!$cached->fetchColumn())$this->fetchSummary((int)$uid);
                $this->saveSetting('history_cursor',(string)$uid);$done++;if(microtime(true)-$start>8)break;
            }
            $this->saveSetting('history_complete',$done===count($uids)?'1':'0');
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function fetchSummary(int $uid):array{
        $o=@imap_fetch_overview($this->imap,(string)$uid,FT_UID);if(!$o||!isset($o[0]))throw new RuntimeException('Message not found.');
        $from=$this->decode((string)($o[0]->from??''));[$name,$email]=$this->address($from);[$alias,$originalTo]=$this->recipientInfo($uid);
        $subject=trim($this->decode((string)($o[0]->subject??'')))?:'(No subject)';$body=$this->text($uid);$c=MailClassifier::classify($email,$subject,$body);
        $m=['account_id'=>$this->accountId,'imap_uid'=>$uid,'message_id'=>(string)($o[0]->message_id??''),'alias_email'=>$alias,'original_to'=>$originalTo,'sender_name'=>$name,'sender_email'=>$email,'subject'=>$subject,'preview'=>$this->preview($body),'category'=>$c['category'],'subcategory'=>$c['subcategory'],'code'=>$c['code'],'received_at'=>$this->normalizeDate((string)($o[0]->date??'')),'is_read'=>!empty($o[0]->seen)?1:0];
        $m['id']=Database::upsert($m);return $m;
    }
    public function markRead(int $uid):bool{
        try {
            if(!@imap_reopen($this->imap,$this->config['mailbox'],0,1))return false;
            return (bool)@imap_setflag_full($this->imap,(string)$uid,'\\Seen',ST_UID);
        }catch(Throwable $e){return false;}
    }
    private function verifyCachedMailbox():void {
        $this->assertCurrentAccount();
        $status=imap_status($this->imap,$this->config['mailbox'],SA_UIDVALIDITY);
        if(!$status)throw new RuntimeException('Unable to verify mailbox.');
        $identity=hash('sha256',$this->config['mailbox']."\n".$this->config['username']."\n".$status->uidvalidity);
        if(!hash_equals((string)$this->setting('mailbox_identity',''),$identity))throw new RuntimeException('Mailbox changed. Refresh the inbox before retrying.');
    }
    public function rawSource(int $uid):string {
        $this->verifyCachedMailbox();
        $overview=imap_fetch_overview($this->imap,(string)$uid,FT_UID);
        if(!$overview)throw new RuntimeException('Message not found.');
        if((int)($overview[0]->size??0)>10485760)throw new RuntimeException('Source preview is limited to 10 MiB.');
        $header=imap_fetchheader($this->imap,$uid,FT_UID);
        $body=imap_body($this->imap,$uid,FT_UID|FT_PEEK);
        if($header===false||$body===false)throw new RuntimeException('Unable to read the message source.');
        return $header."\r\n".$body;
    }
    public function markAllRead():void {
        $this->verifyCachedMailbox();
        // Server-side flags include unread mail outside the loaded history window.
        imap_errors();
        $uids=imap_search($this->imap,'UNSEEN',SE_UID);
        $errors=imap_errors();
        if($uids===false&&$errors)throw new RuntimeException('Unable to list unread messages.');
        foreach(array_chunk($uids?:[],250) as $chunk){
            $this->assertCurrentAccount();
            if(!imap_setflag_full($this->imap,implode(',',array_map('intval',$chunk)),'\\Seen',ST_UID))throw new RuntimeException('Unable to mark messages read.');
            $params=array_merge([$this->accountId],array_map('intval',$chunk));
            Database::connection()->prepare('UPDATE messages SET is_read=1 WHERE account_id=? AND imap_uid IN ('.implode(',',array_fill(0,count($chunk),'?')).')')->execute($params);
        }
        Database::connection()->prepare('UPDATE messages SET is_read=1 WHERE account_id=?')->execute([$this->accountId]);
    }
    public function setRead(int $uid,bool $read):void {
        $this->verifyCachedMailbox();
        if(!imap_reopen($this->imap,$this->config['mailbox'],0,1))throw new RuntimeException('Unable to update message.');
        $ok=$read?imap_setflag_full($this->imap,(string)$uid,'\\Seen',ST_UID):imap_clearflag_full($this->imap,(string)$uid,'\\Seen',ST_UID);
        if(!$ok)throw new RuntimeException('Unable to update message status.');
    }
    public function moveToTrash(int $uid):void {
        $this->verifyCachedMailbox();
        if(!imap_reopen($this->imap,$this->config['mailbox'],0,1))throw new RuntimeException('Unable to open the mailbox for moving mail.');
        $overview=imap_fetch_overview($this->imap,(string)$uid,FT_UID);
        if(!$overview)throw new RuntimeException('Message no longer exists.');
        if(!empty($overview[0]->deleted))return;
        $end=strpos($this->config['mailbox'],'}');
        if($end===false)throw new RuntimeException('Invalid mailbox configuration.');
        $server=substr($this->config['mailbox'],0,$end+1);
        $current=substr($this->config['mailbox'],$end+1);
        $boxes=imap_getmailboxes($this->imap,$server,'*')?:[];$available=[];
        foreach($boxes as $box)$available[substr($box->name,strlen($server))]=true;
        $configured=(string)($this->config['trash_mailbox']??'');
        $candidates=$configured!==''?[$configured]:['Trash','INBOX.Trash','Deleted Items','Deleted Messages'];
        $trash=null;
        foreach($candidates as $candidate)if(isset($available[$candidate])&&strcasecmp($candidate,$current)!==0){$trash=$candidate;break;}
        if($trash===null)throw new RuntimeException('Trash folder not found. Set trash_mailbox in config/imap.php.');
        if(!imap_mail_move($this->imap,(string)$uid,$trash,CP_UID))throw new RuntimeException('Unable to move the message to Trash.');
        // No EXPUNGE: never permanently remove other messages marked deleted by another client.
    }
    public function fetchFullMessage(int $uid):array{
        $this->verifyCachedMailbox();
        $o=@imap_fetch_overview($this->imap,(string)$uid,FT_UID);if(!$o||!isset($o[0]))throw new RuntimeException('Message not found.');$s=@imap_fetchstructure($this->imap,$uid,FT_UID);if(!$s)throw new RuntimeException('Unable to read MIME structure.');
        $parts=[];$this->collectTextParts($uid,$s,'',$parts);$html='';$plain='';foreach($parts as $p){if($p['subtype']==='HTML'&&$html==='')$html=$p['body'];if($p['subtype']==='PLAIN'&&$plain==='')$plain=$p['body'];}if($html!=='')$html=$this->sanitizeHtml($html);
        $from=$this->decode((string)($o[0]->from??''));[$sn,$se]=$this->address($from);[$alias,$originalTo]=$this->recipientInfo($uid);
        return ['uid'=>$uid,'message_id'=>(string)($o[0]->message_id??''),'subject'=>trim($this->decode((string)($o[0]->subject??'')))?:'(No subject)','sender_name'=>$sn,'sender_email'=>$se,'to'=>$this->decode((string)($o[0]->to??'')),'cc'=>$this->decode((string)($o[0]->cc??'')),'alias_email'=>$alias,'original_to'=>$originalTo,'date'=>$this->normalizeDate((string)($o[0]->date??'')),'html'=>$html,'text'=>$plain,'frame_token'=>$this->renderChannel,'code'=>MailClassifier::code((string)($o[0]->subject??'')."\n".$plain."\n".$html)];
    }
    private function recipientInfo(int $uid):array{
        $h=@imap_fetchheader($this->imap,$uid,FT_UID);
        if(!$h)return['',''];
        $h=preg_replace("/\r?\n[ \t]+/",' ',$h)??$h;

        // The IMAP mailbox is only the transport destination. We want the
        // address the message was actually sent/forwarded to.
        $transport=[];
        try{
            $u=(string)($this->config['username']??'');
            foreach($this->extractEmails($u) as $e)$transport[strtolower($e)]=true;
            $m=(string)($this->config['mailbox']??'');
            foreach($this->extractEmails($m) as $e)$transport[strtolower($e)]=true;
        }catch(Throwable $e){}

        // 1) Other-hosting/domain forwarders explicitly tell us the owner
        // address in this header, e.g. "owner mehedi@... -> netflix@...".
        if(preg_match('/^X-Get-Message-Sender-Via:\s*.*?owner\s+([^\s]+@[^\s]+)\s*->/im',$h,$m)){
            $e=strtolower(trim($m[1]));
            if(filter_var($e,FILTER_VALIDATE_EMAIL))return['',$e];
        }

        // 2) Gmail forwarding keeps the original sender/forwarding address
        // in X-Forwarded-For and the inner Delivered-To header.
        if(preg_match_all('/^X-Forwarded-For:\s*(.+)$/im',$h,$ms)){
            foreach($ms[1] as $v){
                foreach($this->extractEmails($v) as $e){
                    if(!isset($transport[strtolower($e)]))return['',$e];
                }
            }
        }

        // 3) Catch-all delivery: Envelope-To / X-To / To contain the address
        // that the sender actually targeted. Delivered-To is also useful for
        // forwarded mail, as long as it is not our transport mailbox.
        foreach(['Envelope-To','X-Envelope-To','X-To','To','X-Original-To','Original-To','Delivered-To'] as $key){
            if(preg_match_all('/^'.preg_quote($key,'/').':\s*(.+)$/im',$h,$ms)){
                foreach($ms[1] as $v)foreach($this->extractEmails($v) as $e){
                    $le=strtolower($e);
                    if(isset($transport[$le]))continue;
                    return['',$e];
                }
            }
        }

        // Last fallback for providers that only expose the original address
        // inside the message body/footer (e.g. "This message was emailed to").
        $body=$this->text($uid);
        if(preg_match('/(?:emailed|mailed|sent)\s+to\s+[^<\[]*[<\[]?([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i',$body,$m)){
            $e=strtolower($m[1]);
            if(!isset($transport[$e])&&filter_var($e,FILTER_VALIDATE_EMAIL))return['',$e];
        }
        return['',''];
    }
    private function extractEmails(string $v):array{$out=[];if(preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',$v,$m))foreach($m[0] as $e){$e=strtolower(trim($e," \t<>\"'"));if(filter_var($e,FILTER_VALIDATE_EMAIL))$out[]=$e;}return array_values(array_unique($out));}
    private function text(int $uid):string{if(isset($this->bodyCache[$uid]))return $this->bodyCache[$uid];$s=@imap_fetchstructure($this->imap,$uid,FT_UID);if(!$s)return'';$p=[];$this->collectTextParts($uid,$s,'',$p);foreach($p as $x)if($x['subtype']==='PLAIN')return $this->bodyCache[$uid]=$x['body'];foreach($p as $x)if($x['subtype']==='HTML')return $this->bodyCache[$uid]=trim(html_entity_decode(strip_tags($x['body']),ENT_QUOTES|ENT_HTML5,'UTF-8'));return'';}
    private function collectTextParts(int $uid,object $s,string $prefix,array &$out):void{if(array_sum(array_map(fn($part)=>strlen($part['body']),$out))>=4194304||count($out)>=50||substr_count($prefix,'.')>20||strtoupper((string)($s->disposition??''))==='ATTACHMENT')return;if(!empty($s->parts)){foreach($s->parts as $i=>$p){$n=$prefix===''?(string)($i+1):$prefix.'.'.($i+1);$this->collectTextParts($uid,$p,$n,$out);}return;}$disposition=strtoupper((string)($s->disposition??''));if($disposition==='ATTACHMENT'||(int)($s->bytes??0)>2097152)return;$type=(int)($s->type??-1);$sub=strtoupper((string)($s->subtype??''));if($type!==0||!in_array($sub,['PLAIN','HTML'],true))return;$remaining=4194304-array_sum(array_map(fn($part)=>strlen($part['body']),$out));if((int)($s->bytes??0)>$remaining)return;$n=$prefix===''?'1':$prefix;$d=@imap_fetchbody($this->imap,$uid,$n,FT_UID|FT_PEEK);if($d===false)return;$d=$this->decodeBody((string)$d,(int)($s->encoding??0));$d=$this->toUtf8($d,$this->partCharset($s));if(strlen($d)>$remaining)return;$out[]=['subtype'=>$sub,'body'=>$d,'section'=>$n];}
    private function decodeBody(string $d,int $e):string{return match($e){3=>base64_decode($d,true)?:'',4=>quoted_printable_decode($d),default=>$d};}
    private function partCharset(object $p):string{foreach(['parameters','dparameters'] as $prop)foreach(($p->{$prop}??[]) as $x)if(strtolower((string)($x->attribute??''))==='charset')return(string)($x->value??'UTF-8');return'UTF-8';}
    private function toUtf8(string $t,string $c):string{$c=trim($c," \t\r\n\"'");if($c===''||strtoupper($c)==='UTF-8'||strtoupper($c)==='US-ASCII')return$t;if(function_exists('mb_convert_encoding')){$x=$this->convertCharset($t,$c);if($x!==false)return$x;}return$t;}
    private function convertCharset(string $text,string $charset):string{try{return mb_convert_encoding($text,'UTF-8',$charset);}catch(ValueError $e){return $text;}}
    private function sanitizeHtml(string $html):string {
        $this->renderChannel=bin2hex(random_bytes(24));
        if(class_exists('DOMDocument')){
            $previous=libxml_use_internal_errors(true);
            $doc=new DOMDocument('1.0','UTF-8');
            $doc->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><body>'.$html.'</body></html>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
            libxml_clear_errors();libxml_use_internal_errors($previous);
            $allowed=['html','head','body','title','style','div','span','p','br','hr','a','img','table','thead','tbody','tfoot','tr','td','th','col','colgroup','h1','h2','h3','h4','h5','h6','strong','b','em','i','u','s','small','font','center','blockquote','pre','code','ul','ol','li','dl','dt','dd','sup','sub'];
            $attributes=['style','class','id','title','alt','width','height','align','valign','bgcolor','color','face','size','border','cellpadding','cellspacing','colspan','rowspan','dir','lang','role'];
            foreach(iterator_to_array($doc->getElementsByTagName('*')) as $element){
                $tag=strtolower($element->tagName);
                if(!in_array($tag,$allowed,true)){$element->parentNode?->removeChild($element);continue;}
                foreach(iterator_to_array($element->attributes) as $attribute){
                    $name=strtolower($attribute->name);
                    if(!in_array($name,$attributes,true)&&!($tag==='a'&&$name==='href')&&!($tag==='img'&&$name==='src'))$element->removeAttributeNode($attribute);
                }
                if($tag==='a'){
                    $url=trim($element->getAttribute('href'));
                    $scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));
                    $valid=in_array($scheme,['http','https'],true)&&filter_var($url,FILTER_VALIDATE_URL)&&!preg_match('/[\x00-\x20\x7f]/',$url);
                    if($valid){$element->setAttribute('target','_blank');$element->setAttribute('rel','noopener noreferrer');}
                    else $element->removeAttribute('href');
                }
                if($tag==='img'&&!preg_match('~^data:image/(?:png|jpeg|gif|webp);base64,[a-z0-9+/=\s]+$~i',$element->getAttribute('src'))){
                    $label=$doc->createElement('span');$label->setAttribute('style','font:12px sans-serif;color:#64748b;');
                    $label->appendChild($doc->createTextNode($element->getAttribute('alt')));$element->parentNode?->replaceChild($label,$element);
                }
            }
            $content='';
            $head=$doc->getElementsByTagName('head')->item(0);
            if($head)foreach($head->getElementsByTagName('style') as $style)$content.=$doc->saveHTML($style);
            $body=$doc->getElementsByTagName('body')->item(0);
            if($body)foreach($body->childNodes as $node)$content.=$doc->saveHTML($node);
        }else{
            // Fail closed when DOM is unavailable. Never trust raw email HTML.
            $content='<pre style="white-space:pre-wrap">'.htmlspecialchars(strip_tags($html),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</pre>';
        }
        $nonce=$this->renderChannel;
        $policy="default-src 'none'; script-src 'nonce-$nonce'; style-src 'unsafe-inline'; img-src data:; base-uri 'none'; form-action 'none';";
        $bridge=<<<'JS'
(()=>{for(const node of [document.documentElement,document.body]){node.style.setProperty('overflow','hidden','important');node.style.setProperty('height','auto','important');node.style.setProperty('min-height','0','important');}const originals=new Map();function setTheme(mode){document.documentElement.dataset.mailTheme=mode;if(mode==='dark'){for(const node of [document.body,...document.body.querySelectorAll('table,tbody,tr,td,th,div,section,p,span,h1,h2,h3,h4,h5,h6,strong,small,li,a')]){if(!originals.has(node))originals.set(node,node.getAttribute('style'));if(['BODY','TABLE','TBODY','TR','TD','TH','DIV','SECTION'].includes(node.tagName))node.style.setProperty('background-color','#121215','important');node.style.setProperty('color',node.tagName==='A'?'#A3E635':'#FAFAFA','important');}}else{for(const [node,style] of originals){if(style===null)node.removeAttribute('style');else node.setAttribute('style',style);}originals.clear();}}let old=0,queued=false;function measure(){queued=false;const h=Math.min(250000,Math.max(1,Math.ceil(document.body.getBoundingClientRect().height),document.body.scrollHeight));if(h!==old){old=h;parent.postMessage({type:'privatemail:resize',channel:document.currentScript?.nonce||CHANNEL,height:h},'*');}}const CHANNEL=document.currentScript.nonce;const schedule=()=>{if(!queued){queued=true;requestAnimationFrame(measure);}};new ResizeObserver(schedule).observe(document.body);addEventListener('load',schedule);addEventListener('resize',schedule);document.fonts?.ready.then(schedule);addEventListener('message',e=>{if(e.source!==parent||e.data?.channel!==CHANNEL)return;if(e.data.type==='privatemail:measure'){old=0;schedule();}if(e.data.type==='privatemail:theme'&&['dark','light'].includes(e.data.mode)){setTheme(e.data.mode);old=0;schedule();}});schedule();})();
JS;
        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="'.htmlspecialchars($policy,ENT_QUOTES,'UTF-8').'"><meta name="referrer" content="no-referrer"><style>html,body{margin:0!important;padding:0!important;height:auto!important;min-height:0!important;overflow:hidden!important}body{display:flow-root;overflow-wrap:anywhere}img,table{max-width:100%}img{height:auto}html[data-mail-theme=dark],html[data-mail-theme=dark] body,html[data-mail-theme=dark] :is(table,tbody,tr,td,th,div,section){background-color:#121215!important;color:#FAFAFA!important;border-color:#27272A!important}html[data-mail-theme=dark] :is(p,span,h1,h2,h3,h4,h5,h6,strong,small,li){color:#FAFAFA!important}html[data-mail-theme=dark] a{color:#A3E635!important}html[data-mail-theme=light],html[data-mail-theme=light] body{background-color:#FFFFFF!important;color:#0F172A}</style></head><body>'.$content.'<script nonce="'.$nonce.'">'.$bridge.'</script></body></html>';
    }
    private function decode(string $v):string{if($v==='')return'';$p=@imap_mime_header_decode($v);if(!$p)return$v;$o='';foreach($p as $x){$t=(string)$x->text;$c=strtoupper((string)($x->charset??'DEFAULT'));if($c!=='DEFAULT'&&$c!=='UTF-8'&&function_exists('mb_convert_encoding')){$y=$this->convertCharset($t,$c);if($y!==false)$t=$y;}$o.=$t;}return trim($o);}
    private function address(string $v):array{if(preg_match('/^(.*?)\s*<([^>]+)>$/',$v,$m))return[trim($m[1],"\"' "),trim($m[2])];return['',trim($v)];}
    private function preview(string $v):string{$v=preg_replace('/\s+/',' ',trim($v))??'';return function_exists('mb_substr')?mb_substr($v,0,220):substr($v,0,220);}
    private function normalizeDate(string $raw):string{try{return(new DateTimeImmutable($raw))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');}catch(Throwable $e){return gmdate('Y-m-d\TH:i:s\Z');}}
}
