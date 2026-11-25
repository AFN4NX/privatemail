<?php
declare(strict_types=1);
$navPage=basename($_SERVER['SCRIPT_NAME']??'index.php');
function navIcon(string $name):string{
    $paths=[
        'inbox'=>'<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="m5.45 5.11-3.45 6V18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6.89l-3.45-6A2 2 0 0 0 16.81 4H7.19a2 2 0 0 0-1.74 1.11Z"/>',
        'new'=>'<path d="M21 11V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7"/><path d="m3 6 9 6 9-6"/><path class="mail-plus-mark" d="M19 14v8M15 18h8"/>',
        'addresses'=>'<circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/>',
        'settings'=>'<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3"/><circle cx="15" cy="17" r="3"/>',
    ];
    return '<svg class="magic-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths[$name].'</svg>';
}
$tabs=[['index.php','/','inbox','Inbox'],['new','/aliases.php?create=1','new','New Alias'],['aliases.php','/aliases.php','addresses','Addresses'],['settings.php','/settings.php','settings','Settings']];
$active=match($navPage){'aliases.php'=>(($_GET['create']??'')==='1'?1:2),'settings.php','backup.php','sessions.php'=>3,default=>0};
$center=3+(360-6)*($active+.5)/4;
// Server-rendered curve is usable before JS measures the actual viewport.
$path='M8 1 H'.($center-36).' C'.($center-25).' 1 '.($center-27).' 32 '.$center.' 32 C'.($center+27).' 32 '.($center+25).' 1 '.($center+36).' 1 H340 Q359 1 359 20 V36 Q359 55 340 55 H20 Q1 55 1 36 V8 Q1 1 8 1 Z';
?>
<nav class="bottom-nav magic-nav" id="mobileNavigation" aria-label="Mobile navigation" data-active-index="<?=$active?>" style="--active-index:<?=$active?>">
 <svg class="magic-surface" viewBox="0 0 360 56" preserveAspectRatio="none" aria-hidden="true"><defs><clipPath id="magicBarClip"><use href="#magicBarShape"/></clipPath></defs><path id="magicBarShape" class="magic-surface-path" d="<?=$path?>"/><g class="magic-surface-links" clip-path="url(#magicBarClip)"><?php foreach($tabs as $i=>[$file,$url,$icon,$label]):?><a href="<?=$url?>" tabindex="-1" focusable="false" aria-hidden="true"><rect x="<?=$i*25?>%" y="0" width="25%" height="56"/></a><?php endforeach;?></g></svg>
 <div class="magic-indicator" aria-hidden="true"><?=navIcon($tabs[$active][2])?></div>
 <div class="magic-nav-items">
 <?php foreach($tabs as $i=>[$file,$url,$icon,$label]):?>
 <a class="magic-nav-item <?=$i===$active?'is-active':''?>" href="<?=$url?>" data-nav-index="<?=$i?>" <?=$i===$active?'aria-current="page"':''?>><?=navIcon($icon)?><span class="magic-nav-label"><?=$label?></span></a>
 <?php endforeach;?>
 </div>
</nav>
<script src="/assets/navigation.js?v=13" defer></script>
