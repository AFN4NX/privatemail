<?php
declare(strict_types=1);
final class MultiMailbox {
    public static function synchronize(bool $force=false):void {
        $interval=(int)Database::getSetting('refresh_interval','0');$cooldown=$interval>0?max(10,$interval):SYNC_INTERVAL_SECONDS;
        $failed=0;foreach(MailboxRegistry::all(true) as $account){if(!$force&&time()-(int)Database::getSetting('account_'.$account['id'].'_last_sync','0')<$cooldown)continue;try{(new MailService(true,(int)$account['id']))->synchronize($force);}catch(Throwable $e){$failed++;error_log('PrivateMail: mailbox sync failed for account '.(int)$account['id']);}}
        if($failed)throw new RuntimeException('One or more mailboxes are unavailable. Showing cached messages from your selected accounts.');
    }
    public static function status():array {
        $accounts=MailboxRegistry::all(true);$failed=0;$unread=0;
        foreach($accounts as $account){try{$s=(new MailService(true,(int)$account['id']))->pollStatus();$unread+=(int)$s['unread_count'];}catch(Throwable $e){$failed++;$q=Database::connection()->prepare('SELECT COUNT(*) FROM messages WHERE account_id=? AND is_read=0');$q->execute([(int)$account['id']]);$unread+=(int)$q->fetchColumn();}}
        if($accounts&&$failed===count($accounts))throw new RuntimeException('Selected mailboxes are unavailable.');
        return ['latest_id'=>Database::latestSelectedId(),'unread_count'=>$unread];
    }
    public static function historyComplete():bool {foreach(MailboxRegistry::all(true) as $a)if(Database::getSetting('account_'.$a['id'].'_history_complete','0')!=='1')return false;return true;}
    public static function backfillOlder():void {
        $accounts=array_values(array_filter(MailboxRegistry::all(true),fn($a)=>Database::getSetting('account_'.$a['id'].'_history_complete','0')!=='1'));if(!$accounts)return;
        $last=(int)Database::getSetting('history_account_turn','0');$chosen=$accounts[0];foreach($accounts as $a)if((int)$a['id']>$last){$chosen=$a;break;}
        Database::setSetting('history_account_turn',(string)$chosen['id']);(new MailService(true,(int)$chosen['id']))->backfillOlder();
    }
}
