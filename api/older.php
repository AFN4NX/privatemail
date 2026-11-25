<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';Auth::require();
if($_SERVER['REQUEST_METHOD']!=='POST')Security::json(['ok'=>false,'error'=>'POST required.'],405);
if(!Security::verifyCsrf($_POST['_csrf']??null))Security::json(['ok'=>false,'error'=>'Security check failed.'],419);
session_write_close();
$alias=trim((string)($_POST['alias']??''));
if(isset($_POST['inbox'])){$a=Database::findAlias((int)$_POST['inbox']);if(!$a)Security::json(['ok'=>false,'error'=>'Address not found.'],404);$alias=(string)$a['email'];}
$q=trim((string)($_POST['q']??''));$limit=Database::messageLimit();$seen=null;
if(isset($_POST['seen_ids'])){
    $raw=(string)$_POST['seen_ids'];if(strlen($raw)>1600000)Security::json(['ok'=>false,'error'=>'Too many loaded messages. Refresh the inbox.'],422);
    $decoded=json_decode($raw,true);if(!is_array($decoded)||count($decoded)>100000)Security::json(['ok'=>false,'error'=>'Invalid history cursor.'],422);
    foreach($decoded as $id)if(!is_int($id)||$id<1)Security::json(['ok'=>false,'error'=>'Invalid history cursor.'],422);$seen=array_values(array_unique($decoded));
}
$offset=$seen!==null?count($seen):max(0,(int)($_POST['offset']??0));
if($limit>0&&$offset>=$limit)Security::json(['ok'=>true,'messages'=>[],'has_more'=>false]);
$warning=null;
try{
    $items=$seen!==null?Database::historyUnseen($seen,$alias,$q):Database::historyPage($offset,$alias,$q);$take=$limit>0?min(50,$limit-$offset):50;
    if(count($items)<$take&&!MultiMailbox::historyComplete()){
        try{MultiMailbox::backfillOlder();}catch(Throwable $e){$warning='Older mail could not finish syncing. Try again shortly.';}
        $items=$seen!==null?Database::historyUnseen($seen,$alias,$q):Database::historyPage($offset,$alias,$q);
    }
    $next=$offset+count($items);$nextSeen=$seen!==null?array_values(array_unique(array_merge($seen,array_map('intval',array_column($items,'id'))))):null;$more=($limit===0||$next<$limit)&&(count($nextSeen!==null?Database::historyUnseen($nextSeen,$alias,$q):Database::historyPage($next,$alias,$q))>0||!MultiMailbox::historyComplete());
    Security::json(['ok'=>true,'messages'=>$items,'has_more'=>$more,'warning'=>$warning,'latest_id'=>Database::latestSelectedId()]);
}catch(Throwable $e){Security::json(['ok'=>false,'error'=>'Unable to load older mail.'],503);}
