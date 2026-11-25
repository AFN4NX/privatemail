<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';Auth::require();define('PRIVATEMAIL_SETTINGS_VIEW',true);
$error='';$message=(string)($_SESSION['sessions_flash']??'');unset($_SESSION['sessions_flash']);
if($_SERVER['REQUEST_METHOD']==='POST')try{
 if(!Security::verifyCsrf($_POST['_csrf']??null))throw new RuntimeException('Security check failed. Refresh and retry.');
 $section=(string)($_POST['section']??'');
 if($section==='session_revoke'){SessionRegistry::revoke((string)($_POST['session_id']??''));$message='Device signed out. Its next request will require login.';}
 elseif($section==='sessions_revoke_others'){SessionRegistry::revokeOthers();$message='All other devices signed out.';}
 else throw new InvalidArgumentException('Unknown session action.');
 $_SESSION['sessions_flash']=$message;header('Location: /sessions.php',true,303);exit;
}catch(Throwable $e){$error=$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'Unable to update sessions. Retry.';}
function settingsHidden(string $section):void{echo '<input type="hidden" name="_csrf" value="'.Security::e(Security::csrfToken()).'"><input type="hidden" name="section" value="'.Security::e($section).'">';}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Active sessions — PrivateMail</title>


<link rel="stylesheet" href="/assets/tokens.css?v=10"><link rel="stylesheet" href="/assets/app.css?v=14"><link rel="stylesheet" href="/assets/mobile.css?v=11"><link rel="stylesheet" href="/assets/magic-nav.css?v=15"><link rel="stylesheet" href="/assets/features.css?v=15"><link rel="icon" href="/assets/icons/icon-192.png" type="image/png"><meta name="application-name" content="PrivateMail"><meta name="apple-mobile-web-app-title" content="PrivateMail"><link rel="manifest" href="/manifest.json"><meta name="theme-color" content="#84CC16"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png"><script src="/assets/features.js?v=22" defer></script><script src="/assets/pwa.js?v=12" defer></script><link rel="stylesheet" href="/assets/settings.css?v=7"><script src="/assets/settings.js?v=3" defer></script><link rel="stylesheet" href="/assets/confirmation-robot.css?v=1"><script src="/assets/confirmation-robot.js?v=2" defer></script></head>
<body <?=uiAttributes()?> class="settings-page sessions-page"><div class="app-shell"><?php renderSidebar('sessions.php');?><main class="workspace"><?php renderTopbar('sessions.php');?>
<section class="settings-content sessions-content">
<?php if($message):?><div class="notice" role="status"><?=Security::e($message)?></div><?php endif;?>
<?php if($error):?><div class="notice error" role="alert"><?=Security::e($error)?></div><?php endif;?>
<?php require __DIR__.'/app/SessionsView.php';?>
</section></main></div><script src="/assets/app.js?v=17"></script><?php require __DIR__.'/app/Navigation.php';?></body></html>
