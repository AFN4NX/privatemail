<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';
Auth::require();
if($_SERVER['REQUEST_METHOD']!=='POST')Security::json(['ok'=>false,'error'=>'Method not allowed.'],405);
if(!Security::verifyCsrf($_POST['_csrf']??null))Security::json(['ok'=>false,'error'=>'Security check failed. Refresh and retry.'],419);
$ids=MailboxRegistry::selectedIds();
session_write_close();
$failed=0;
foreach($ids as $id){
    $lock=null;
    try{
        $lock=MailboxRegistry::acquire((int)$id);
        $service=new MailService(false,(int)$id);
        $service->markAllRead();
        unset($service);
    }catch(Throwable $e){$failed++;}
    finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}
}
Security::json(['ok'=>$failed===0,'unread_count'=>Database::unreadSelected(),'error'=>$failed?'Some mailboxes could not be updated. Retry to finish.':null],$failed?502:200);
