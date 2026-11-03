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
        'transport' => 'native',
        'to'        => 'tijaniapatira@gmail.com',
        'from'      => 'no-reply@tijanidev.com.ng',
        'from_name' => 'Tijani Dev Website',
    ],
];
```

Notes:

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

## 7. Contact-form email delivery

The form uses PHP `mail()` through the host's MTA.

1. In cPanel → **Email Accounts**, create `no-reply@tijanidev.com.ng` (or a forwarder).
2. In `includes/config.local.php`, set `mail.from` to that address and `mail.to` to your
   inbox.
3. Make sure the domain has **SPF** (and ideally **DKIM**) records in cPanel → **Email
   Deliverability**. Without them Gmail may file the notification as spam.
4. Send a test message from the public contact form.
5. Check **Admin → Messages**:
   * `Delivered` = the host accepted the message;
   * `Mail failed` = it was **not** emailed. The message is still stored so you can reply
     manually. The form told the visitor the truth and pointed them at WhatsApp/email.

The default transport is `native`. The dashboard shows a warning whenever it is not `native`
(`log` and `capture` are development aids and never deliver mail).

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
5. **Messages** shows every contact submission, including failed deliveries.

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
- [ ] Admin → System shows every environment row green.
- [ ] cPanel account backups enabled.

Optional extra hardening: password-protect `/admin/` with cPanel **Directory Privacy** in
addition to the app login, or restrict it to your IP with `.htaccess`.

---

## 11. Backups and restore

**Download the database:** Admin → **System & backup** → *Download database backup*. This
checkpoints SQLite's WAL and streams a `.sqlite` file.

**Full backup:** cPanel → **Backup** (or your host's nightly backup) covers files + database.
Include `assets/uploads/` — the SQLite file alone does not contain the images.

**Restore:**

1. Put the site in maintenance or close the admin session.
2. Delete `storage/portfolio.sqlite-wal` and `storage/portfolio.sqlite-shm` if present.
3. Replace `storage/portfolio.sqlite` with the backup (permissions `0600`).
4. Restore `assets/uploads/` alongside it so image paths resolve.
5. Sign in and open **Projects** to confirm the rows and images load.

---

## 12. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| HTTP 500 on every page | `.htaccess` directive not allowed, or permissions | Check cPanel **Errors**; comment out `<IfModule>`-less lines; set `debug` false and read `storage/logs/php-error.log` |
| Installer says `pdo_sqlite missing` | Extension disabled | Enable it in **Select PHP Version → Extensions** |
| “Storage directory not writable” | Wrong owner/permissions | `chmod 0750 storage` and ensure the account owns it; create the folder configured in `config.local.php` |
| Uploads fail | `fileinfo` disabled, folder not writable, PHP upload limit | Enable `fileinfo`; `chmod 0755 assets/uploads`; raise `upload_max_filesize`/`post_max_size` to ≥ 8M |
| Contact form: “saved but not delivered” | Host refuses `mail()`, missing From mailbox, SPF/DKIM | Create the mailbox, set `mail.from`, check **Messages**, test again |
| `/projects` → 404 | `mod_rewrite` off | Enable rewrite or set `clean_urls` false |
| Admin redirects to login repeatedly | Cookies blocked / HTTPS mismatch / session path | Serve only one hostname (`www` vs non-`www`), allow cookies, ensure `storage/sessions` writable |
| “Too many sign-in attempts” | Throttling | Wait 15 minutes; failures are pruned automatically |
| CSS changes not visible | Browser cache | Hard refresh; static assets are cached one month by `.htaccess` |
| Images broken after move | Uploads moved/not copied | Restore `assets/uploads/` (paths are stored relative to it) |

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
   through: home → projects → a project → contact → admin login.
