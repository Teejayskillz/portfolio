<?php
/**
 * SQLite schema.
 *
 * Every statement is idempotent so install.php (and the CLI installer) can be
 * re-run safely. Foreign keys are enabled per connection in Database.
 */

declare(strict_types=1);

return [
    // Schema/installation bookkeeping -------------------------------------
    "CREATE TABLE IF NOT EXISTS app_meta (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT ''
    )",

    // Dashboard-managed settings (email configuration, etc.) -------------
    "CREATE TABLE IF NOT EXISTS app_settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT '',
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )",

    // Admin accounts ------------------------------------------------------
    "CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        display_name TEXT NOT NULL DEFAULT 'Administrator',
        must_change_password INTEGER NOT NULL DEFAULT 1 CHECK (must_change_password IN (0, 1)),
        last_login_at TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )",

    // Taxonomy ------------------------------------------------------------
    "CREATE TABLE IF NOT EXISTS project_categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        slug TEXT NOT NULL UNIQUE,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS technologies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        slug TEXT NOT NULL UNIQUE,
        group_name TEXT NOT NULL DEFAULT 'Other',
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )",

    // Projects ------------------------------------------------------------
    "CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        short_description TEXT NOT NULL DEFAULT '',
        description TEXT NOT NULL DEFAULT '',
        problem TEXT NOT NULL DEFAULT '',
        solution TEXT NOT NULL DEFAULT '',
        category_id INTEGER REFERENCES project_categories(id) ON DELETE SET NULL,
        cover_image TEXT,
        live_url TEXT,
        github_url TEXT,
        completion_date TEXT,
        is_featured INTEGER NOT NULL DEFAULT 0 CHECK (is_featured IN (0, 1)),
        status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published')),
        seo_title TEXT,
        seo_description TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        published_at TEXT
    )",

    "CREATE TABLE IF NOT EXISTS project_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        file_path TEXT NOT NULL,
        alt_text TEXT NOT NULL DEFAULT '',
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS project_technologies (
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        technology_id INTEGER NOT NULL REFERENCES technologies(id) ON DELETE CASCADE,
        PRIMARY KEY (project_id, technology_id)
    )",

    // Security + operations ----------------------------------------------
    "CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        ip_address TEXT NOT NULL,
        succeeded INTEGER NOT NULL DEFAULT 0,
        attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS contact_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        subject TEXT NOT NULL,
        message TEXT NOT NULL,
        ip_address TEXT NOT NULL DEFAULT '',
        user_agent TEXT NOT NULL DEFAULT '',
        mail_status TEXT NOT NULL DEFAULT 'pending' CHECK (mail_status IN ('sent', 'failed', 'spam', 'pending')),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS activity_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        actor_id INTEGER,
        action TEXT NOT NULL,
        entity_type TEXT NOT NULL DEFAULT '',
        entity_id INTEGER,
        description TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )",

    // Indexes -------------------------------------------------------------
    "CREATE INDEX IF NOT EXISTS projects_status_idx ON projects (status, published_at DESC)",
    "CREATE INDEX IF NOT EXISTS projects_featured_idx ON projects (is_featured, status)",
    "CREATE INDEX IF NOT EXISTS projects_category_idx ON projects (category_id)",
    "CREATE INDEX IF NOT EXISTS project_images_project_idx ON project_images (project_id, sort_order)",
    "CREATE INDEX IF NOT EXISTS project_technologies_tech_idx ON project_technologies (technology_id)",
    "CREATE INDEX IF NOT EXISTS login_attempts_lookup_idx ON login_attempts (username, ip_address, attempted_at)",
    "CREATE INDEX IF NOT EXISTS login_attempts_ip_idx ON login_attempts (ip_address, attempted_at)",
    "CREATE INDEX IF NOT EXISTS contact_messages_created_idx ON contact_messages (created_at)",
    "CREATE INDEX IF NOT EXISTS contact_messages_ip_idx ON contact_messages (ip_address, created_at)",
    "CREATE INDEX IF NOT EXISTS activity_log_created_idx ON activity_log (created_at DESC)",
];
