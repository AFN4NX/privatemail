# PrivateMail

PrivateMail is a self-hosted, receive-only IMAP workspace for a clean inbox, multiple mailbox connections and disposable receiving addresses. It is designed for a single private workspace. It does not send email or replace your mail provider. Messages remain on the IMAP server; PrivateMail stores a local SQLite index and protected workspace settings.

This repository contains the complete application source, setup wizard, migration flow, mobile/desktop UI and test contracts. Start with the setup section below, then read [PROJECT_DOCUMENTATION.md](PROJECT_DOCUMENTATION.md) for the full architecture and operations guide.

## Features at a glance

- Multiple encrypted IMAP connections with arbitrary mailbox selection.
- Responsive Inbox, Addresses and Message Reader views.
- Obsidian Dark, Crisp Light and validated custom themes.
- Mobile floating navigation with a transparent curved notch.
- Catch-all alias creation with domain selection, random names and custom names.
- Read/unread actions, bulk mark-as-read, server Trash and confirmation states.
- Conservative OTP detection with explicit manual copy only.
- Lightweight polling that checks `latest_id` and `unread_count` before fetching a list.
- Progressive history loading, setup wizard and encrypted hosting migration.
- Active-device sessions from the ME account menu.
- PWA installation with static-asset caching and network-only mail APIs.

## Interface and mobile features

- Compact floating curved-notch bar: 56px, 44px active circle, four destinations (Inbox, New Alias, Addresses, Settings), transparent non-intercepting wrapper, safe-area support and reduced-motion fallback.
- Reader: recipient-address copy, per-email Light/Dark control and confirmed Trash action. On mobile, Back to inbox and the three actions share one row. The Source button is removed.
- Contextual 4–8 digit OTP detection with year/date/order/amount safeguards; reader Copy Code banner; inbox one-tap code pill. Codes are copied only on an explicit click; background OTP auto-copy and its setting have been removed.
- Silent polling at the selected 10/15/30/60/120-second interval, visibility pause, stale-response protection, unread counts and preserved list position. Newest-message viewing follows new arrivals until the user chooses a specific message. A chosen message remains stable during unrelated arrivals.
- Mobile pull-to-refresh; left swipe reveals Delete, right swipe toggles read/unread. Delete still requires confirmation.
- Realistic random alias names, fixed/random configured domains and a Dice Random action beside the name input.
- Settings use refresh pills and visual density choices. No custom keyboard shortcuts or audio notifications. Standard Escape/Tab accessibility remains.

## Sessions and PWA

Sessions and authentication cookies are configured for 2,592,000 seconds with HttpOnly, Lax and HTTPS Secure cookies. Private writable `storage/sessions` avoids the hosting server's shared short-lifetime session cleanup. Authentication expiry uses the same 30-day window. Moving existing sessions to this directory means one initial re-login may be required. Persistent hosting storage and server session configuration still matter.

`manifest.json`, install icons, Apple metadata and `sw.js` enable installation on supported HTTPS/localhost browsers. iOS uses Share → Add to Home Screen. The worker caches only an explicit list of static assets and a generic offline page. It never caches authenticated HTML, API responses, email content, source, login data or POST requests. Full offline mailbox access is intentionally absent.

## Storage and deployment

Existing `config/app.php`, `config/imap.php`, `config/domains.php`, database and private storage contents are omitted so overlay installation preserves them. Fresh installs copy the `.example.php` files. PHP 8.1+, IMAP, PDO_SQLite, mbstring and DOM for rich rendering; HTTPS and writable database/storage are required. No default password.

Trash uses an existing server folder, detects common names or reads `trash_mailbox` from IMAP config. No permanent EXPUNGE is executed. Read/unread and Trash requests require authentication and CSRF. Rich mail has an opaque sandbox, a nonce-authorized theme/height bridge and new-tab HTTP(S) links. External tracking resources remain blocked.

Initial/new-mail sync inspects the latest 50 messages. Older history is available through explicit incremental loading. Attachments/CID, full-body search and conversation threading are not implemented. OTP detection is conservative regex classification, not sender authentication.

## Checks

Run `node tests/run.cjs` for the bundled offline JavaScript suites. Run `php tests/smoke.php` and PHP lint on a PHP host. Node behavior, regex-pattern fixtures, exact token checks, PWA cache boundaries and integration wiring passed during this build. PHP runtime/live IMAP, device gestures and browser/PWA installation were not executable in the build environment and need host/device validation.

`preview.html`, `preview-aliases.html` and `preview-navigation.html` contain sample content. Production-only mail/server actions are not available in the static demos.

## Open-source quick start

### Requirements

- PHP 8.1 or newer
- IMAP, PDO SQLite, mbstring, OpenSSL and DOM extensions
- HTTPS in production, or localhost for development
- Writable `database/` and `storage/` directories
- A receiving IMAP mailbox with TLS access, normally port 993
- A configured catch-all route if aliases should receive mail

Apache deployments should keep the included `.htaccess` active. Nginx deployments need equivalent rules that deny direct access to `app/`, `config/`, `database/`, `storage/`, test files, backups, keys and session files.

### First installation

1. Extract the repository into the website document root.
2. Make `database/` and `storage/` writable by PHP. Keep them private.
3. Open the site and complete the dependency check in the setup wizard.
4. Open `storage/setup-token.php` in the hosting File Manager. Copy only the token inside the quotes after `return`.
5. Claim the installation and choose a strong app password.
6. Add and test an IMAP mailbox, then add the domains used for aliases.
7. Choose theme, refresh and history settings, finish setup and send a test email.

The setup token intentionally does not reveal itself through a normal browser request. Configured installations skip the wizard and use Settings.

### Updating an existing installation

Back up the database, real config files and private storage. Extract this source over the existing application files, preserve the live config/database/storage, confirm write permissions and hard-refresh the browser. The release excludes live credentials, messages, database files, encryption keys, sessions and generated backups.

### Hosting migration

Use **Settings → Backup & Migration**. Export an encrypted `.pmb` using the current app password and a separate backup password. Install the new host, complete its setup wizard, upload the `.pmb`, preview it and use Merge for a normal migration. Replace is destructive to managed local configuration and should only be confirmed after reviewing the preview and recovery details. IMAP-server mail is not exported and must be migrated separately when changing mail providers.

## Repository structure

```text
index.php / aliases.php / settings.php   Main authenticated views
sessions.php / setup.php / backup.php    Sessions, setup and migration
login.php / logout.php                   Authentication screens
app/                                     PHP services and shared views
api/                                     Authenticated JSON endpoints
assets/                                  CSS, JavaScript and icons
config/*.example.php                     Safe configuration templates
database/                                Runtime SQLite location
storage/                                 Keys, sessions, locks and staging
tests/                                   Node, Python and PHP checks
manifest.json / sw.js                    PWA metadata and service worker
PROJECT_DOCUMENTATION.md                 Detailed project documentation
```

Important modules include `Database.php` for SQLite and migrations, `MailService.php` for one IMAP connection, `MultiMailbox.php` for selected-account aggregation, `MailboxRegistry.php` for encrypted connections and domain mapping, `Auth.php` and `SessionRegistry.php` for authentication, `SetupService.php` for first install, and `BackupService.php` for encrypted migration.

## API and performance model

All application endpoints require authentication and state-changing requests require CSRF protection. `/api/status.php` returns only `latest_id` and `unread_count`. Unchanged IDs do not trigger full inbox downloads. Pending requests do not overlap, hidden tabs pause polling, and static service-worker assets use stale-while-revalidate. APIs, authenticated HTML, email content and POST requests are never cached.

The main endpoints are `/api/status.php`, `/api/inbox.php`, `/api/older.php`, `/api/message.php`, `/api/message_actions.php`, `/api/mark_all_read.php` and `/api/aliases.php`. Consult the endpoint source for exact parameters and responses.

## Security and open-source boundaries

Sessions use private storage, HttpOnly/SameSite cookies and a 30-day lifetime. Password changes rotate the auth revision. IMAP credentials use AES-256-GCM with `storage/accounts.key`. Rich email is sandboxed and its bridge is nonce-bound. IP addresses in Active Sessions identify the connection and are not guaranteed exact location.

PrivateMail is not a hosted service, SMTP sender, spam filter or full offline mail archive. DNS, MX, catch-all routing, mailbox provisioning and mail-server retention remain outside the application.

Never commit real credentials, live mail or runtime data:

```text
config/app.php
config/imap.php
config/domains.php
database/*.sqlite*
storage/accounts.key
storage/sessions/*
storage/*.pmb
```

## Contributing

Keep pull requests focused and explain user-facing behavior and security impact. Run the Node and Python checks relevant to the change, run PHP checks on a PHP 8.1+ staging host for backend changes, preserve the Light/Dark token system and reduced-motion behavior, and include a short desktop/mobile manual-test note for UI changes. Never include credentials, production databases or private backups in a pull request.

## License

This repository is prepared for open-source publication. Before publishing, add the copyright holder and chosen license. MIT is a practical default for a personal project, but the project owner should explicitly choose the license before adding a `LICENSE` file.

See [PROJECT_DOCUMENTATION.md](PROJECT_DOCUMENTATION.md) for the complete data model, deployment checklist, troubleshooting guide, backup format, limitations and maintenance notes. Bengali deployment notes remain available in `INSTALL_BN.md`, `MIGRATION_GUIDE_BN.md` and `SETUP_BACKUP_CHECKS.md`.

## Lightweight refresh and transparent navigation

Automatic ticks call `/api/status.php`, returning only integer `latest_id` and `unread_count` fields. The successful JSON body is under 100 bytes; HTTP headers are additional. An unchanged mailbox uses IMAP STATUS and a database aggregate without downloading message headers or bodies. UIDNEXT or mailbox identity changes synchronize the cache before reporting the ID.

Only an increased latest ID triggers a background list update. Unread-only changes update counters. Manual refresh, search, pull-to-refresh and user mutations may explicitly fetch the list. External deletions or flag changes do not redraw rows until a manual refresh or new arrival. Pending requests suppress polling; hiding the tab aborts the active status/list fetch and stops the timer.

Static CSS, JavaScript, icons and local fonts use Stale-While-Revalidate. API requests always use the network and never enter the service-worker cache. The mobile pill is an actual SVG path with a concave notch. The outer wrapper has no fill, blur or shadow; clipped hit regions let touches through the notch and rounded edges.

Mobile content fills the entire viewport behind the floating navigation. Bottom clearance lives inside the scrolling content, rather than shortening the workspace and exposing a blank page-background rectangle.

Address headings and search controls stay fixed while only their rows scroll. Address and inbox list scrollbars are hidden without disabling scrolling. The random-generation icon sits inside the email preview card beside Copy.

## Settings editor

General uses radio pills for refresh intervals and two density previews. Appearance offers Dark, Light, System and one named Custom theme with Lime/Violet/Ocean Blue/Rose presets, live preview, contrast warning and reset. Only validated six-digit hex colours are stored. The saved custom theme applies throughout the authenticated workspace.

Security requires the current app password, confirmation and a 12–72-byte new password. The new hash is stored in the existing protected SQLite database and takes precedence over config/environment passwords. Other sessions are invalidated while the changing session stays signed in. Keep database backups along with the config. Administrators recovering forgotten access can remove the `access_password_hash` and `auth_revision` rows from `app_settings` to restore the configured password, then set a new password.

Mail Connection requires the current app password, hostname, port, SSL/TLS or STARTTLS, username and mailbox folder. Blank mailbox password retains the current secret. Test Connection keeps the input fields in place; Test & save always verifies the submitted credentials again before atomic config replacement. Failure leaves the existing file untouched. `config` must be writable by PHP for saves. Existing optional IMAP settings such as Trash are preserved. Mailbox/account changes keep alias definitions; the next sync replaces cached messages when mailbox identity changes.

Run `php tests/settings.php` on a PHP host for offline theme validation, password rotation and session-invalidation tests, in addition to the existing checks. These PHP tests were supplied but not executed in the build environment, which has no PHP runtime. Browser/device layout and live IMAP tests remain host-side checks.

Addresses use a responsive card grid with icon-only Copy/Open inbox/Delete actions, accessible labels, hover/focus tooltips, copy check feedback and reduced-motion-aware micro-interactions. Search, sort and deletion confirmation remain unchanged.

The Addresses controls are condensed into a single desktop row or two compact mobile rows: title/count, search, icon sort and an icon Add button. Introductory copy and the separate count row are removed. Card borders use a restrained mix of the active theme accent and the neutral border.

## Message history limit

Settings → General → Message limit offers 50, 100, 200, 500 and All. The initial inbox remains a maximum 50-row page. Load older messages reads another cached page (up to 50 rows) or indexes up to 25 older IMAP messages within an eight-second soft budget per request. All removes the history cap, not the per-request batch size. Loading is user-triggered, not part of status polling, and uses the same client request lane and server mailbox lock. New-arrival updates retain already loaded older rows; manual refresh/search starts from the latest page. Aliases/search may need repeated loading while unrelated older messages are indexed. Only existing server messages can be retrieved. Increasing the setting does not pre-download the entire mailbox.

Older UID discovery uses an IMAP UID-only search; summary/body classification is restricted to the batch. Large mailboxes can still take time to index. Newest-ID polling remains the original two-field API. Host-side PHP/IMAP checks are required for the new history path.

## Shared email reading preference and custom values

General now offers Auto refresh Off/10s/1m/Custom (10–86400 seconds) and Message limit All/50/100/Custom (1–100000). Previously saved intervals and limits select Custom automatically when necessary. A shared Email body theme Light/Dark preference applies to every opened message, independently of the app appearance. Reader-level theme controls are removed. Save General and reopen/reload the inbox to apply the shared preference. All still uses incremental Load older requests.

## Multi-IMAP release

Settings now manages multiple encrypted IMAP connections and receiving domains. Choose all mailboxes, none, or any checkbox combination and Save selection; reopen the inbox to apply. Inbox/search/history/counts only use selected accounts. Each message has an owning account; its read/unread, body and Trash operations use that connection. Sync identities, UIDNEXT, locks and history cursors are independent. One failed account leaves other cached accounts available. Status checks remain two integers; IMAP STATUS is checked for each selected connection.

On first upgrade the existing `config/imap.php` is imported as Primary mailbox without copying its password into plaintext database fields. Existing domain config is imported once. Existing message IDs and the SQLite autoincrement high-water mark are preserved while uniqueness becomes `(account_id, imap_uid)`. New/updated credentials use AES-256-GCM with a private key in `storage/accounts.key`; PHP OpenSSL and writable storage are required. Back up the database AND this key together. A missing original key cannot decrypt saved accounts. Removing an account deletes only its local cached rows and connection, never server messages. Linked domains must first be reassigned/removed. Domain removal/deactivation keeps existing aliases and received mail; inactive domains cannot create new aliases. Mapping does not alter MX, DNS, cPanel routing or provision actual mailboxes.

New accounts test successfully before saving. Editing keeps the old mailbox password when the field is blank. Credentials changes and connection removal require the current app password; selection/domain preferences use authenticated CSRF-protected forms. Connection errors never display mailbox passwords.

Before upgrading, stop app requests and make a consistent backup of the existing SQLite database (including its WAL state through SQLite backup or a stopped app), config and storage. Test on a copy first because the message table migrates. The separate approved stable-base ZIP is retained; rollback also requires restoring its matching pre-upgrade database/config/storage backup.

`python3 tests/multi-migration.py` exercises production SQL contracts for migration/UID collisions/selection/upserts/removal. `php tests/multi.php` exercises the actual PHP registry and encryption on an isolated temporary tree. PHP/live-IMAP and browser/device checks have not run in this build environment. The SQL/Python and JavaScript checks have run successfully. Large account counts and slow providers increase polling round-trip time; requests remain non-overlapping.

## Second multi-IMAP audit

Read AUDIT_BN.md for confirmed defects, fixes, checks actually executed and remaining host-side validation. Corrected code uses shared account locks, missing-key protection, a view-revision header, delivered-ID history exclusion, cooldown-before-connect, and POST/Redirect/GET for settings updates. Run `php tests/sync.php` alongside the existing PHP checks on a PHP host.

## Setup and portable hosting migration

Read MIGRATION_GUIDE_BN.md. Fresh installations use a private File Manager token to claim ownership, then a password/mailbox/domain/preferences wizard. Existing configured sites skip setup. Settings links to Backup & Migration: encrypted portable `.pmb` export, password-free settings JSON, authenticated upload/preview, Merge/Replace and encrypted automatic recovery before Replace. Local app authentication, server paths, keys and message caches are excluded. Imported secrets use the target installation’s key, so moving the web app does not require the source key. Actual mail server data is not exported. Setup/backup PHP flows and real upload/restore still require staging validation; the provided Python crypto/SQL checks are contract tests only.

Fresh setup token is stored in storage/setup-token.php, which returns HTTP 404 with no body when executed directly. Read its quoted token through File Manager. It is deleted after ownership is claimed. Private-directory server rules remain required for database/key/session protection.

Login and sign-out use the robot authentication screens described below.

## Robot authentication screens

Login and sign-out now share a neutral Obsidian/Lime robot card inspired by the user-provided CodePen reference (https://codepen.io/editor/itayko/pen/019fc70d-b75a-73b9-a643-f75d58b3199b). The character independently uses CSS 3D head turning, a NOT LOOKING back panel, cursor gaze, small blinks and password focus/input/reveal reactions. No name/email fields were added to the single-password app. No password value is reflected into the character or transmitted by the animation script. Moving background glow/blur layers are removed. Sign-out remains an authenticated CSRF-protected POST with Stay in inbox, loading feedback and a small character wave. Reduced-motion disables the transitions. Standalone preview-login.html/preview-logout.html contain sample UI only; browser/device rendering of this implementation still needs validation.

Confirmation companions use the same Lime-outlined robot design across address/message deletion, mailbox/domain removal, backup import preview and sign-out. Destructive hover/focus/touch shows red eyes and a sad mouth; Cancel/Keep shows a happy expression; password focus shows NOT LOOKING. This layer never invokes delete, submit or logout, and preserves the existing explicit confirmation logic. Closed settings confirmations initialize lazily. Reduced-motion is respected.

On hoverless/coarse-pointer devices destructive confirmations default to sad red eyes on opening, including when Cancel receives programmatic focus. Explicit Cancel/Keep touches show happy feedback; password fields keep privacy feedback. Reopening resets the mood and desktop hover behavior is retained.

Create-address dialog is compact: inline Name/mode selector, no duplicate random-name display, smaller full-address preview with horizontal Shuffle/Copy icons, and tighter spacing. Custom mode keeps its editable local-part field. Existing form control IDs/submission/domain logic are retained.

Address builder: mode controls inside preview, compact mail robot, animated mail-plus icons, mobile inline creation workspace, accent card strokes. Motion honors reduced-motion preferences.

Mobile address studio: larger preview and touch targets, no mobile close icon or outside-tap dismissal, creation URL retained until Cancel/success, trailing scroll spacers expose the final address/settings actions above floating navigation.

Inbox polish: neutral row dividers, unread count at heading instead of loaded count/topbar, polling-aligned circular interval progress, mode controls beside Domain and circular regenerate action.

Unread count and polling progress share one circle, with no seconds label. Domain dropdown and Random/Custom controls share one line; regenerate uses an animated SVG orbit and sparkle, respecting reduced motion.

Topbar check action marks all unread messages in selected IMAP mailboxes as read, including unloaded history. Authenticated CSRF POST, account locks, UIDVALIDITY checks, batched UID flags and per-mailbox failures preserve local/server consistency. Single-message read API now correctly handles the void-returning setRead method.

Active sessions (Settings): device/browser labels, sign-in/last-active timestamps in Dhaka time, connection IP, current device marker and CSRF-protected single/all-other revocation. Raw PHP session IDs and registry tokens are never displayed. Registry stores token hashes; legacy authenticated sessions are enrolled on next use. Revocation applies on the next authenticated request. IP is not an exact location. The registry is not included in portable backup exports. Host test: php tests/sessions.php. PHP runtime execution still requires host verification.

Active sessions moved from Settings into ME account menu → Active sessions, with a dedicated responsive page. Sign out remains in the same account menu.
