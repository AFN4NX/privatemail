<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';Auth::require();session_write_close();
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$message=$id?Database::findMessage($id):null;
header('Content-Type: text/plain; charset=utf-8');header('Content-Security-Policy: default-src \'none\'; sandbox');
if(!$message){http_response_code(404);exit('Message not found.');}
try{$accountLock=MailboxRegistry::acquire((int)$message['account_id'],true);echo (new MailService(true,(int)$message['account_id']))->rawSource((int)$message['imap_uid']);}
catch(Throwable $e){http_response_code(502);echo 'Unable to load source. Source previews are limited to 10 MiB.';}

