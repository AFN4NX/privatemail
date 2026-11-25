<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
Auth::require();
$sort=(string)($_GET['sort']??'new');
$sort=in_array($sort,['new','old','az','za','domain'],true)?$sort:'new';
$search=trim((string)($_GET['search']??''));
$aliases=Database::aliases($sort);
if($search!=='') $aliases=array_values(array_filter($aliases,fn($a)=>stripos((string)$a['email'],$search)!==false||stripos((string)$a['domain'],$search)!==false));
$allAliases=Database::aliases('new');
$domains=MailboxRegistry::activeDomains();
$domains=array_values(array_unique(array_filter(array_map(fn($d)=>strtolower(trim((string)$d)),$domains))));
$csrf=Security::csrfToken();
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Aliases — PrivateMail</title><link rel="stylesheet" href="/assets/tokens.css?v=10"><link rel="stylesheet" href="/assets/app.css?v=14"><link rel="stylesheet" href="/assets/mobile.css?v=11"><link rel="stylesheet" href="/assets/magic-nav.css?v=15"><link rel="stylesheet" href="/assets/features.css?v=15"><link rel="icon" href="/assets/icons/icon-192.png" type="image/png"><meta name="application-name" content="PrivateMail"><meta name="apple-mobile-web-app-title" content="PrivateMail"><link rel="manifest" href="/manifest.json"><meta name="theme-color" content="#84CC16"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png"><script src="/assets/features.js?v=22" defer></script><script src="/assets/pwa.js?v=12" defer></script><link rel="stylesheet" href="/assets/address-cards.css?v=3"><link rel="stylesheet" href="/assets/confirmation-robot.css?v=1"><script src="/assets/confirmation-robot.js?v=2" defer></script><link rel="stylesheet" href="/assets/compact-builder.css?v=6"></head>
<body <?=uiAttributes()?> class="aliases-page">
<div class="app-shell">
<?php renderSidebar('aliases.php'); ?>
<main class="workspace">
<?php renderTopbar('aliases.php'); ?>
<section class="aliases-content">
 <header class="address-controls">
  <div class="address-title"><h1>Addresses</h1><span class="address-total" id="aliasCount" aria-label="Total addresses"><?=count($aliases)?></span></div>
  <div class="address-tools"><label class="alias-search" for="aliasSearch"><?=layoutIcon('search')?><input id="aliasSearch" value="<?=Security::e($search)?>" placeholder="Search addresses…" aria-label="Search email or domain" autocomplete="off"></label>
   <div class="alias-sort-control" title="Sort addresses"><?=layoutIcon('settings')?><select id="aliasSort" aria-label="Sort addresses"><option value="new" <?=$sort==='new'?'selected':''?>>Newest first</option><option value="old" <?=$sort==='old'?'selected':''?>>Oldest first</option><option value="az" <?=$sort==='az'?'selected':''?>>A → Z</option><option value="za" <?=$sort==='za'?'selected':''?>>Z → A</option><option value="domain" <?=$sort==='domain'?'selected':''?>>By domain</option></select></div>
  </div>
  <button class="address-add" id="createAliasButton" type="button" aria-label="Create new address" title="New address"><?=layoutIcon('mailplus')?></button>
 </header>
 <div class="alias-list" id="aliasList">
 <?php if(!$aliases): ?><div class="alias-empty"><div>⌁</div><strong>No aliases yet</strong><span>Create your first receiving address to get started.</span></div>
 <?php else: foreach($aliases as $a): ?><article class="alias-card"><div class="alias-main"><div class="alias-avatar" aria-hidden="true"><?=layoutIcon('at')?></div><div><strong><?=Security::e((string)$a['email'])?></strong><span class="alias-domain-caption"><?=Security::e((string)$a['domain'])?></span></div></div><div class="alias-actions" role="group" aria-label="Address actions"><button type="button" class="alias-action copy-alias" data-email="<?=Security::e((string)$a['email'])?>" aria-label="Copy email address" title="Copy address" data-tooltip="Copy address"><?=layoutIcon('copy')?></button><a class="alias-action alias-open" aria-label="Open address inbox" title="Open inbox" data-tooltip="Open inbox" href="/?inbox=<?=(int)$a['id']?>"><?=layoutIcon('inbox')?></a><button type="button" class="alias-action danger delete-alias" data-id="<?= (int)$a['id']?>" data-email="<?=Security::e((string)$a['email'])?>" aria-label="Delete address" title="Delete address" data-tooltip="Delete address"><?=layoutIcon('trash')?></button></div></article><?php endforeach; endif; ?>
 </div>
</section>
</main></div>
<div class="modal-backdrop alias-dialog-backdrop" id="aliasModal" hidden>
 <section class="alias-modal compact-builder" role="dialog" aria-modal="true" aria-labelledby="createAliasTitle" aria-describedby="aliasDialogDescription">
  <header class="alias-dialog-header"><div class="builder-heading"><svg class="builder-robot" viewBox="0 0 92 62" fill="none" aria-hidden="true"><path d="M46 3v7"/><circle cx="46" cy="3" r="2"/><rect x="12" y="10" width="68" height="48" rx="16"/><rect class="br-screen" x="21" y="20" width="50" height="29" rx="9"/><path class="br-eyes" d="M32 29v5m15-5v5"/><path d="M33 40q6 5 12 0"/><rect x="57" y="38" width="26" height="18" rx="4"/><path d="m59 41 11 8 11-8"/></svg><h2 id="createAliasTitle">Create address</h2></div><button class="modal-close" id="aliasModalClose" type="button" aria-label="Close create address"><?=layoutIcon('close')?></button></header>
  
  <p class="visually-hidden" id="aliasDialogDescription">Choose a domain and address name.</p>
  <form id="aliasForm" class="alias-builder">
   <input type="hidden" name="_csrf" value="<?=Security::e($csrf)?>">
   <div class="builder-field"><label class="visually-hidden" id="aliasDomainLabel">Domain</label>
   <input type="hidden" id="aliasDomain" name="domain" value="__random__">
   <div class="domain-inline-controls"><details class="domain-picker" id="domainPicker"><summary aria-labelledby="aliasDomainLabel aliasDomainDisplay"><span class="domain-picker-icon"><?=layoutIcon('at')?></span><span id="aliasDomainDisplay">Random domain</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
    <div class="domain-options" id="domainOptions" role="group" aria-label="Available domains">
     <button type="button" class="domain-option is-picked" data-domain="__random__"><span>Random domain</span><span class="domain-check" aria-hidden="true">✓</span></button>
     <?php foreach($domains as $d):?><button type="button" class="domain-option" data-domain="<?=Security::e($d)?>"><span><?=Security::e($d)?></span><span class="domain-check" aria-hidden="true">✓</span></button><?php endforeach;?>
    </div>
   </details>   <div class="generator-tabs" role="group" aria-label="Address name mode"><button type="button" class="generator-tab active" data-mode="random" aria-pressed="true">Random</button><button type="button" class="generator-tab" data-mode="custom" aria-pressed="false">Custom</button></div></div></div>
   <div class="builder-field name-edit-field">
   <div class="alias-name-field" id="aliasNameField"><input id="aliasLocal" name="local_part" class="generator-custom" aria-label="Address name" maxlength="64" placeholder="Enter your address name" autocomplete="off" autocapitalize="none" spellcheck="false" readonly></div></div>
   <section class="address-preview" aria-label="New email address"><div class="preview-mode-row"><span class="preview-eyebrow">EMAIL ADDRESS</span>
</div><div class="address-preview-row"><output id="aliasPreview" for="aliasLocal aliasDomain" aria-live="polite">Preparing your address…</output><div class="preview-tools"><button type="button" id="aliasRegenerate" aria-label="Generate another address" title="Random address"><svg class="regenerate-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><g class="regenerate-orbit"><path d="M20 10a8 8 0 0 0-14-3L3 10m0-5v5h5M4 14a8 8 0 0 0 14 3l3-3m0 5v-5h-5"/></g><path class="regenerate-spark" d="m12 8 1.2 2.8L16 12l-2.8 1.2L12 16l-1.2-2.8L8 12l2.8-1.2Z"/></svg></button><button type="button" id="aliasCopy" class="preview-copy" aria-label="Copy email address"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="3"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg></button></div></div></section>
   <p class="alias-form-error" id="aliasFormError" role="alert" hidden></p>
   <footer class="builder-footer"><button type="button" class="builder-cancel" id="aliasCancel">Cancel</button><button type="submit" class="builder-submit" id="aliasSubmit"><?=layoutIcon('mailplus')?><span>Create address</span></button></footer>
   
  </form>
 </section>
</div>
<div class="modal-backdrop alias-dialog-backdrop" id="aliasDeleteModal" hidden><section class="alias-delete-dialog" data-confirmation role="dialog" aria-modal="true" aria-labelledby="deleteAliasTitle" aria-describedby="deleteAliasDescription"><div class="delete-dialog-icon"><?=layoutIcon('address')?></div><h2 id="deleteAliasTitle">Remove this address?</h2><p class="delete-email" id="aliasDeleteEmail"></p><p id="deleteAliasDescription">This removes the address from your list. Existing emails stay, and catch-all delivery continues.</p><p id="aliasDeleteError" class="alias-form-error" role="alert" hidden></p><footer class="builder-footer"><button type="button" id="aliasDeleteCancel" data-confirm-cancel class="builder-cancel">Keep address</button><button type="button" id="aliasDeleteConfirm" data-confirm-danger class="builder-submit">Remove address</button></footer></section></div>
<div class="app-toast" id="appToast" role="status" hidden></div>
<script>window.PRIVATEMAIL={api:'/api/inbox.php',aliasesApi:'/api/aliases.php'};</script><script src="/assets/app.js?v=17"></script>
<?php require __DIR__.'/app/Navigation.php'; ?></body></html>
