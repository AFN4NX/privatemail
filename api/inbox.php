<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';Auth::require();
$csrf=Security::csrfToken();session_write_close();
$category=trim((string)($_GET['category']??''))?:null;
$alias=trim((string)($_GET['alias']??''))?:null;
if(array_key_exists('inbox',$_GET)){
 $inbox=Database::findAlias((int)$_GET['inbox']);
 if(!$inbox)Security::json(['ok'=>false,'error'=>'Address not found.'],404);
 $alias=(string)$inbox['email'];
}
$q=trim((string)($_GET['q']??''));$warning=null;
try{
    MultiMailbox::synchronize(($_GET['refresh']??'')==='1');
}catch(Throwable $e){$warning='IMAP is unavailable or synchronization is incomplete. Showing cached messages.';error_log('PrivateMail: inbox sync failed.');}
try{
    $messages=$q!==''?Database::searchMessages($q,(Database::messageLimit()>0?min(50,Database::messageLimit()):50),$alias):Database::recent((Database::messageLimit()>0?min(50,Database::messageLimit()):50),$category,$alias);
    Security::json(['ok'=>true,'messages'=>$messages,'count'=>count($messages),'csrf'=>$csrf,'warning'=>$warning,'latest_id'=>Database::latestSelectedId(),'unread_count'=>Database::unreadSelected()]);
}catch(Throwable $e){Security::json(['ok'=>false,'error'=>'Unable to load the inbox.'],500);}
