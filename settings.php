<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';Auth::require();define('PRIVATEMAIL_SETTINGS_VIEW',true);
$message=(string)($_SESSION['settings_flash']??'');unset($_SESSION['settings_flash']);$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!Security::verifyCsrf($_POST['_csrf']??null))throw new RuntimeException('Security check failed. Refresh and try again.');
  $section=(string)($_POST['section']??'');
  if($section==='session_revoke'){
   SessionRegistry::revoke((string)($_POST['session_id']??''));$message='Device signed out. Its next request will require login.';
  }elseif($section==='sessions_revoke_others'){
   SessionRegistry::revokeOthers();$message='All other devices signed out.';
  }elseif($section==='account_selection'){
   MailboxRegistry::select((array)($_POST['accounts']??[]));$message='Visible mailboxes updated. Reopen the inbox to apply your selection.';
  }elseif($section==='account_remove'){
   if(!Auth::attempt((string)($_POST['current_password']??'')))throw new RuntimeException('Current app password is incorrect or attempts are temporarily limited.');
   MailboxRegistry::remove((int)($_POST['account_id']??0));$message='Mailbox removed from PrivateMail. Server mail was not deleted.';
  }elseif($section==='domain_save'){
   MailboxRegistry::saveDomain((int)($_POST['domain_id']??0),(string)($_POST['domain']??''),(int)($_POST['domain_account']??0),isset($_POST['domain_active']));$message='Domain saved.';
  }elseif($section==='domain_remove'){
   Database::connection()->prepare('DELETE FROM managed_domains WHERE id=?')->execute([(int)($_POST['domain_id']??0)]);$message='Domain removed. Existing aliases and mail were kept.';
  }elseif($section==='general'){
   $refreshMode=(string)($_POST['refresh_mode']??'0');$limitMode=(string)($_POST['limit_mode']??'50');$density=(string)($_POST['density']??'comfortable');$bodyTheme=(string)($_POST['email_body_theme']??'light');
   $refresh=$refreshMode==='custom'?filter_var($_POST['custom_refresh']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>10,'max_range'=>86400]]):(in_array($refreshMode,['0','10','60'],true)?(int)$refreshMode:false);
   $limit=$limitMode==='custom'?filter_var($_POST['custom_limit']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]):(in_array($limitMode,['all','50','100'],true)?$limitMode:false);
   if($refresh===false||$limit===false||!in_array($density,['comfortable','compact'],true)||!in_array($bodyTheme,['light','dark'],true))throw new InvalidArgumentException('Use a refresh interval of 10–86400 seconds and a message limit of 1–100000, or All.');
   Database::setSetting('message_limit',(string)$limit);Database::setSetting('refresh_interval',(string)$refresh);Database::setSetting('density',$density);Database::setSetting('email_body_theme',$bodyTheme);$message='General settings saved.';
  }elseif($section==='appearance'){
   $theme=(string)($_POST['theme']??'dark');if(!in_array($theme,['dark','light','system','custom'],true))throw new InvalidArgumentException('Invalid theme.');
   $custom=$theme==='custom'?SettingsService::theme((array)($_POST['custom']??[])):SettingsService::savedTheme();Database::setSetting('custom_theme',json_encode($custom));Database::setSetting('theme',$theme);$message='Appearance saved.';
  }elseif($section==='password'){
   $new=(string)($_POST['new_password']??'');if($new!==(string)($_POST['confirm_password']??''))throw new InvalidArgumentException('New passwords do not match.');
   if(strlen($new)<12||strlen($new)>72)throw new InvalidArgumentException('Use a password between 12 and 72 bytes.');
   if(!Auth::attempt((string)($_POST['current_password']??'')))throw new RuntimeException('Current app password is incorrect or attempts are temporarily limited.');
   Auth::changePassword($new);$message='App password updated. Other sessions will need to sign in again.';
  }elseif($section==='imap'){
   if(!Auth::attempt((string)($_POST['current_password']??'')))throw new RuntimeException('Current app password is incorrect or attempts are temporarily limited.');
   $accountId=max(0,(int)($_POST['account_id']??0));$base=$accountId>0?MailboxRegistry::config($accountId):[];$config=SettingsService::connection($_POST,$base);$intent=(string)($_POST['intent']??'test');
   if(!in_array($intent,['test','save'],true))throw new InvalidArgumentException('Invalid mailbox action.');
   $configHash=hash('sha256',serialize([$accountId,$config]));$tested=hash_equals((string)($_SESSION['imap_test_hash']??''),$configHash)&&time()-(int)($_SESSION['last_imap_test']??0)<120;
   if(time()-(int)($_SESSION['last_imap_test']??0)<5&&!($intent==='save'&&$tested))throw new RuntimeException('Wait a few seconds before testing again.');
   unset($_SESSION['imap_test_hash']);$_SESSION['last_imap_test']=time();SettingsService::test($config);
   if($intent==='save'){$accountId=MailboxRegistry::save($accountId,(string)($_POST['account_name']??'Mailbox'),$config);$message='Connection tested and mailbox saved.';}else{$_SESSION['imap_test_hash']=$configHash;$message='Connection successful. Use Test & save to apply these settings.';}
  }else throw new InvalidArgumentException('Unknown settings section.');
 }catch(Throwable $e){$error=$e instanceof PDOException?'Unable to save. Check for a duplicate domain or database permissions.':($e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'Unable to save settings. Check server permissions and try again.');}
}
if(($_SERVER['HTTP_ACCEPT']??'')==='application/json'&&($_POST['section']??'')==='imap')Security::json(['ok'=>$error==='','message'=>$error!==''?$error:$message],$error!==''?400:200);
if($_SERVER['REQUEST_METHOD']==='POST'&&$error===''&&$message!==''){
 $_SESSION['settings_flash']=$message;$target='/settings.php';if(($_POST['section']??'')==='imap'&&isset($accountId))$target.='?account_edit='.$accountId.'#mailboxEditor';header('Location: '.$target,true,303);exit;
}
$refreshInterval=(string)Database::getSetting('refresh_interval','0');$density=(string)Database::getSetting('density','comfortable');$theme=(string)Database::getSetting('theme','dark');$custom=SettingsService::savedTheme();
$editAccount=max(0,(int)($_GET['account_edit']??($_POST['account_id']??0)));$editRow=$editAccount>0?MailboxRegistry::find($editAccount):null;
try{$imap=$editRow?MailboxRegistry::config($editAccount):[];}catch(Throwable $e){$imap=[];$error='Unable to read this account’s saved credentials. Restore the original mailbox encryption key from backup.';}
$mailbox=(string)($imap['mailbox']??'');preg_match('/^\{([^:}]+):(\d+)([^}]*)\}(.*)$/',$mailbox,$parts);
$fields=['host'=>$parts[1]??'','port'=>$parts[2]??'993','tls'=>str_contains($parts[3]??'','/ssl')?'ssl':'tls','folder'=>$parts[4]??'INBOX','username'=>$imap['username']??''];
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['section']??'')==='imap')foreach($fields as $k=>$v)$fields[$k]=(string)($_POST[$k]??$v);
$accounts=MailboxRegistry::all();$managedDomains=MailboxRegistry::domains();$editDomain=max(0,(int)($_GET['domain_edit']??0));$domainRow=null;foreach($managedDomains as $d)if((int)$d['id']===$editDomain)$domainRow=$d;
function settingsHidden(string $section):void{echo '<input type="hidden" name="_csrf" value="'.Security::e(Security::csrfToken()).'"><input type="hidden" name="section" value="'.$section.'">';}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Settings — PrivateMail</title>


<link rel="stylesheet" href="/assets/tokens.css?v=10"><link rel="stylesheet" href="/assets/app.css?v=14"><link rel="stylesheet" href="/assets/mobile.css?v=11"><link rel="stylesheet" href="/assets/magic-nav.css?v=15"><link rel="stylesheet" href="/assets/features.css?v=15"><link rel="icon" href="/assets/icons/icon-192.png" type="image/png"><meta name="application-name" content="PrivateMail"><meta name="apple-mobile-web-app-title" content="PrivateMail"><link rel="manifest" href="/manifest.json"><meta name="theme-color" content="#84CC16"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png"><script src="/assets/features.js?v=22" defer></script><script src="/assets/pwa.js?v=12" defer></script><link rel="stylesheet" href="/assets/settings.css?v=7"><script src="/assets/settings.js?v=3" defer></script><link rel="stylesheet" href="/assets/confirmation-robot.css?v=1"><script src="/assets/confirmation-robot.js?v=2" defer></script></head>
<body <?=uiAttributes()?> class="settings-page">
<div class="app-shell"><?php renderSidebar('settings.php');?><main class="workspace"><?php renderTopbar('settings.php');?>
<section id="pm-settings" class="settings-content"><div class="settings-page-title"><div class="page-head"><h1>Settings</h1><p>Your workspace, your way.</p></div><a class="button" href="/backup.php">Backup &amp; Migration</a></div>
<?php if($message):?><div class="notice success" role="status"><?=Security::e($message)?></div><?php endif;?>
<?php if($error):?><div class="notice error" role="alert"><?=Security::e($error)?></div><?php endif;?>
<div class="section-grid settings-redesign">
<div class="settings-stack"><section class="card"><div class="card-title"><span class="card-icon"><?=layoutIcon('settings')?></span><div><h2>General</h2><p>A comfortable rhythm for your inbox.</p></div></div>
<form method="post"><?php settingsHidden('general');?>
<fieldset class="choice-field custom-choice-field" data-custom-choice="refresh_mode"><legend>Auto refresh</legend><div class="interval-choices">
<?php foreach(['0'=>'Off','10'=>'10s','60'=>'1m','custom'=>'Custom'] as $value=>$label):$picked=$value==='custom'?!in_array($refreshInterval,['0','10','60'],true):$refreshInterval===(string)$value;?><label class="choice-pill"><input type="radio" name="refresh_mode" value="<?=$value?>" <?=$picked?'checked':''?>><span><?=$label?></span></label><?php endforeach;?></div>
<label class="custom-number-field">Refresh every (seconds)<input type="number" name="custom_refresh" min="10" max="86400" step="1" value="<?=max(10,(int)$refreshInterval)?>"><small>10 seconds to 24 hours</small></label></fieldset>
<fieldset class="choice-field custom-choice-field" data-custom-choice="limit_mode"><legend>Message limit</legend><div class="interval-choices">
<?php $savedLimit=(string)Database::getSetting('message_limit','50');foreach(['all'=>'All','50'=>'50','100'=>'100','custom'=>'Custom'] as $value=>$label):$picked=$value==='custom'?!in_array($savedLimit,['all','50','100'],true):$savedLimit===(string)$value;?><label class="choice-pill"><input type="radio" name="limit_mode" value="<?=$value?>" <?=$picked?'checked':''?>><span><?=$label?></span></label><?php endforeach;?></div>
<label class="custom-number-field">Maximum messages<input type="number" name="custom_limit" min="1" max="100000" step="1" value="<?=$savedLimit==='all'?200:max(1,(int)$savedLimit)?>"><small>Older mail loads in batches.</small></label></fieldset>
<fieldset class="choice-field"><legend>Email body theme</legend><p class="field-hint">Use the same reading theme for every message.</p><div class="interval-choices"><?php foreach(['light'=>'Light','dark'=>'Dark'] as $value=>$label):?><label class="choice-pill"><input type="radio" name="email_body_theme" value="<?=$value?>" <?=Database::getSetting('email_body_theme','light')===$value?'checked':''?>><span><?=$label?></span></label><?php endforeach;?></div></fieldset>
<fieldset class="choice-field"><legend>Message density</legend><div class="density-choices">
<?php foreach(['comfortable'=>'Comfortable','compact'=>'Compact'] as $value=>$label):?><label class="density-choice"><input type="radio" name="density" value="<?=$value?>" <?=$density===$value?'checked':''?>><span class="density-content"><strong><?=$label?></strong><span class="density-demo <?=$value?>" aria-hidden="true"><i></i><i></i><i></i></span></span></label><?php endforeach;?></div></fieldset>
<div class="actions"><button class="primary">Save changes</button></div></form></section><section class="card"><div class="card-title"><span class="card-icon"><?=layoutIcon('lock')?></span><div><h2>Security</h2><p>Update your PrivateMail access password.</p></div></div>
<form method="post" class="settings-form"><?php settingsHidden('password');?>
<label class="settings-label">Current app password<input type="password" name="current_password" autocomplete="current-password" required></label>
<label class="settings-label">New password<input type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72" required></label>
<label class="settings-label">Confirm new password<input type="password" name="confirm_password" autocomplete="new-password" minlength="12" maxlength="72" required></label>
<p class="field-hint">Use 12–72 bytes. Other signed-in sessions will be signed out.</p><div class="actions"><button class="primary">Update password</button></div></form></section><?php require __DIR__.'/app/AccountSelectionView.php';?></div>
<div class="settings-stack"><section class="card"><div class="card-title"><span class="card-icon"><?=layoutIcon('moon')?></span><div><h2>Appearance</h2><p>Choose a look or make it yours.</p></div></div>
<form method="post" id="appearanceForm"><?php settingsHidden('appearance');?><div class="theme-choices">
<?php foreach(['dark'=>'Dark','light'=>'Light','system'=>'System','custom'=>'Custom'] as $value=>$label):?><label class="theme-choice"><input type="radio" name="theme" value="<?=$value?>" <?=$theme===$value?'checked':''?>><span><i class="theme-swatch <?=$value?>"></i><strong><?=$label?></strong></span></label><?php endforeach;?></div>
<div id="customThemeEditor" class="custom-theme-editor"><label class="settings-label">Theme name<input name="custom[name]" id="themeName" maxlength="40" value="<?=Security::e($custom['name'])?>" required></label>
<div class="preset-choices" aria-label="Theme presets"><?php foreach(['lime'=>'Lime','violet'=>'Violet','ocean'=>'Ocean Blue','rose'=>'Rose'] as $value=>$label):?><button type="button" data-theme-preset="<?=$value?>"><?=$label?></button><?php endforeach;?></div>
<div class="color-grid"><?php foreach(['accent'=>'Accent','background'=>'Background','surface'=>'Cards','text'=>'Text'] as $key=>$label):?><label class="color-choice"><input type="color" name="custom[<?=$key?>]" data-theme-color="<?=$key?>" value="<?=Security::e($custom[$key])?>"><span><?=$label?></span></label><?php endforeach;?></div>
<div id="themePreview" class="theme-live-preview"><span class="preview-title">Your private inbox</span><span class="preview-muted">A preview of your workspace</span><div class="preview-message"><strong>Welcome to PrivateMail</strong><span>Everything in one place.</span><span class="preview-accent">New message</span></div></div>
<p id="contrastWarning" class="contrast-warning" role="status" hidden>Low text contrast. Choose a lighter text or darker background for comfortable reading.</p>
<button type="button" class="text-button" id="resetCustomTheme">Reset to Default</button></div>
<div class="actions"><button class="primary">Save appearance</button></div></form></section><?php require __DIR__.'/app/MailboxEditorView.php';require __DIR__.'/app/DomainSettingsView.php';?></div>
</div>
</section></main></div><script src="/assets/app.js?v=17"></script><?php require __DIR__.'/app/Navigation.php';?></body></html>
