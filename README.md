# TijaniDev — personal developer portfolio

A PHP portfolio for **Apatira Tijani Adewale**, Software & Web Developer (Lagos, Nigeria),
upgraded from the original single-page template into a database-driven site with a private
project dashboard.

**Tagline:** Building Digital Solutions That Move Businesses Forward.

---

## What is in this build

| Area | Detail |
|---|---|
| Public site | Home, projects index, project detail pages, contact, thank-you, 404, XML sitemap, robots.txt |
| Admin | Dashboard, projects CRUD, publishing, featured flags, cover + multi-screenshot uploads, categories, technologies, contact messages, **email settings**, system checks, database download **and restore from an uploaded backup** |
| Database | SQLite (PDO, prepared statements, foreign keys, indexes) |
| Content | Projects, categories, technologies and screenshots are edited in the dashboard — no PHP edits needed |
| Email | Owner notification + optional visitor confirmation, configured entirely from **Admin → Email settings**; honest failure handling and a stored-message fallback |
| SEO | Per-page title/description/canonical, Open Graph/Twitter cards, JSON-LD, dynamic sitemap |

### Design continuity

The original site's identity is kept and extended, not replaced:

* near-black canvas (`#050807`), the same green accent (`#08d665`), Poppins, pill buttons,
  uppercase navigation labels;
* the template's dark hero is now a two-column hero with a code card, the same techy feel
  without a particle canvas or a 2-second preloader;
* Bootstrap, jQuery and the unused carousel/lightbox plugins are still in `assets/` but no
  longer loaded on the new pages — the public design system is a single 70 KB stylesheet and
  one 12 KB vanilla-JS file.

---

## Requirements

* PHP **8.0+** (tested on 8.3) with `pdo_sqlite`, `mbstring`, `fileinfo`, `session`, `json`;
* Apache with `mod_rewrite` for clean URLs (or set `clean_urls` to `false`);
* writable `storage/` and `assets/uploads/` directories;
* HTTPS in production.

---

## Project structure

```
index.php                 home page controller
projects.php              project index (filters + pagination)
project.php               project detail (?slug=… or /project/{slug})
contact.php               contact form endpoint (validation, spam checks, mail)
thank-you.php             confirmation page
404.php                   ErrorDocument + unknown routes
sitemap.php               dynamic XML sitemap (served at /sitemap.xml)
install.php               one-time web installer
robots.txt, ads.txt       crawler rules / ad verification (preserved)

admin/
  _init.php               admin bootstrap (session, noindex, layout)
  login.php, logout.php
  index.php               dashboard overview
  projects.php            project list + publish/feature/delete actions
  project-form.php        create/edit project
  project-media.php       screenshot upload, captions, deletion
  project-action.php      publish / unpublish / feature / unfeature
  project-delete.php      delete project + files
  categories.php          category management
  technologies.php        technology management
  messages.php            contact inbox
  message.php             message detail / delete
  email.php               email addresses, confirmation copy, test email
  change-password.php     forced + voluntary password change
  system.php              environment, data and security checks
  backup.php              authenticated SQLite download (live DB or safety copy)
  restore.php             upload + validate + restore a database backup
  includes/               admin layout + guards (denied to the web)

includes/
  config.php              safe defaults (no secrets)
  config.sample.php       copy to config.local.php for per-server settings
  bootstrap.php           error handling, sessions, headers, module loading
  db.php, schema.php      PDO connection + idempotent schema
  settings.php            dashboard-managed settings (email configuration)
  backup.php              backup, inspection, restore swap + rollback
  seed.php                starter categories, technologies, real GitHub projects
  auth.php                login, throttling, sessions, password change
  csrf.php                per-session CSRF token
  validation.php          all server-side validation
  uploads.php             MIME-verified image uploads, safe filenames, deletion
  mailer.php              native mail transport with honest failure reporting
  contact.php             honeypot + signed timing + rate limit pipeline
  repositories.php        every SQL query used by the app
  layout.php, icons.php   shared public layout + inline SVG set
  seo.php                 JSON-LD builders
  session.php             hardened sessions, security headers

templates/                public views (home, projects, project, thank-you, 404, error)
assets/css/premium.css    public design system
assets/css/admin.css      dashboard styles
assets/js/site.js         progressive enhancements (nav, reveal, lightbox, counters)
assets/js/admin.js        dashboard enhancements (slug, previews, confirms)
assets/uploads/           public images (script execution denied)
storage/                  SQLite DB, logs, sessions, secret key (denied to the web)
tools/                    development-only installer, router and test suites (denied)
```

---

## Local development

The project needs no build step and no Composer install.

```bash
# 1. start the development server (router emulates the production .htaccess)
php -S 127.0.0.1:8080 tools/dev-router.php

# 2. initialise a local database (creates storage/portfolio.sqlite)
php tools/install-cli.php

# 3. open the site
#    http://127.0.0.1:8080
#    http://127.0.0.1:8080/admin/login.php
```

Default first-run credentials are `admin` / `admin`; the dashboard forces a password change
immediately after sign-in.

### Tests

The test suites run against a live local server. Use a **separate** test database so your
preview content is untouched:

```bash
# terminal 1 — test server on 8081 with a throwaway DB and captured mail
PORTFOLIO_STORAGE="$PWD/tools/tmp/test-storage" \
PORTFOLIO_MAIL_TRANSPORT=capture \
php -S 127.0.0.1:8081 tools/dev-router.php

# terminal 2
PORTFOLIO_STORAGE="$PWD/tools/tmp/test-storage" PORTFOLIO_MAIL_TRANSPORT=capture \
php tools/install-cli.php
php tools/http-tests.php http://127.0.0.1:8081     # 200 functional/security assertions
php tools/site-check.php http://127.0.0.1:8080      # markup, SEO, links, CSS
```

`http-tests.php` covers every public page, the full project lifecycle (create → upload →
publish → feature → unpublish → edit → delete), authentication, forced password change,
login throttling, CSRF on every state-changing route, contact validation, honeypot/timing
spam traps, rate limiting, upload rejection of executable files, XSS escaping and cascade
deletion. It also changes the email configuration, sends a test email, verifies that the
owner notification and visitor confirmation are both addressed correctly, checks the
confirmation toggle, downloads a live database backup, uploads it back through the restore
tool, checks the automatic safety copy, path-traversal protection and rollback of rejected
files. The fixtures live in `tools/fixtures/`.

> `PORTFOLIO_MAIL_TRANSPORT=capture` simulates delivery into `storage/logs/captured/`.
> Production always uses `native`; the dashboard warns loudly if anything else is set.

---

## Seeded content

The installer can add three **real** projects from the owner's public GitHub repositories
(`Portfolio Website`, `Gift Card Storefront`, `Django Blog Platform`). Copy is deliberately
short and factual because the repository READMEs do not document features in detail. Review
them in the dashboard, add screenshots, write the problem/solution narrative and adjust the
stack — never publish claims that are not true.

---

## Security summary

* SQLite + PDO prepared statements; no string-built SQL with user input.
* Passwords stored with `password_hash()`; verified with `password_verify()`; rehashed on login.
* Session id regenerated on login/logout, token rotated, 30-minute idle timeout, 12-hour cap.
* Login throttling (5 attempts / 15 min per pair, 10 per IP) persisted in SQLite.
* CSRF token on every POST form, constant-time comparison, 419 response on failure.
* Server-side validation for every input; output escaped with `htmlspecialchars`.
* Uploads verified by `finfo` MIME + `getimagesize`, extension derived from MIME, random
  filenames, 5 MB limit, 32×32 px minimum, executable uploads rejected.
* Email settings are admin-only, CSRF-protected and validated; the visitor confirmation is
  only sent after the owner notification is accepted, and delivery failures are reported
  honestly instead of showing a false success.
* Database restore is admin-only, CSRF-protected, magic-byte checked, integrity-checked,
  schema-version checked, trigger-rejecting, preceded by an automatic safety copy and rolled
  back automatically if verification fails. Backup downloads are restricted to the backups
  directory and refuse path traversal.
* Private directories (`includes/`, `storage/`, `templates/`, `tools/`, `admin/includes/`)
  denied by `.htaccess`, by the PHP guards and by the dev router.
* Security headers sent from PHP and Apache; admin responses marked `noindex`.
* No secrets in Git: `includes/config.local.php`, the database, uploads and logs are ignored.

---

## Known limitations

* Mail uses PHP's `mail()`. Notification and sender addresses and the visitor confirmation are
  managed in **Admin → Email settings**; delivery still depends on the host's MTA and on
  SPF/DKIM records for the domain, and there is no SMTP client. Failed delivery is reported
  honestly and the message is stored in the inbox — see `DEPLOYMENT.md` for setup and
  troubleshooting.
* The environment that produced this build has no browser runtime, so responsiveness was
  verified by CSS breakpoint review and structural checks rather than screenshots. Open the
  site on a phone before launch if you want visual confirmation.
* The live domain currently serves an unrelated Botble "James.dev" demo theme, not this
  repository. Deploying this project to `public_html` replaces it; see `DEPLOYMENT.md`.
* Database backups contain projects, text, taxonomies and messages — **not** the image files
  in `assets/uploads/`. Back those up separately.

---

## Deployment

See **[DEPLOYMENT.md](DEPLOYMENT.md)** for the cPanel walkthrough: PHP version and extensions,
upload steps, permissions, storage paths, installer, admin access, password change, mail
configuration, clean URLs, backups and troubleshooting.
