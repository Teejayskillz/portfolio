# Deployment guide — cPanel shared hosting

Target: **https://tijanidev.com.ng** (root of `public_html`).
This guide assumes cPanel + Apache + PHP 8.x with `mod_rewrite`.

> **Before you start:** the domain currently serves an unrelated Botble/"James.dev" demo
> theme. Everything in this repository replaces it. Take a cPanel backup of the old
> `public_html` first if you want to keep any of it.

---

## 1. Requirements

| Requirement | Why | How to check / fix |
|---|---|---|
| PHP 8.0 or newer | Code uses PHP 8 syntax | cPanel → **Select PHP Version** → choose 8.2 or 8.3 |
| `pdo_sqlite` | The database | same screen → **Extensions** → tick `pdo_sqlite`, `sqlite3` |
| `mbstring` | UTF-8 safe strings | Extensions → tick `mbstring` |
| `fileinfo` | Real image type detection | Extensions → tick `fileinfo` |
| `session`, `json`, `openssl` | Login, JSON-LD, TLS helpers | enabled by default |
| Apache `mod_rewrite` | Clean URLs (`/project/slug`) | usually enabled; see §10 if not |
| PHP `mail()` | Contact form delivery | enabled by default on cPanel |
| HTTPS (AutoSSL) | Secure cookies, trust | cPanel → **SSL/TLS Status** → Run AutoSSL |

The installer checks all critical items and refuses to run if one is missing.

---

## 2. Files to upload

Upload the **contents** of this project into `public_html` using cPanel File Manager
(zip → upload → extract) or FTP. You should end up with:

```
public_html/
  index.php  projects.php  project.php  contact.php  thank-you.php  404.php
  sitemap.php  install.php  robots.txt  ads.txt  .htaccess
  admin/  assets/  includes/  storage/  templates/  (tools/ optional)
```

Do **not** upload:

* `storage/portfolio.sqlite` from a development machine (install fresh on the server);
* `tools/tmp/` (test artefacts);
* anything containing local paths or secrets (`includes/config.local.php` is server-specific).

`tools/` is blocked from the web by `.htaccess`, but it is optional in production. You may
delete it after deployment.

---

## 3. Directory permissions

cPanel → File Manager → right-click → **Change Permissions**:

| Path | Permission | Notes |
|---|---|---|
| `storage/` | `0750` (writable by owner) | holds the database, logs, sessions |
| `storage/logs/`, `storage/sessions/` | `0750` | created automatically, must be writable |
| `assets/uploads/` | `0755` | public images, must be writable |
| `assets/uploads/projects/` | `0755` | created automatically |
| `storage/backups/` | `0700` | automatic pre-restore safety copies |
| `storage/restore/` | `0700` | temporary upload staging |
| PHP files | `0644` | never 0777 |

If uploads fail, confirm the PHP user owns/writes these directories. On cPanel the account
owner normally does.

---

## 4. Configure the application

Create `includes/config.local.php` from `includes/config.sample.php` and edit:

```php
<?php
return [
    'site_url'  => 'https://tijanidev.com.ng',
    'base_path' => '',            // '/portfolio' only if not at the domain root
    'clean_urls'=> true,
    'debug'     => false,

    'storage' => [
        // Recommended: outside public_html. Must exist and be writable.
        'path'     => '/home/YOUR_CPANEL_USER/portfolio-storage',
        'database' => 'portfolio.sqlite',
    ],

    'mail' => [
        // Fallback defaults only — after first sign-in use Admin → Email settings.
        'transport' => 'native',
        'to'        => 'tijaniapatira@gmail.com',
        'from'      => 'no-reply@tijanidev.com.ng',
        'from_name' => 'TijaniDev',
    ],
];
```

Notes:

* **Email addresses and the visitor confirmation are managed in Admin → Email settings**, not
  in this file. The `mail.*` values here are fallbacks used before the first save.
* If you move storage above `public_html`, create the folder first
  (`/home/YOUR_CPANEL_USER/portfolio-storage`). Keeping it inside `public_html/storage` also
  works — that folder is denied by `.htaccess` and by a PHP guard — but outside is safer.
* **Uploads always stay** in `assets/uploads/` because the images must be publicly served.
* `config.local.php` is ignored by Git. There are no secrets in this repository.

---

## 5. Initialise the SQLite database

1. Open `https://tijanidev.com.ng/install.php`.
2. Check the server checklist (all items should be green).
3. Keep the username `admin` and the temporary password `admin` — or choose another; you will
   be forced to change it on the first sign-in anyway.
4. Leave **“Add starter projects from my public GitHub repositories”** ticked if you want
   three real projects to start from (you can edit or delete them).
5. Submit. The installer creates the schema, categories, technologies, admin account,
   uploads folders and `storage/install.lock`.
6. **Delete `install.php`** from the server after success. (The installer refuses to run again
   while an admin account exists, but removing it is cleaner.)

Prefer the terminal? `php tools/install-cli.php` does the same.

---

## 6. Sign in and secure the account

1. Go to `https://tijanidev.com.ng/admin/login.php`.
2. Sign in with your installer credentials.
3. The dashboard forces **Change password** on first login:
   * at least 10 characters,
   * at least one letter and one number,
   * different from the temporary password.
4. After saving you land on the dashboard. The temporary password stops working.

Session rules: 30 minutes idle timeout, 12 hours absolute, login throttled to 5 failures per
15 minutes per username/IP. CSRF tokens protect every form.

---

## 7. Email notifications and confirmations

Everything the owner normally changes is in the dashboard: **Admin → Email settings**
(`/admin/email.php`). No code editing or redeploy is needed after a change.

1. **Notification email** — the inbox that should receive each contact submission.
2. **Sender name** and **sender email** — what recipients see. Use an address on your own
   domain (for example `no-reply@tijanidev.com.ng`), not a Gmail address, for deliverability.
3. **Visitor confirmation** — optional automatic receipt sent to the person who submitted the
   form. Edit the subject and message; placeholders such as `{name}`, `{subject}`, `{message}`,
   `{owner_name}`, `{site_name}` and `{site_url}` are replaced on send.
4. Click **Send test email** to check delivery to the notification address, then submit the
   public form once and confirm both copies arrive (your inbox and the visitor's).
5. Settings are stored in SQLite, so they are included in database backups and survive a
   restore.

Order of sending on every contact submission:

1. the owner notification is sent first;
2. only if the mail server accepts it, the visitor confirmation is sent;
3. either way the message is stored under **Messages** (with `Delivered` or `Mail failed`);
4. if the owner notification fails the visitor sees an honest error and the WhatsApp/email
   fallback — a confirmation is **never** sent claiming success;
5. if only the confirmation fails, the enquiry is still delivered and the visitor is told the
   confirmation could not be sent.

### Host requirements

The send method is PHP `mail()` through the host MTA (shown on the settings page and in
System). For it to work reliably:

* create the **From** mailbox/forwarder in cPanel → **Email Accounts**;
* enable **SPF** and **DKIM** in cPanel → **Email Deliverability** (without them Gmail may
  spam-folder notifications);
* make sure your host allows `mail()` (most cPanel accounts do).

`includes/config.local.php` still holds fallback values
(`mail.to`, `mail.from`, `mail.from_name`) used before the first dashboard save. Once you save
from **Email settings** the database wins. **Reset to defaults** on that page removes the
database overrides.

The transport itself (`native`, or the development-only `log`/`capture`) is configured in
`includes/config.local.php`; `native` is required for real delivery and the dashboard warns
when it is anything else.

---

## 8. Clean URLs

`.htaccess` maps:

| URL | Target |
|---|---|
| `/projects` | `projects.php` |
| `/project/github-notch` | `project.php?slug=github-notch` |
| `/sitemap.xml` | `sitemap.php` |
| `/admin` | `admin/` |

If `/projects` returns 404, either enable `mod_rewrite`/`AllowOverride` with your host, or set
`'clean_urls' => false` in `includes/config.local.php` (links then use `project.php?slug=…`).
In a **sub-directory** deployment, change `ErrorDocument 404 /404.php` in `.htaccess` and set
`base_path` accordingly.

---

## 9. First content pass (recommended)

1. **Projects → Edit** each starter project: title, slug, short description, full
   description, problem, solution, category, technologies, live/GitHub links, completion date.
2. Upload a **cover image** (JPG/PNG/WebP, ≤ 5 MB) and 2–4 **screenshots** on the media page.
3. Set **Published** and, for the best three, **Featured**.
4. **Categories** and **Technologies** are editable at any time; deleting a technology detaches
   it from projects, deleting a category in use is refused until its projects are moved.
5. **Email settings** — set your notification address, sender identity and the visitor
   confirmation, then send a test email.
6. **Messages** shows every contact submission, including failed deliveries.

Nothing on the public site is hardcoded: the home page, project index, detail pages and
sitemap all read the database.

---

## 10. Security checklist before launch

- [ ] `install.php` deleted.
- [ ] HTTPS enabled and forced (AutoSSL + `.htaccess` optional `RewriteCond` redirect).
- [ ] `includes/config.local.php` has `'debug' => false`.
- [ ] Storage outside `public_html` (recommended) or at least `0750` with `.htaccess` intact.
- [ ] `includes/.htaccess`, `storage/.htaccess`, `templates/.htaccess`,
      `admin/includes/.htaccess` all present (Admin → System shows this).
- [ ] Admin password changed and unique; no `admin/admin` login works.
- [ ] Contact form tested; SPF/DKIM configured; failure path verified.
- [ ] Backup downloaded once and a restore tested with a throwaway copy.
- [ ] Admin → System shows every environment row green.
- [ ] cPanel account backups enabled.

Optional extra hardening: password-protect `/admin/` with cPanel **Directory Privacy** in
addition to the app login, or restrict it to your IP with `.htaccess`.

---

## 11. Backups and restore

### Download a backup

Admin → **System & backup** → *Download database backup*. This checkpoints SQLite's WAL and
streams a `.sqlite` file named `portfolio-YYYYMMDD-HHMMSS.sqlite`.

**Full backup:** cPanel → **Backup** (or your host's nightly backup) covers files + database.
Include `assets/uploads/` — the SQLite file alone does not contain the images.

### Restore by uploading a backup (built-in)

Admin → **System & backup** → *Restore from backup* (direct URL: `/admin/restore.php`).

1. Tick the confirmation box and choose the `.sqlite` file downloaded from this site.
2. The server validates the upload **before touching anything**:
   * real SQLite magic bytes (the extension alone is not trusted);
   * `PRAGMA integrity_check` must return `ok`;
   * required tables, at least one usable admin account, no triggers;
   * `PRAGMA foreign_key_check` must be clean;
   * `app_meta.schema_version` must match the deployed version.
3. The live database is copied to `storage/backups/pre-restore-…sqlite` first.
4. The current file is moved aside, the upload is moved in, and the restored file is
   integrity-checked again. Any failure puts the previous database back automatically.
5. On success you are returned to **System** with the project count and the safety-copy name.
6. If the backup comes from a **different installation**, its admin account may not match your
   session. You are signed out and must use the admin credentials that belong to that backup.

**Images are not in the database.** Restore `assets/uploads/` separately (cPanel Backup or
File Manager) or project cards will show missing images.

**Safety copies** stay in `storage/backups` and are listed on the restore page with a download
link. The newest 10 are kept (`'backup' => ['keep' => 10]` in `includes/config.local.php`).

**Size limits:** the restore tool accepts up to 100 MB
(`backup.max_restore_bytes`), but PHP's own limits apply first. If a large upload is rejected
before validation, raise `upload_max_filesize` and `post_max_size` in cPanel → **MultiPHP INI
Editor** (or restore manually, below).

### Manual restore (fallback)

1. Download a copy of the current `storage/portfolio.sqlite`.
2. Sign out of the dashboard.
3. Delete `storage/portfolio.sqlite-wal` and `storage/portfolio.sqlite-shm` if present.
4. Upload the backup as `storage/portfolio.sqlite` (permissions `0600`).
5. Restore `assets/uploads/` alongside it so image paths resolve.
6. Sign in and open **Projects** to confirm the rows and images load.
7. Take a fresh backup from the dashboard once everything looks right.

---

## 12. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| HTTP 500 on every page | `.htaccess` directive not allowed, or permissions | Check cPanel **Errors**; comment out `<IfModule>`-less lines; set `debug` false and read `storage/logs/php-error.log` |
| Installer says `pdo_sqlite missing` | Extension disabled | Enable it in **Select PHP Version → Extensions** |
| “Storage directory not writable” | Wrong owner/permissions | `chmod 0750 storage` and ensure the account owns it; create the folder configured in `config.local.php` |
| Uploads fail | `fileinfo` disabled, folder not writable, PHP upload limit | Enable `fileinfo`; `chmod 0755 assets/uploads`; raise `upload_max_filesize`/`post_max_size` to ≥ 8M |
| Contact form: “saved but not delivered” | Host refuses `mail()`, missing From mailbox, SPF/DKIM | Set the addresses in **Admin → Email settings**, create the From mailbox, run **Send test email**, check **Messages** |
| Email settings won’t save | `storage` not writable or database read-only | Check **System** for writable storage; read `storage/logs/php-error.log` |
| Test email fails | `mail()` disabled or From address rejected | Confirm the From mailbox exists, ask the host to enable `mail()`, check SPF/DKIM |
| Visitor did not receive confirmation | Toggle off, mail server rejected it, or it landed in spam | Check **Email settings** and the warning on the thank-you page; inspect `storage/logs/mail.log` / ask them to check spam |
| Confirmation copy not updating | Old offer/backup restored the database settings | Re-save **Email settings**; settings travel with the database backup |
| `/projects` → 404 | `mod_rewrite` off | Enable rewrite or set `clean_urls` false |
| Admin redirects to login repeatedly | Cookies blocked / HTTPS mismatch / session path | Serve only one hostname (`www` vs non-`www`), allow cookies, ensure `storage/sessions` writable |
| “Too many sign-in attempts” | Throttling | Wait 15 minutes; failures are pruned automatically |
| CSS changes not visible | Browser cache | Hard refresh; static assets are cached one month by `.htaccess` |
| Images broken after move | Uploads moved/not copied | Restore `assets/uploads/` (paths are stored relative to it) |
| Restore: “not a SQLite database” | Wrong file (often a `.sql` dump or HTML page) | Use the `.sqlite` file from System & backup, not a phpMyAdmin export |
| Restore: “schema version … expects …” | Backup comes from another app version or another portfolio | Deploy the matching version, or restore the data manually after migration |
| Restore: “live database is locked” | Another request/process holds the SQLite file | Close other admin tabs, wait a few seconds, retry; avoid restoring during heavy traffic |
| Restore upload rejected as too large | PHP `upload_max_filesize`/`post_max_size` lower than the DB | Raise them in MultiPHP INI Editor, or use the manual file replacement steps |
| Restore succeeded but images are missing | Uploads are not part of the database | Restore `assets/uploads/` from the same date |
| Safety copy missing from the list | Retention (`backup.keep`) or manual deletion | Download backups to your own storage; adjust `backup.keep` |

Logs: `storage/logs/php-error.log`, `app.log`, `mail.log`, `exceptions.log`,
`security.log`, `uploads.log`, `contact.log`.

---

## 13. Updating the code later

1. Download a backup of the database and `assets/uploads/`.
2. Replace files, **not** `storage/` and **not** `assets/uploads/`.
3. Keep `includes/config.local.php` on the server.
4. If the schema changes in a future version, the installer/migration path will be documented
   in the release notes; do not delete the database.
5. Re-run `php tools/site-check.php https://tijanidev.com.ng` after a major change and click
   through: home → projects → a project → contact → admin login → download a backup.
