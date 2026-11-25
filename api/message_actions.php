<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';Auth::require();
if($_SERVER['REQUEST_METHOD']!=='POST')Security::json(['ok'=>false,'error'=>'Method not allowed.'],405);
if(!Security::verifyCsrf($_POST['_csrf']??null))Security::json(['ok'=>false,'error'=>'Security check failed. Refresh and retry.'],419);
if(!in_array(($_POST['action']??''),['trash','read','unread'],true))Security::json(['ok'=>false,'error'=>'Unknown action.'],400);
$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$message=$id?Database::findMessage($id):null;
if(!$message)Security::json(['ok'=>false,'error'=>'Message not found.'],404);
session_write_close();
try{$accountLock=MailboxRegistry::acquire((int)$message['account_id']);
    if(($_POST['action']??'')!=='trash'){
        $read=$_POST['action']==='read';$service=new MailService(true,(int)$message['account_id']);$service->setRead((int)$message['imap_uid'],$read);
        Database::connection()->prepare('UPDATE messages SET is_read=? WHERE id=?')->execute([$read?1:0,$id]);
        Security::json(['ok'=>true,'is_read'=>$read?1:0,'unread_count'=>Database::unreadSelected()]);
    }
    (new MailService(true,(int)$message['account_id']))->moveToTrash((int)$message['imap_uid']);
    Database::connection()->prepare('DELETE FROM messages WHERE id=?')->execute([$id]);
    Database::setSetting('account_'.$message['account_id'].'_last_sync','0');
    Security::json(['ok'=>true]);
}catch(Throwable $e){
    $text=str_contains($e->getMessage(),'Trash folder not found')?'Trash folder not found. Configure trash_mailbox in config/imap.php.':(str_contains($e->getMessage(),'Mailbox changed')?'Mailbox changed. Refresh the inbox and retry.':(($_POST['action']??'')==='trash'?'Unable to move the message to Trash. Please retry.':'Unable to update read status. Please retry.'));
    Security::json(['ok'=>false,'error'=>$text],502);
}

