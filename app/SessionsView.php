<?php if(!defined('PRIVATEMAIL_SETTINGS_VIEW')){http_response_code(404);exit;}
$deviceSessions=SessionRegistry::sessions();$currentDevice=SessionRegistry::currentId();
$sessionDate=static fn(int $stamp):string=>(new DateTimeImmutable('@'.$stamp))->setTimezone(new DateTimeZone('Asia/Dhaka'))->format('d M Y, g:i A');
?>
<section class="card" id="activeSessions"><div class="card-title"><span class="card-icon"><?=layoutIcon('lock')?></span><div><h2>Active sessions <span class="session-count"><?=count($deviceSessions)?></span></h2><p>Devices signed in to your PrivateMail.</p></div></div>
<div class="session-list">
<?php foreach($deviceSessions as $device):$isCurrent=hash_equals($currentDevice,(string)$device['id']);?>
<article class="device-session"><div class="session-device-row"><strong><?=Security::e(SessionRegistry::device((string)$device['agent']))?></strong><?php if($isCurrent):?><span class="session-current">This device</span><?php endif;?></div>
<dl class="session-meta"><div><dt>Signed in</dt><dd><?=Security::e($sessionDate((int)$device['created']))?></dd></div><div><dt>Last active</dt><dd><?=$isCurrent?'Now':Security::e($sessionDate((int)$device['last_seen']))?></dd></div><div><dt>IP address</dt><dd><?=Security::e($device['ip'])?></dd></div></dl>
<?php if(!$isCurrent):?><form method="post" class="session-revoke-form"><?php settingsHidden('session_revoke');?><input type="hidden" name="session_id" value="<?=Security::e($device['id'])?>"><button class="button" type="submit">Sign out device</button></form><?php endif;?>
</article><?php endforeach;?></div>
<p class="field-hint">Times shown in Dhaka time. IP identifies the connection, not an exact location. Sessions from before this update appear when used again. Last active updates about once a minute.</p>
<?php if(count($deviceSessions)>1):?><form method="post" class="session-revoke-all"><?php settingsHidden('sessions_revoke_others');?><button class="button" type="submit">Sign out all other devices</button></form><?php endif;?>
</section>
