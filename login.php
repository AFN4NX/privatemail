<?php
declare(strict_types=1); require_once __DIR__.'/bootstrap.php';
define('PRIVATEMAIL_AUTH_VIEW',true);
if(Auth::check()){header('Location: /');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Security::verifyCsrf($_POST['_csrf']??null))$error='Security check failed.';
    elseif(Auth::attempt((string)($_POST['password']??''))){header('Location: /');exit;}
    else $error='Incorrect password or too many attempts. If necessary, try again in 15 minutes.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>PrivateMail — Access</title><link rel="stylesheet" href="/assets/tokens.css?v=10"><link rel="stylesheet" href="/assets/app.css?v=14"><link rel="stylesheet" href="/assets/mobile.css?v=11"><link rel="stylesheet" href="/assets/features.css?v=15"><link rel="stylesheet" href="/assets/login.css?v=3"><link rel="icon" href="/assets/icons/icon-192.png" type="image/png"><meta name="application-name" content="PrivateMail"><meta name="apple-mobile-web-app-title" content="PrivateMail"><link rel="manifest" href="/manifest.json"><meta name="theme-color" content="#09090B"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png"><script src="/assets/pwa.js?v=12" defer></script><script src="/assets/login.js?v=2" defer></script></head>
<body class="auth-page login-page" data-theme="dark">
<main class="robot-auth-shell"><span class="auth-wordmark">PrivateMail<span>.</span></span><?php $authRobotMode='login';require __DIR__.'/app/AuthRobot.php';?>
<section class="auth-card login-card" aria-labelledby="loginTitle"><span class="robot-hand hand-left" aria-hidden="true"></span><span class="robot-hand hand-right" aria-hidden="true"></span><span class="robot-foot foot-left" aria-hidden="true"></span><span class="robot-foot foot-right" aria-hidden="true"></span>
<div class="login-intro"><h1 id="loginTitle">Who goes there<span>?</span></h1><p>Your private inbox is one password away.</p></div>
<?php if($error):?><div class="alert danger login-error" role="alert"><?=Security::e($error)?></div><?php endif;?>
<form method="post" class="login-form" id="loginForm"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrfToken())?>">
<label class="login-label" for="loginPassword">Access password</label><div class="login-input-wrap"><svg class="login-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg><input type="password" id="loginPassword" name="password" autocomplete="current-password" placeholder="Enter your password" aria-describedby="loginCaps" required><button type="button" id="loginReveal" class="login-reveal" aria-label="Show password" aria-pressed="false" hidden><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
<p class="login-caps" id="loginCaps" role="status" hidden>Caps Lock is on.</p>
<button class="button primary login-submit" id="loginSubmit" type="submit"><span id="loginSubmitLabel">Open PrivateMail</span><svg class="login-submit-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5"/></svg><span class="login-spinner" aria-hidden="true"></span></button>
</form>
<p class="login-footer">YOUR MAIL. YOUR SPACE.</p></section></main><p class="login-signature">PRIVATEMAIL <span>·</span> PERSONAL WORKSPACE</p></body></html>
