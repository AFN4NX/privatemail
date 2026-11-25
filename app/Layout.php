<?php
declare(strict_types=1);
function layoutIcon(string $name):string{
 $paths=[
 'mailplus'=>'<path d="M21 11V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7"/><path d="m3 6 9 6 9-6"/><path class="mail-plus-mark" d="M19 14v8M15 18h8"/>',
 'at'=>'<circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/>',
 'lock'=>'<rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
 'inbox'=>'<path d="M4 4h16v12h-5l-2 3h-2l-2-3H4z"/><path d="M4 12h5l2 3h2l2-3h5"/>',
 'copy'=>'<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',
 'trash'=>'<path d="M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6"/>',
 'source'=>'<path d="m8 7-5 5 5 5m8-10 5 5-5 5M14 4l-4 16"/>',
 'check'=>'<path d="m5 12 4 4L19 6"/>',
 'sun'=>'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.4 1.4M17.6 17.6 19 19M5 19l1.4-1.4M17.6 6.4 19 5"/>',
 'moon'=>'<path d="M20.9 13a9 9 0 0 1-9.9-9.9A9 9 0 1 0 20.9 13Z"/>',
 'dice'=>'<rect x="3" y="3" width="18" height="18" rx="4"/><circle cx="8" cy="8" r=".7"/><circle cx="16" cy="8" r=".7"/><circle cx="12" cy="12" r=".7"/><circle cx="8" cy="16" r=".7"/><circle cx="16" cy="16" r=".7"/>',
 'mail'=>'<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/>',
 'address'=>'<rect x="4" y="3" width="16" height="18" rx="3"/><circle cx="12" cy="9" r="2"/><path d="M8 16c0-4 8-4 8 0"/>',
 'settings'=>'<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3"/><circle cx="15" cy="17" r="3"/>',
 'shuffle'=>'<path d="m18 3 3 3-3 3M18 15l3 3-3 3M3 6h3c5 0 7 12 12 12h3M3 18h3c2 0 4-3 6-6s4-6 6-6h3"/>',
 'plus'=>'<path d="M12 5v14M5 12h14"/>','search'=>'<circle cx="10" cy="10" r="6"/><path d="m15 15 5 5"/>',
 'refresh'=>'<path d="M20 7v5h-5M4 17v-5h5"/><path d="M6 7a7 7 0 0 1 12-1l2 6M4 12l2 6a7 7 0 0 0 12-1"/>',
 'arrow'=>'<path d="M5 12h14m-5-5 5 5-5 5"/>','close'=>'<path d="m6 6 12 12M6 18 18 6"/>'
 ];
 return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths[$name].'</svg>';
}
function renderSidebar(string $page,string $alias=''):void{
 $items=Database::aliases('new');
 ?>
 <aside class="sidebar premium-sidebar address-only-panel" id="mailSidebar" aria-label="Email addresses">
 <div class="address-panel-heading"><span class="address-panel-symbol"><?=layoutIcon('address')?></span><strong>Your addresses</strong><a href="/aliases.php?create=1" class="panel-add-address" aria-label="Create an address"><?=layoutIcon('mailplus')?></a><button class="drawer-close" id="closeAddressDrawer" type="button" aria-label="Close addresses"><?=layoutIcon('close')?></button></div>
 <div class="sidebar-search"><?=layoutIcon('search')?><input id="emailSearch" aria-label="Filter addresses" placeholder="Find an address…" autocomplete="off"></div>
 <div class="sidebar-aliases" id="sidebarAliases">
 <a class="sidebar-email <?=$alias===''?'selected':''?>" href="/"><span class="alias-row-icon"><?=layoutIcon('inbox')?></span><span>All addresses</span></a>
 <?php foreach($items as $a):$email=(string)$a['email'];$selected=strcasecmp($alias,$email)===0;?><div class="sidebar-email-row <?=$selected?'selected':''?>" data-email="<?=Security::e(strtolower($email))?>"><a class="sidebar-email" href="/?inbox=<?=(int)$a['id']?>" title="<?=Security::e($email)?>"><span class="alias-row-icon"><?=layoutIcon('mail')?></span><span><?=Security::e($email)?></span></a><button class="sidebar-copy" type="button" data-copy-email="<?=Security::e($email)?>" aria-label="Copy <?=Security::e($email)?>" title="Copy email address"><?=layoutIcon('copy')?></button></div><?php endforeach;?>
 <?php if(!$items):?><p class="sidebar-empty">No addresses yet.</p><?php endif;?>
 </div>
 </aside><button class="drawer-scrim" id="addressScrim" type="button" aria-label="Close address menu" hidden></button>
 <?php
}
function renderTopbar(string $page):void{
 $label=match($page){'aliases.php'=>'Addresses','settings.php'=>'Settings','sessions.php'=>'Active sessions',default=>'Inbox'};
 ?>
 <header class="topbar premium-topbar"><div class="header-identity"><a class="header-brand" href="/" aria-label="PrivateMail inbox"><span class="header-logo"><?=layoutIcon('mail')?></span><strong>PrivateMail<span>.</span></strong></a><span class="header-page-name"><?=$label?></span></div><nav class="desktop-page-nav" aria-label="Main navigation"><?php foreach([['index.php','/','Inbox','inbox'],['aliases.php','/aliases.php','Addresses','address'],['settings.php','/settings.php','Settings','settings']] as [$file,$url,$title,$icon]):?><a href="<?=$url?>" class="<?=$page===$file?'active':''?>" <?=$page===$file?'aria-current="page"':''?>><?=layoutIcon($icon)?><span><?=$title?></span></a><?php endforeach;?></nav><div class="premium-top-actions"><button id="toggleAddressDrawer" class="header-tool mobile-address-button" type="button" aria-label="Open addresses" aria-expanded="false" aria-controls="mailSidebar"><?=layoutIcon('address')?></button><?php if($page==='index.php'):?><button class="header-tool" id="markAllReadButton" type="button" aria-label="Mark all selected mailboxes as read" title="Mark all as read"><?=layoutIcon('check')?></button><?php endif;?><details class="account-menu"><summary class="header-avatar" aria-label="Account menu">ME</summary><div class="account-menu-panel"><span class="account-menu-heading">Your account</span><a href="/sessions.php"><?=layoutIcon('lock')?><span>Active sessions</span></a><a href="/logout.php"><?=layoutIcon('arrow')?><span>Sign out</span></a></div></details></div></header>
 <?php
}

function settingDropdown(string $name,string $value,array $options):void{ ?>
<details class="setting-dropdown"><summary aria-label="<?=Security::e(ucwords(str_replace('_',' ',$name)))?>"><span class="dropdown-value"><?=Security::e($options[$value]??reset($options))?></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary><input type="hidden" name="<?=Security::e($name)?>" value="<?=Security::e($value)?>"><div class="settings-options" role="group" aria-label="<?=Security::e($name)?>"><?php foreach($options as $key=>$label):?><button type="button" data-option="<?=Security::e((string)$key)?>" aria-pressed="<?=$key==$value?'true':'false'?>"><span><?=Security::e($label)?></span><span class="option-check" aria-hidden="true">✓</span></button><?php endforeach;?></div></details>
<?php }
