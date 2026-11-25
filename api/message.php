<?php
declare(strict_types=1); require_once __DIR__.'/../bootstrap.php'; Auth::require();session_write_close();
try{
    $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id)throw new InvalidArgumentException('Invalid message ID.');
    $message=Database::findMessage((int)$id);if(!$message)throw new RuntimeException('Message not found.');
    $accountLock=MailboxRegistry::acquire((int)$message['account_id'],true);
    $service=new MailService(true,(int)$message['account_id']);$full=$service->fetchFullMessage((int)$message['imap_uid']);
    if($service->markRead((int)$message['imap_uid']))Database::markRead((int)$message['id']);
    Security::json(['ok'=>true,'unread_count'=>Database::unreadSelected(),'message'=>['id'=>(int)$message['id'],'subject'=>$full['subject'],'sender_name'=>$full['sender_name'],'sender_email'=>$full['sender_email'],'to'=>$full['to'],'cc'=>$full['cc'],'original_to'=>$full['original_to'],'alias_email'=>$full['alias_email'],'date'=>$full['date'],'html'=>$full['html'],'text'=>$full['text'],'frame_token'=>$full['frame_token'],'code'=>$full['code']]]);
}catch(Throwable $e){Security::json(['ok'=>false,'error'=>'Unable to load this message. It may have been removed from the mailbox.'],500);}

