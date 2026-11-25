<?php
declare(strict_types=1);
final class Database {
    private static ?PDO $pdo=null;
    public static function connection(): PDO {
        if (self::$pdo) return self::$pdo;
        if (!is_dir(dirname(DB_PATH))) mkdir(dirname(DB_PATH),0750,true);
        self::$pdo=new PDO('sqlite:'.DB_PATH,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        self::$pdo->exec('PRAGMA busy_timeout=5000');
        self::$pdo->exec('PRAGMA journal_mode=WAL');
        self::$pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT, imap_uid INTEGER NOT NULL UNIQUE, message_id TEXT,
            alias_email TEXT, original_to TEXT, sender_name TEXT, sender_email TEXT, subject TEXT NOT NULL DEFAULT '(No subject)',
            preview TEXT NOT NULL DEFAULT '', category TEXT NOT NULL DEFAULT 'other',
            subcategory TEXT NOT NULL DEFAULT 'other', code TEXT, received_at TEXT,
            is_read INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_date ON messages(received_at DESC)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_category ON messages(category,subcategory)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_alias ON messages(alias_email)');
        $messageColumns=self::$pdo->query("PRAGMA table_info(messages)")->fetchAll();
        $names=array_map(fn($c)=>(string)$c['name'],$messageColumns);
        if(!in_array('original_to',$names,true)) self::$pdo->exec('ALTER TABLE messages ADD COLUMN original_to TEXT');

        self::$pdo->exec("CREATE TABLE IF NOT EXISTS aliases (
            id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE,
            local_part TEXT, domain TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $aliasColumns=self::$pdo->query("PRAGMA table_info(aliases)")->fetchAll();
        $aliasNames=array_map(fn($c)=>(string)$c['name'],$aliasColumns);
        if(!in_array('local_part',$aliasNames,true)) self::$pdo->exec('ALTER TABLE aliases ADD COLUMN local_part TEXT');
        if(!in_array('domain',$aliasNames,true)) self::$pdo->exec('ALTER TABLE aliases ADD COLUMN domain TEXT');
        if(self::$pdo->query("SELECT 1 FROM aliases WHERE local_part IS NULL OR domain IS NULL LIMIT 1")->fetchColumn())self::$pdo->exec("UPDATE aliases SET local_part=CASE WHEN instr(email,'@')>0 THEN substr(email,1,instr(email,'@')-1) ELSE email END, domain=CASE WHEN instr(email,'@')>0 THEN substr(email,instr(email,'@')+1) ELSE '' END WHERE local_part IS NULL OR domain IS NULL");
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_aliases_created ON aliases(created_at DESC,id DESC)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_aliases_domain ON aliases(domain)');
        return self::$pdo;
    }
    public static function init(): void {
        self::connection()->exec("CREATE TABLE IF NOT EXISTS app_settings (key TEXT PRIMARY KEY,value TEXT NOT NULL,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $db=self::connection();$columns=array_column($db->query('PRAGMA table_info(messages)')->fetchAll(),'name');
        if(!in_array('account_id',$columns,true)){
            $db->beginTransaction();try{
                $columns=array_column($db->query('PRAGMA table_info(messages)')->fetchAll(),'name');
                if(!in_array('account_id',$columns,true)){
                    $sequence=(int)$db->query("SELECT COALESCE(seq,0) FROM sqlite_sequence WHERE name='messages'")->fetchColumn();
                    $db->exec("CREATE TABLE messages_multi(id INTEGER PRIMARY KEY AUTOINCREMENT,account_id INTEGER NOT NULL DEFAULT 1,imap_uid INTEGER NOT NULL,message_id TEXT,alias_email TEXT,original_to TEXT,sender_name TEXT,sender_email TEXT,subject TEXT NOT NULL DEFAULT '(No subject)',preview TEXT NOT NULL DEFAULT '',category TEXT NOT NULL DEFAULT 'other',subcategory TEXT NOT NULL DEFAULT 'other',code TEXT,received_at TEXT,is_read INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(account_id,imap_uid))");
                    $names=implode(',',$columns);$db->exec('INSERT INTO messages_multi('.$names.') SELECT '.$names.' FROM messages');$db->exec('DROP TABLE messages');$db->exec('ALTER TABLE messages_multi RENAME TO messages');
                    $sequenceUpdate=$db->prepare("UPDATE sqlite_sequence SET seq=MAX(seq,?) WHERE name='messages'");$sequenceUpdate->bindValue(1,$sequence,PDO::PARAM_INT);$sequenceUpdate->execute();
                }
                $db->commit();
            }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        }
        $db->exec('CREATE INDEX IF NOT EXISTS idx_messages_date ON messages(received_at DESC,id DESC)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_messages_category ON messages(category,subcategory)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_messages_alias ON messages(alias_email)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_messages_account_date ON messages(account_id,received_at DESC,id DESC)');
        if(!self::getSetting('utc_dates_v1')){
            self::connection()->exec("UPDATE messages SET received_at=strftime('%Y-%m-%dT%H:%M:%SZ',received_at) WHERE received_at IS NOT NULL");
            self::setSetting('utc_dates_v1','1');
        }
        self::connection()->exec('CREATE INDEX IF NOT EXISTS idx_messages_recipient_date ON messages(original_to COLLATE NOCASE,received_at DESC,id DESC)');
    }
    public static function getSetting(string $key,mixed $default=null): mixed { $s=self::connection()->prepare('SELECT value FROM app_settings WHERE key=:key LIMIT 1');$s->execute([':key'=>$key]);$v=$s->fetchColumn();return $v===false?$default:$v; }
    public static function setSetting(string $key,string $value): void { self::connection()->prepare('INSERT INTO app_settings(key,value) VALUES(:key,:value) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_at=CURRENT_TIMESTAMP')->execute([':key'=>$key,':value'=>$value]); }
    public static function upsert(array $m): int {
        $db=self::connection();
        $sql="INSERT INTO messages(account_id,imap_uid,message_id,alias_email,original_to,sender_name,sender_email,subject,preview,category,subcategory,code,received_at,is_read)
        VALUES(:account,:uid,:mid,:alias,:original_to,:name,:email,:subject,:preview,:cat,:sub,:code,:date,:read)
        ON CONFLICT(account_id,imap_uid) DO UPDATE SET message_id=excluded.message_id,alias_email=excluded.alias_email,original_to=excluded.original_to,
        sender_name=excluded.sender_name,sender_email=excluded.sender_email,subject=excluded.subject,preview=excluded.preview,
        category=excluded.category,subcategory=excluded.subcategory,code=excluded.code,received_at=excluded.received_at,is_read=excluded.is_read,updated_at=CURRENT_TIMESTAMP";
        $db->prepare($sql)->execute([':account'=>$m['account_id']??1,':uid'=>$m['imap_uid'],':mid'=>$m['message_id']??null,':alias'=>$m['alias_email']??null,':original_to'=>$m['original_to']??null,':name'=>$m['sender_name']??'',':email'=>$m['sender_email']??'',':subject'=>$m['subject']??'(No subject)',':preview'=>$m['preview']??'',':cat'=>$m['category']??'other',':sub'=>$m['subcategory']??'other',':code'=>$m['code']??null,':date'=>$m['received_at']??null,':read'=>$m['is_read']??0]);
        $s=$db->prepare('SELECT id FROM messages WHERE account_id=:account AND imap_uid=:uid LIMIT 1');$s->execute([':account'=>$m['account_id']??1,':uid'=>$m['imap_uid']]);return (int)$s->fetchColumn();
    }
    public static function accountScope():string {return class_exists('MailboxRegistry')?MailboxRegistry::scope():'1=1';}
    public static function latestSelectedId():int {return (int)self::connection()->query('SELECT COALESCE(MAX(id),0) FROM messages WHERE '.self::accountScope())->fetchColumn();}
    public static function unreadSelected():int {return (int)self::connection()->query('SELECT COUNT(*) FROM messages WHERE is_read=0 AND '.self::accountScope())->fetchColumn();}
    public static function messageLimit(): int {$value=(string)self::getSetting('message_limit','50');return $value==='all'?0:(ctype_digit($value)&&(int)$value>=1&&(int)$value<=100000?(int)$value:50);}
    public static function historyPage(int $offset,?string $alias=null,string $query=''): array {
        $offset=max(0,$offset);$limit=self::messageLimit();$take=$limit>0?max(0,min(50,$limit-$offset)):50;if($take===0)return [];
        $where=[self::accountScope()];$params=[];if($alias!==null&&$alias!==''){$where[]='original_to = :alias COLLATE NOCASE';$params[':alias']=$alias;}
        if(trim($query)!==''){$where[]='(sender_name LIKE :q OR sender_email LIKE :q OR subject LIKE :q OR preview LIKE :q OR original_to LIKE :q)';$params[':q']='%'.trim($query).'%';}
        $sql='SELECT * FROM messages'.($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY received_at DESC,id DESC LIMIT $take OFFSET $offset";$stmt=self::connection()->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
    }
    public static function historyUnseen(array $seen,?string $alias=null,string $query=''):array {
        $limit=self::messageLimit();$take=$limit>0?max(0,min(50,$limit-count($seen))):50;if($take===0)return [];
        $db=self::connection();$db->exec('CREATE TEMP TABLE IF NOT EXISTS delivered_ids(id INTEGER PRIMARY KEY)');$db->exec('DELETE FROM delivered_ids');$insert=$db->prepare('INSERT OR IGNORE INTO delivered_ids(id) VALUES(?)');foreach($seen as $id)$insert->execute([$id]);
        $where=[self::accountScope(),'NOT EXISTS (SELECT 1 FROM delivered_ids d WHERE d.id=messages.id)'];$params=[];
        if($alias!==null&&$alias!==''){$where[]='original_to = :alias COLLATE NOCASE';$params[':alias']=$alias;}
        if(trim($query)!==''){$where[]='(sender_name LIKE :q OR sender_email LIKE :q OR subject LIKE :q OR preview LIKE :q OR original_to LIKE :q)';$params[':q']='%'.trim($query).'%';}
        $stmt=$db->prepare('SELECT * FROM messages WHERE '.implode(' AND ',$where)." ORDER BY received_at DESC,id DESC LIMIT $take");$stmt->execute($params);return $stmt->fetchAll();
    }
    public static function recent(int $limit=50,?string $category=null,?string $alias=null): array {
        $limit=max(1,min(200,$limit));
        if($alias!==null&&$alias!==''){ $s=self::connection()->prepare("SELECT * FROM messages WHERE ".self::accountScope()." AND original_to = :a COLLATE NOCASE ORDER BY received_at DESC,id DESC LIMIT $limit");$s->execute([':a'=>$alias]);return $s->fetchAll(); }
        if($category){$s=self::connection()->prepare("SELECT * FROM messages WHERE ".self::accountScope()." AND category=:c ORDER BY received_at DESC,id DESC LIMIT $limit");$s->execute([':c'=>$category]);return $s->fetchAll();}
        return self::connection()->query("SELECT * FROM messages WHERE ".self::accountScope()." ORDER BY received_at DESC,id DESC LIMIT $limit")->fetchAll();
    }
    public static function searchMessages(string $query,int $limit=50,?string $alias=null): array {
        $limit=max(1,min(200,$limit));$sql="SELECT * FROM messages WHERE ".self::accountScope()." AND (sender_name LIKE :q OR sender_email LIKE :q OR subject LIKE :q OR preview LIKE :q OR original_to LIKE :q)";
        if($alias!==null&&$alias!=='')$sql.=" AND original_to = :alias COLLATE NOCASE";
        $sql.=" ORDER BY received_at DESC,id DESC LIMIT $limit";$s=self::connection()->prepare($sql);$p=[':q'=>'%'.trim($query).'%'];if($alias!==null&&$alias!=='')$p[':alias']=$alias;$s->execute($p);return $s->fetchAll();
    }
    public static function markRead(int $id): bool { $s=self::connection()->prepare('UPDATE messages SET is_read=1,updated_at=CURRENT_TIMESTAMP WHERE id=:id');$s->execute([':id'=>$id]);return $s->rowCount()>0; }
    public static function aliases(string $sort='new'): array { $sql=match($sort){ 'old'=>'SELECT * FROM aliases ORDER BY datetime(created_at) ASC,id ASC','az'=>'SELECT * FROM aliases ORDER BY lower(email) ASC,id ASC','za'=>'SELECT * FROM aliases ORDER BY lower(email) DESC,id DESC','domain'=>'SELECT * FROM aliases ORDER BY lower(domain) ASC,lower(local_part) ASC,id ASC',default=>'SELECT * FROM aliases ORDER BY datetime(created_at) DESC,id DESC'};return self::connection()->query($sql)->fetchAll(); }
    public static function findMessage(int $id): ?array { if($id<1)return null;$s=self::connection()->prepare('SELECT * FROM messages WHERE id=:id LIMIT 1');$s->execute([':id'=>$id]);$r=$s->fetch();return $r&&(!class_exists('MailboxRegistry')||in_array((int)$r['account_id'],MailboxRegistry::selectedIds(),true))?$r:null; }
    public static function findAlias(int $id): ?array {$s=self::connection()->prepare('SELECT * FROM aliases WHERE id=?');$s->execute([$id]);return $s->fetch()?:null;}
    public static function aliasId(string $email): int {$s=self::connection()->prepare('SELECT id FROM aliases WHERE email = ? COLLATE NOCASE');$s->execute([$email]);return (int)$s->fetchColumn();}
    public static function aliasCount(): int{return (int)self::connection()->query('SELECT COUNT(*) FROM aliases')->fetchColumn();}
    public static function createAlias(string $email,string $local,string $domain): array{$s=self::connection()->prepare('INSERT INTO aliases(email,local_part,domain) VALUES(:email,:local,:domain)');$s->execute([':email'=>$email,':local'=>$local,':domain'=>$domain]);return self::connection()->query('SELECT * FROM aliases WHERE id='.(int)self::connection()->lastInsertId())->fetch();}
    public static function deleteAlias(int $id): bool{$s=self::connection()->prepare('DELETE FROM aliases WHERE id=:id');$s->execute([':id'=>$id]);return $s->rowCount()>0;}
    public static function aliasExists(string $email): bool{$s=self::connection()->prepare('SELECT 1 FROM aliases WHERE lower(email)=lower(:email) LIMIT 1');$s->execute([':email'=>$email]);return (bool)$s->fetchColumn();}
}
