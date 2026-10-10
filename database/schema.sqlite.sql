-- ===========================================================================
-- Athenaeum (雅典学院) — SQLite schema
-- Used for local verification when no MySQL server is reachable.
-- Semantically identical to database/schema.mysql.sql.
-- ===========================================================================

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS {prefix}users (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    uid               TEXT NOT NULL UNIQUE,
    email             TEXT NOT NULL UNIQUE,
    password_hash     TEXT NOT NULL,
    nickname          TEXT NOT NULL,
    display_name      TEXT,
    affiliation       TEXT,
    avatar_path       TEXT,
    bio               TEXT,
    homepage_md       TEXT,
    locale            TEXT,
    role              TEXT NOT NULL DEFAULT 'user',
    status            TEXT NOT NULL DEFAULT 'active',
    email_verified_at TEXT,
    admin_note        TEXT,
    created_at        TEXT NOT NULL,
    updated_at        TEXT,
    last_login_at     TEXT,
    last_login_ip     TEXT
);
CREATE INDEX IF NOT EXISTS idx_users_status ON {prefix}users (status);
CREATE INDEX IF NOT EXISTS idx_users_role ON {prefix}users (role);

CREATE TABLE IF NOT EXISTS {prefix}user_links (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL,
    platform   TEXT NOT NULL,
    label      TEXT,
    url        TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_user_links_user ON {prefix}user_links (user_id);

CREATE TABLE IF NOT EXISTS {prefix}sections (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    slug         TEXT NOT NULL UNIQUE,
    names        TEXT NOT NULL,
    descriptions TEXT,
    sort_order   INTEGER NOT NULL DEFAULT 0,
    is_default   INTEGER NOT NULL DEFAULT 0,
    is_public    INTEGER NOT NULL DEFAULT 1,
    created_at   TEXT NOT NULL,
    updated_at   TEXT
);

CREATE TABLE IF NOT EXISTS {prefix}categories (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    slug       TEXT NOT NULL UNIQUE,
    names      TEXT NOT NULL,
    parent_id  INTEGER,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT
);

CREATE TABLE IF NOT EXISTS {prefix}papers (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    uid               TEXT NOT NULL UNIQUE,
    slug              TEXT NOT NULL,
    title             TEXT NOT NULL,
    subtitle          TEXT,
    abstract          TEXT NOT NULL,
    language          TEXT NOT NULL DEFAULT 'en',
    section_id        INTEGER,
    category_id       INTEGER,
    keywords          TEXT,
    license           TEXT,
    doi               TEXT,
    uploader_id       INTEGER NOT NULL,
    proxy_uploader_id INTEGER,
    status            TEXT NOT NULL DEFAULT 'pending',
    visibility        TEXT NOT NULL DEFAULT 'public',
    reject_reason     TEXT,
    review_note       TEXT,
    reviewed_by       INTEGER,
    reviewed_at       TEXT,
    published_at      TEXT,
    withdrawn_at      TEXT,
    takedown_reason   TEXT,
    pdf_path          TEXT,
    pdf_name          TEXT,
    pdf_size          INTEGER NOT NULL DEFAULT 0,
    pdf_sha256        TEXT,
    pdf_pages         INTEGER,
    size_exempt       INTEGER NOT NULL DEFAULT 0,
    size_exempt_note  TEXT,
    downloads         INTEGER NOT NULL DEFAULT 0,
    views             INTEGER NOT NULL DEFAULT 0,
    is_featured       INTEGER NOT NULL DEFAULT 0,
    submitted_at      TEXT,
    created_at        TEXT NOT NULL,
    updated_at        TEXT,
    version_no        INTEGER NOT NULL DEFAULT 1,
    language_custom   TEXT,
    category_other    TEXT,
    ai_status         TEXT NOT NULL DEFAULT 'none',
    ai_decision       TEXT,
    ai_confidence     INTEGER,
    ai_reason         TEXT,
    ai_model          TEXT,
    ai_reviewed_at    TEXT,
    ai_payload        TEXT,
    origin            TEXT NOT NULL DEFAULT 'submission',
    origin_source     TEXT,
    origin_published_at TEXT,
    copyright_expired_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_papers_status ON {prefix}papers (status);
CREATE INDEX IF NOT EXISTS idx_papers_section ON {prefix}papers (section_id);
CREATE INDEX IF NOT EXISTS idx_papers_category ON {prefix}papers (category_id);
CREATE INDEX IF NOT EXISTS idx_papers_uploader ON {prefix}papers (uploader_id);
CREATE INDEX IF NOT EXISTS idx_papers_created ON {prefix}papers (created_at);

CREATE TABLE IF NOT EXISTS {prefix}paper_authors (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    paper_id         INTEGER NOT NULL,
    position         INTEGER NOT NULL DEFAULT 0,
    name             TEXT NOT NULL,
    affiliation      TEXT,
    email            TEXT,
    orcid            TEXT,
    user_id          INTEGER,
    is_corresponding INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_paper_authors_paper ON {prefix}paper_authors (paper_id);

CREATE TABLE IF NOT EXISTS {prefix}attachments (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    paper_id      INTEGER NOT NULL,
    kind          TEXT NOT NULL DEFAULT 'archive',
    original_name TEXT NOT NULL,
    stored_name   TEXT NOT NULL,
    path          TEXT NOT NULL,
    size          INTEGER NOT NULL DEFAULT 0,
    mime          TEXT,
    sha256        TEXT,
    downloads     INTEGER NOT NULL DEFAULT 0,
    size_exempt   INTEGER NOT NULL DEFAULT 0,
    uploaded_by   INTEGER,
    created_at    TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_attachments_paper ON {prefix}attachments (paper_id);

CREATE TABLE IF NOT EXISTS {prefix}paper_links (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    paper_id   INTEGER NOT NULL,
    kind       TEXT NOT NULL DEFAULT 'other',
    label      TEXT NOT NULL,
    url        TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_paper_links_paper ON {prefix}paper_links (paper_id);

CREATE TABLE IF NOT EXISTS {prefix}timestamps (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    paper_id        INTEGER NOT NULL,
    -- Page proofs (target_type = 'page') keep paper_id = 0 and point here
    -- instead; a page proof is about the page's own content, not a paper.
    page_id         INTEGER,
    target_type     TEXT NOT NULL DEFAULT 'pdf',
    attachment_id   INTEGER,
    file_name       TEXT,
    file_sha256     TEXT NOT NULL,
    algo            TEXT NOT NULL DEFAULT 'sha256',
    status          TEXT NOT NULL DEFAULT 'pending',
    ots_path        TEXT,
    ots_name        TEXT,
    calendars       TEXT,
    calendar_count  INTEGER NOT NULL DEFAULT 0,
    submitted_at    TEXT,
    upgraded_at     TEXT,
    bitcoin_height  INTEGER,
    bitcoin_time    TEXT,
    attempts        INTEGER NOT NULL DEFAULT 0,
    last_attempt_at TEXT,
    last_error      TEXT,
    created_at      TEXT NOT NULL,
    updated_at      TEXT
);
CREATE INDEX IF NOT EXISTS idx_timestamps_paper ON {prefix}timestamps (paper_id);
CREATE INDEX IF NOT EXISTS idx_timestamps_page ON {prefix}timestamps (page_id);
CREATE INDEX IF NOT EXISTS idx_timestamps_status ON {prefix}timestamps (status);
CREATE INDEX IF NOT EXISTS idx_timestamps_hash ON {prefix}timestamps (file_sha256);

CREATE TABLE IF NOT EXISTS {prefix}settings (
    setting_key   TEXT PRIMARY KEY,
    setting_value TEXT,
    updated_at    TEXT
);

CREATE TABLE IF NOT EXISTS {prefix}audit_logs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_id    INTEGER,
    actor_uid   TEXT,
    action      TEXT NOT NULL,
    target_type TEXT,
    target_id   TEXT,
    meta        TEXT,
    ip          TEXT,
    created_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_audit_actor ON {prefix}audit_logs (actor_id);
CREATE INDEX IF NOT EXISTS idx_audit_created ON {prefix}audit_logs (created_at);

CREATE TABLE IF NOT EXISTS {prefix}login_attempts (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    ip         TEXT NOT NULL,
    email      TEXT NOT NULL,
    success    INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_attempts_lookup ON {prefix}login_attempts (ip, email, created_at);

CREATE TABLE IF NOT EXISTS {prefix}paper_versions (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    paper_id      INTEGER NOT NULL,
    version_no    INTEGER NOT NULL DEFAULT 1,
    label         TEXT,
    note          TEXT,
    pdf_path      TEXT NOT NULL,
    pdf_name      TEXT,
    pdf_size      INTEGER NOT NULL DEFAULT 0,
    pdf_sha256    TEXT,
    uploaded_by   INTEGER,
    size_exempt   INTEGER NOT NULL DEFAULT 0,
    created_at    TEXT NOT NULL,
    published_at  TEXT,
    UNIQUE (paper_id, version_no)
);

CREATE TABLE IF NOT EXISTS {prefix}pages (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    slug        TEXT NOT NULL UNIQUE,
    titles      TEXT,
    contents    TEXT,
    is_system   INTEGER NOT NULL DEFAULT 0,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    updated_by  INTEGER,
    created_at  TEXT NOT NULL,
    updated_at  TEXT
);

CREATE TABLE IF NOT EXISTS {prefix}email_verifications (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    email       TEXT NOT NULL,
    code_hash   TEXT NOT NULL,
    purpose     TEXT NOT NULL DEFAULT 'register',
    attempts    INTEGER NOT NULL DEFAULT 0,
    expires_at  TEXT NOT NULL,
    created_at  TEXT NOT NULL,
    updated_at  TEXT,
    ip          TEXT
);
