<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';
Auth::require();

$domains=MailboxRegistry::activeDomains();

function randomLocalPart(int $length=8): string {
    $words=['frost','nova','orbit','pixel','ember','lunar','swift','cobalt','mint','echo','raven','cloud','maple','drift','vivid','spark','atlas','velvet','stone','wave'];
    return $words[random_int(0,count($words)-1)].random_int(10,99);
}
function validLocal(string $local): bool { return (bool)preg_match('/^[a-z0-9](?:[a-z0-9._-]{0,62}[a-z0-9])?$/i',$local); }
function validDomain(string $domain,array $domains): bool { return in_array(strtolower($domain),$domains,true); }

if($_SERVER['REQUEST_METHOD']==='GET'){
    $sort=(string)($_GET['sort']??'new'); $search=trim((string)($_GET['search']??''));
    $items=Database::aliases(in_array($sort,['new','old','az','za','domain'],true)?$sort:'new');
    if($search!=='') $items=array_values(array_filter($items,fn($a)=>stripos($a['email'],$search)!==false||stripos($a['domain'],$search)!==false));
    Security::json(['ok'=>true,'domains'=>$domains,'aliases'=>$items,'count'=>count($items),'total'=>Database::aliasCount()]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')Security::json(['ok'=>false,'error'=>'Method not allowed.'],405);
if(!Security::verifyCsrf($_POST['_csrf']??null)) Security::json(['ok'=>false,'error'=>'Security check failed. Refresh the page and try again.'],419);
$action=(string)($_POST['action']??'');
try{
    if($action==='create'){
        $domain=strtolower(trim((string)($_POST['domain']??'')));
        $local=strtolower(trim((string)($_POST['local_part']??'')));
        if(!validDomain($domain,$domains)) throw new InvalidArgumentException('Please select a valid configured domain.');
        if(!validLocal($local)||str_contains($local,'..')) throw new InvalidArgumentException('Use 1–64 letters, numbers, dots, hyphens or underscores.');
        $email=$local.'@'.$domain;
        if(Database::aliasExists($email)) throw new InvalidArgumentException('That alias already exists. Try another one.');
        $alias=Database::createAlias($email,$local,$domain);
        Security::json(['ok'=>true,'alias'=>$alias]);
    }
    if($action==='delete'){
        $id=(int)($_POST['id']??0); if($id<1||!Database::deleteAlias($id)) throw new InvalidArgumentException('Alias not found.');
        Security::json(['ok'=>true]);
    }
    Security::json(['ok'=>false,'error'=>'Unknown action.'],400);
}catch(Throwable $e){ Security::json(['ok'=>false,'error'=>$e instanceof PDOException&&$e->getCode()==='23000'?'That alias already exists.':($e instanceof InvalidArgumentException?$e->getMessage():'Unable to update aliases.')],400); }
