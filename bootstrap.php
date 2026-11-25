<?php
declare(strict_types=1);
if(basename($_SERVER['SCRIPT_NAME']??'')!=='setup.php'&&(!extension_loaded('pdo_sqlite')||!extension_loaded('mbstring'))){header('Location: /setup.php');exit;}
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
define('AUTH_SESSION_SECONDS',2592000);
ini_set('session.gc_maxlifetime','2592000');
ini_set('session.gc_probability','1');
ini_set('session.gc_divisor','100');
ini_set('session.cookie_lifetime','2592000');
$sessionDirectory=__DIR__.'/storage/sessions';
if(!is_dir($sessionDirectory))@mkdir($sessionDirectory,0700,true);
if(!is_dir($sessionDirectory)||!is_writable($sessionDirectory)){http_response_code(503);exit('PrivateMail requires writable storage/sessions.');}
session_save_path($sessionDirectory);
session_name('privatemail_session');
session_set_cookie_params([
    'lifetime'=>2592000,
    'path'=>'/',
    'httponly'=>true,
    'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite'=>'Lax'
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once (is_file(__DIR__.'/config/app.php')?__DIR__.'/config/app.php':__DIR__.'/config/app.example.php');
if(!defined('APP_ACCESS_PASSWORD'))define('APP_ACCESS_PASSWORD',getenv('PRIVATEMAIL_ACCESS_PASSWORD')?:'');
if(!defined('APP_PASSWORD_HASH'))define('APP_PASSWORD_HASH',getenv('PRIVATEMAIL_PASSWORD_HASH')?:'');
if(!defined('SYNC_INTERVAL_SECONDS'))define('SYNC_INTERVAL_SECONDS',30);
require_once __DIR__.'/app/Security.php';
require_once __DIR__.'/app/Auth.php';
require_once __DIR__.'/app/Database.php';
require_once __DIR__.'/app/MailClassifier.php';
require_once __DIR__.'/app/MailService.php';
$schemaLock=@fopen(__DIR__.'/storage/schema.lock','c');
if(!$schemaLock||!flock($schemaLock,LOCK_EX)){http_response_code(503);exit('Unable to lock application schema.');}
try{Database::init();
require_once __DIR__.'/app/SettingsService.php';
require_once __DIR__.'/app/MailboxRegistry.php';
require_once __DIR__.'/app/MultiMailbox.php';
require_once __DIR__.'/app/SetupService.php';
require_once __DIR__.'/app/BackupService.php';
MailboxRegistry::init();}catch(Throwable $e){error_log('PrivateMail initialization failed: '.get_class($e));http_response_code(503);exit('Unable to initialize PrivateMail. Check server logs and database/storage permissions.');}finally{flock($schemaLock,LOCK_UN);fclose($schemaLock);}
if(SetupService::needed()&&basename($_SERVER['SCRIPT_NAME']??'')!=='setup.php'){header('Location: /setup.php');exit;}
header('X-PrivateMail-View: '.MailboxRegistry::viewToken());

function uiAttributes(): string {
    $theme=(string)Database::getSetting('theme','dark');
    $density=(string)Database::getSetting('density','comfortable');
    return 'data-theme="'.Security::e($theme).'" data-density="'.Security::e($density).'"'.($theme==='custom'?' style="'.Security::e(SettingsService::themeStyle(SettingsService::savedTheme())).'"':'');
}

require_once __DIR__.'/app/Layout.php';
