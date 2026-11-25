# PrivateMail: Project Documentation

Final approved base: 25 November 2026, Asia/Dhaka.

## 1. Project overview

PrivateMail একটি personal, receive-only webmail workspace। একাধিক IMAP mailbox-এর mail এক inbox-এ দেখা, catch-all address তালিকা manage করা এবং verification code সহজে copy করা যায়। PHP server, SQLite local index এবং vanilla JavaScript frontend ব্যবহার করে। এটি multi-user SaaS নয়: একটি app access password দিয়ে একই personal workspace-এ প্রবেশ করা হয়। IMAP accounts app users নয়। SMTP sending নেই।

| Area | Implementation |
| --- | --- |
| Backend | PHP 8.1+, session authentication, PDO SQLite |
| Mail | PHP IMAP extension, TLS connections |
| Frontend | HTML, CSS semantic variables, vanilla JavaScript, inline SVG |
| Local persistence | SQLite index/preferences/aliases/account registry |
| Credential protection | AES-256-GCM encrypted account configuration with local key |
| Migration backup | Password-encrypted portable `.pmb`, optional settings JSON |
| Installable app | Web manifest, static-asset service worker, standalone display |
| Deployment | Website document root; root-relative URLs |

## 2. Requirements

- PHP 8.1 or later with PDO_SQLite, mbstring, IMAP, OpenSSL and DOM.
- HTTPS in production. Localhost is supported for development.
- Writable `database/` and `storage/`, including private `storage/sessions/`.
- Outbound network access to configured IMAP hosts/ports, normally TLS port 993.
- Apache `.htaccess` support, or equivalent Nginx/server access restrictions.
- Existing receiving mailbox, working DNS/MX and catch-all delivery on the mail server.

Adding domains here does not create DNS, provision mailboxes or configure catch-all routing. The app manages its own alias/domain list. Mail hosting must already deliver those addresses to the connected IMAP account.

## 3. Fresh installation

1. Extract the full project into the domain/subdomain document root.
2. Allow PHP to write to `database/` and `storage/`. Do not make private files publicly readable.
3. Open the site. The setup wizard checks dependencies and ownership requirements.
4. In hosting File Manager, open `storage/setup-token.php`. Copy only the token inside quotes after `return`, not the entire PHP file. A direct browser request intentionally does not reveal it.
5. Claim installation ownership and choose a strong app access password.
6. Test/save an IMAP connection, add a receiving domain and choose appearance/refresh settings. Alternatively, restore an existing encrypted backup.
7. Finish setup and verify incoming email, read state and mobile layout.

Configured installations bypass first-time setup. The example config files remain available for manual deployment, but the wizard is the preferred fresh-install flow.

## 4. Updating an existing installation

1. Back up live files, SQLite database and private storage before replacing source.
2. Extract this complete ZIP over the existing source. Separate historical patches are unnecessary.
3. Preserve actual `config/app.php`, `config/imap.php`, `config/domains.php`, database files and storage.
4. Confirm writable directories, HTTPS, login, IMAP selection and unread counts.
5. Hard refresh the browser. Service-worker asset versions invalidate older UI caches.

The distribution intentionally excludes real credentials, live database files, local encryption keys, PHP session files and generated encrypted backups. Example configs and protection files are included. Deleting a distributed `tests/` directory is optional for production, but it must never be publicly executable if retained.

## 5. Folder and file structure

| Path | Responsibility |
| --- | --- |
| `index.php` | Inbox list and reading pane |
| `aliases.php` | Address cards and address builder; mobile inline creation page |
| `settings.php` | General, appearance, security, IMAP accounts and domains |
| `sessions.php` | Active devices and remote sign-out; reached through ME menu |
| `login.php`, `logout.php` | Password authentication and POST sign-out confirmation |
| `setup.php` | First-install ownership and configuration wizard |
| `backup.php` | Export, import preview/confirmation, recovery download |
| `bootstrap.php` | Security headers, session configuration, classes and schema setup |
| `app/` | Domain services, database, authentication and shared view components |
| `api/` | Authenticated inbox/status/history/message/address operations |
| `assets/` | Theme tokens, layouts, behavior scripts and app icons |
| `config/*.example.php` | Safe configuration templates |
| `database/` | Runtime SQLite database, protected from direct web access |
| `storage/` | Runtime key, session files, locks and backup staging/recovery |
| `tests/` | Node/Python checks and PHP host test scripts |
| `manifest.json`, `sw.js`, `offline.html` | PWA metadata, static cache and generic offline fallback |
| `preview*.html` | Static demo pages, not authenticated application pages |
| `PROJECT_DOCUMENTATION.md` | Current full project guide |

### Backend modules

| Module | Main responsibility |
| --- | --- |
| `Database.php` | SQLite connection, schema migration, index/search/history/preferences |
| `MailService.php` | One mailbox connection, sync, MIME rendering and read/Trash operations |
| `MultiMailbox.php` | Selected-account aggregation, status and history |
| `MailboxRegistry.php` | Encrypted connection storage, selected accounts, domains and locks |
| `MailClassifier.php` | Classification and conservative OTP extraction |
| `Auth.php` | Password authentication, revision checks and session lifetime |
| `SessionRegistry.php` | Device-session registry, metadata and revocation |
| `Security.php` | Escaping, CSRF tokens and JSON responses |
| `SettingsService.php` | Validated appearance and connection configuration |
| `SetupService.php` | Installer ownership, setup state and initial configuration |
| `BackupService.php` | Portable snapshots, encryption, preview, import and rollback |
| `Layout.php`, `Navigation.php` | Sidebar, topbar, ME menu, icons and mobile navigation |
| `AuthRobot.php` | Shared login/logout mascot markup |
| `*View.php` | Settings/session/account/domain view components |

### Frontend modules

| Asset | Main responsibility |
| --- | --- |
| `tokens.css` | Light/dark semantic theme variables |
| `app.css`, `mobile.css` | Shared desktop/mobile layouts and controls |
| `magic-nav.css`, `navigation.js` | Transparent SVG notch and sliding mobile indicator |
| `features.css`, `features.js` | Polling, reader updates, OTP, gestures and bulk mark-read |
| `app.js` | Alias builder, copy actions, drawer, menus and shared interaction |
| `address-cards.css` | Address grid, icon actions and compact controls |
| `compact-builder.css` | Address creation layout and animated regenerate/copy feedback |
| `settings.css`, `settings.js` | Settings controls, custom themes and connection testing |
| `login.css`, `login.js` | Robot/password interaction and login/logout presentation |
| `confirmation-robot.*` | Confirmation mascot states, including mobile danger state |
| `migration.css`, `pwa.js` | Backup screens and PWA registration |

## 6. Data model and private files

SQLite uses WAL mode and a busy timeout. The primary runtime tables are:

| Table | Data |
| --- | --- |
| `messages` | Local message metadata/index, account ID, IMAP UID, classification, OTP and read state |
| `aliases` | Managed address, local part, domain and creation time |
| `app_settings` | Preferences, auth revision and account sync cursors |
| `mail_accounts` | Connection name, encrypted config, legacy and selected flags |
| `managed_domains` | Domain, optional account mapping and active flag |
| `login_attempts` | Temporary authentication failure counters |
| `active_sessions` | Opaque ID, token hash, sign-in/activity times, IP, user agent and revocation |

Message uniqueness is scoped to `(account_id, imap_uid)`. UIDs from different IMAP accounts must not be treated as globally unique. Mailbox identity/UIDVALIDITY protects cached UID operations from acting on a changed mailbox.

The SQLite index is not a full offline email archive. Original email remains on the IMAP server; bodies are fetched/rendered when needed. A source release or app backup does not replace a separate mail-server backup.

`storage/accounts.key` must stay paired with the runtime database when copying raw installation files. Losing the key can make saved IMAP credentials unreadable. Portable encrypted export re-encrypts credentials on the new host and does not require copying the old key.

## 7. Main request flows

### Authentication

Bootstrap starts a private PHP session. Protected pages/API call `Auth::require()`. Passwords are verified against the stored hash or configured fallback. Successful login rotates the PHP session ID. Login failures are rate-limited by the direct connection address. Password changes rotate auth revision so other sessions must sign in again.

Session cookie and idle lifetime are configured for 30 days, not an unconditional guarantee: storage cleanup, browser cookie removal, host policy or manual revocation can end a session earlier.

### Inbox and polling

The initial page renders cached messages. At the selected refresh interval, JavaScript requests a lightweight status response with `latest_id` and `unread_count`. Unchanged latest ID does not trigger a full list download. An increased ID permits an inbox UI refresh. Explicit search, history, pull-to-refresh and mutations can also update the list.

Pending requests suppress overlapping polling. Hidden tabs pause polling and abort pending status/list work. The unread badge is also the circular refresh progress indicator, without a seconds label. It is not the number of loaded messages.

A changed mailbox-view header detects configuration/selection changes. Unread-only changes do not force a full message-list redraw on every status tick.

### History

Settings support All, 50, 100 or a custom limit. All allows progressive history loading, not downloading the entire mailbox on every poll. Older-message batches and delivered-ID exclusion keep multi-account pagination stable.

### Read and Trash

Per-message actions verify the mailbox identity and use IMAP UID operations. Mark all as read applies to all selected mailbox folders, including unread messages outside the loaded window. Partial mailbox failures do not report complete success. Trash moves mail to an existing server Trash folder; removing an address only removes its app list entry, not received email.

## 8. API map

All endpoints require authentication. State-changing POST requests require CSRF protection. APIs are network-only in the service worker.

| Endpoint | Purpose |
| --- | --- |
| `GET /api/status.php` | Tiny latest-ID/unread-count check |
| `GET /api/inbox.php` | Current filtered inbox list and metadata |
| `POST /api/older.php` | Older history batch/cursor processing |
| `GET /api/message.php` | Selected message and rendered content |
| `POST /api/message_actions.php` | Read, unread or Trash for one message |
| `POST /api/mark_all_read.php` | Mark unread server mail in selected accounts read |
| `/api/aliases.php` | Alias metadata/list and POST create/delete operations |
| `GET /api/source.php` | Authenticated raw source endpoint; no viewer Source button |

For exact parameter names and responses, consult each endpoint and its JavaScript caller. Keep integrations on the same authenticated origin; these are application endpoints, not a public unauthenticated API.

## 9. Settings and daily use

- General: refresh Off, 10s, 1m or custom input; history All, 50, 100 or custom; density; global email-body Light/Dark.
- Appearance: Dark, Light, System or validated custom colors with preview/contrast warning.
- Security: change app access password.
- Connected mailboxes: add/update/test/remove connections and select any subset for the inbox.
- Domains: add/update/remove and enable/disable domains used in alias creation.
- ME → Active sessions: device/browser, sign-in time, last activity, IP, current-device marker and remote sign-out.
- ME → Sign out: sign out current device through confirmation.

Device labels are user-agent descriptions, not verified hardware identities. IP is the direct server connection address, not guaranteed geography. Behind a reverse proxy it can be the proxy IP. Untrusted forwarded headers are not accepted as device location. Old sessions appear after they are used following this update. Last activity updates roughly once a minute; revocation is checked on subsequent authenticated requests.

## 10. UI and design system

| Token | Light | Dark |
| --- | --- | --- |
| App background | `#F8FAFC` | `#09090B` |
| Panel surface | `#FFFFFF` | `#121215` |
| Input/hover | `#F1F5F9` | `#1C1C21` |
| Neutral border | `#E2E8F0` | `#27272A` |
| Main text | `#0F172A` | `#FAFAFA` |
| Muted text | `#64748B` | `#A1A1AA` |
| Accent | `#84CC16` | `#A3E635` |
| Accent hover | `#65A30D` | `#BEF264` |

Desktop uses address sidebar, message list and reader. Mobile/tablet up to 900px uses a four-tab floating navigation with a true transparent SVG notch. Scroll spacers expose the last controls above the bar. The mobile address builder is inline, does not close on empty-space taps and hides its modal X.

Address builder domain dropdown, Random and Custom share a row. Regenerate and Copy icons are centered; regeneration has touch feedback and Copy becomes a checkmark without a success toast. Robot password privacy and destructive confirmation moods support mobile. Animations respect reduced-motion preference. No custom keyboard shortcuts or notification sounds are added.

## 11. Email rendering and OTP

HTML email is sanitized and rendered inside an opaque sandboxed iframe, with a restricted CSP and no same-origin privilege. A nonce/channel-bound bridge resizes the frame, avoiding a nested body scroll area. External HTTP(S) links open outside the iframe in a new tab. Remote images/tracking are restricted by the rendering policy, so an email may not exactly match its sender's web version.

Global email-body theme is separate from app theme and applies to all messages. Dark conversion is a readability adaptation; third-party HTML can still vary. OTP detection is conservative, uses 4–8 digit patterns and filters common year/date/amount false positives. Detection is not guaranteed for every provider. Code copying is manual; auto-copy incoming OTP is intentionally absent.

## 12. Backup, export and import

### Export types

Encrypted migration `.pmb` is the portable option for moving hosting: it contains supported app settings, aliases, domains and IMAP connections including credentials protected by a backup password. Current app password authorizes export; backup password is a newly chosen separate encryption password, not an existing account password.

Settings JSON excludes IMAP passwords. New imported connections can require passwords and testing before use. Neither format includes the app password/hash, live sessions, raw encryption key or server email archive.

### Import modes

Merge keeps matching existing entries and imports supported data/preferences. Replace replaces managed accounts/domains/aliases/local message cache after preview and confirmation. Replace creates an encrypted recovery snapshot first; JSON Replace requires choosing a recovery password.

Imports are validated, limited to 10 MiB subject to hosting upload limits, staged encrypted and expire after a short preview window. Review the preview before confirming. The implementation retains the last five recovery snapshots. Backup passwords are not recoverable by the app.

### Moving hosting

1. Export encrypted `.pmb` on the old host and keep its password separately.
2. Install this source on the new HTTPS host and claim setup with a new app password.
3. Import the `.pmb`, review preview and confirm.
4. Test all mailboxes, selections and active domains, then sync the inbox.
5. Switch website DNS only after verification.
6. If the mail server is also being closed, migrate server email separately first.

For a raw-file restore, copy a consistent database, actual config and matching private key together while writes are stopped. Do not restore another host's active PHP sessions as a portable migration strategy.

## 13. Security and deployment checklist

- Keep `app/`, `config/`, `database/`, `storage/` and `tests/` inaccessible directly from the web.
- Verify Apache directory protection files are honored; Nginx ignores `.htaccess` and needs equivalent rules.
- Deny database, backup, key, staged import and session files. Disable directory listing.
- Do not publish real credentials, database files or keys in a repository or source ZIP.
- Keep TLS certificate validation enabled for IMAP; do not enable insecure validation bypasses.
- Use a strong app password and separate strong backup passwords.
- Keep PHP, extensions and hosting security updates current.
- Review ME → Active sessions and revoke unexpected devices.
- Keep encrypted exports outside the public document root and remove obsolete sensitive backups deliberately.
- Docs/previews are not needed for runtime. Avoid publishing them if source/architecture exposure is unwanted.

## 14. Tests and validation limits

Node frontend/contracts:

```bash
node tests/run.cjs
```

Python schema/backup contracts:

```bash
python3 tests/multi-migration.py
python3 tests/backup-contracts.py
```

PHP host checks, using the test scripts' isolated fixtures:

```bash
php tests/smoke.php
php tests/settings.php
php tests/multi.php
php tests/sync.php
php tests/backup.php
php tests/sessions.php
```

This release was checked with the available Node suite, release/token source checks, SQLite contract checks and ZIP integrity checks. PHP runtime execution, real IMAP mutation and physical mobile visual testing were not performed in the authoring environment. Source checks and mocked browser tests are not substitutes for host/device testing.

After deployment, test login/logout, remote session revocation from a second browser, password change, IMAP connection test, unread/read actions, bulk mark-read, Trash, alias creation/copy, hidden-tab polling, older history, encrypted export/import and final-row mobile scrolling.

## 15. Troubleshooting

| Symptom | Check |
| --- | --- |
| Setup appears unexpectedly | PHP extensions, configured password and database/storage access |
| Saved connection cannot decrypt | Matching `accounts.key`; restore encrypted export if key is lost |
| IMAP test fails | Host/port, credentials, certificate, outbound firewall and mailbox folder |
| New domain receives no email | DNS/MX and catch-all routing on the mail host |
| Old UI remains | Hard refresh and service-worker update; confirm changed source uploaded |
| Unread number differs from visible rows | Count is unread mail in selected accounts; loaded/history/filter rows differ |
| Device IP is `::1` | Local IPv6 loopback, normal on localhost |
| Every device shows same IP | Shared network, VPN or reverse proxy; IP is not a unique device ID |
| Session missing | Use that older session once after upgrade; expired/revoked sessions are hidden |
| Copy unavailable | Secure context, browser clipboard permission, supported device/browser |
| Trash fails | Configure an existing server Trash folder; check connection identity |
| Import rejected | Password, file type/size, PHP upload limit, preview expiry or conflict |

## 16. Maintenance and final-base policy

Use the latest complete source as the base for future changes. Keep an independent live-data backup before upgrades. This final package includes the current full documentation and existing detailed setup/migration guides. Historical source ZIPs are not needed to install it.

The source ZIP contains no personal mailbox credentials or live messages. Keeping one source release does not mean deleting runtime `storage/`, database files or the live encryption key. Those are required for the deployed app.
