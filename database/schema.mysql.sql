-- ===========================================================================
-- Athenaeum (雅典学院) — MySQL schema
-- Charset: utf8mb4 (full Unicode, including CJK and emoji in abstracts)
-- The installer replaces {prefix} with the configured table prefix.
-- ===========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Accounts
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {prefix}users (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uid               VARCHAR(16)     NOT NULL,
    email             VARCHAR(190)    NOT NULL,
    password_hash     VARCHAR(255)    NOT NULL,
    nickname          VARCHAR(80)     NOT NULL,
    display_name      VARCHAR(120)    NULL,
    affiliation       VARCHAR(190)    NULL,
    avatar_path       VARCHAR(255)    NULL,
    bio               VARCHAR(500)    NULL,
    homepage_md       MEDIUMTEXT      NULL,
    locale            VARCHAR(10)     NULL,
    role              VARCHAR(16)     NOT NULL DEFAULT 'user',
    status            VARCHAR(16)     NOT NULL DEFAULT 'active',
    email_verified_at DATETIME        NULL,
    admin_note        VARCHAR(500)    NULL,
    created_at        DATETIME        NOT NULL,
    updated_at        DATETIME        NULL,
    last_login_at     DATETIME        NULL,
    last_login_ip     VARCHAR(45)     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_users_uid (uid),
    UNIQUE KEY uniq_users_email (email),
    KEY idx_users_status (status),
    KEY idx_users_role (role),
    KEY idx_users_nickname (nickname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {prefix}user_links (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    platform   VARCHAR(40)     NOT NULL,
    label      VARCHAR(120)    NULL,
    url        VARCHAR(500)    NOT NULL,
    sort_order INT             NOT NULL DEFAULT 0,
    created_at DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY idx_user_links_user (user_id),
    KEY idx_user_links_platform (platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Taxonomy: sections (一区/二区/三区/预印本 …) and categories
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {prefix}sections (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug         VARCHAR(80)     NOT NULL,
    names        TEXT            NOT NULL,
    descriptions TEXT            NULL,
    sort_order   INT             NOT NULL DEFAULT 0,
    is_default   TINYINT(1)      NOT NULL DEFAULT 0,
    is_public    TINYINT(1)      NOT NULL DEFAULT 1,
    created_at   DATETIME        NOT NULL,
    updated_at   DATETIME        NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_sections_slug (slug),
    KEY idx_sections_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {prefix}categories (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug       VARCHAR(80)     NOT NULL,
    names      TEXT            NOT NULL,
    parent_id  BIGINT UNSIGNED NULL,
    sort_order INT             NOT NULL DEFAULT 0,
    created_at DATETIME        NOT NULL,
    updated_at DATETIME        NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_categories_slug (slug),
    KEY idx_categories_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Papers
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {prefix}papers (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uid               VARCHAR(20)     NOT NULL,
    slug              VARCHAR(190)    NOT NULL,
    title             VARCHAR(300)    NOT NULL,
    subtitle          VARCHAR(300)    NULL,
    abstract          MEDIUMTEXT      NOT NULL,
    language          VARCHAR(10)     NOT NULL DEFAULT 'en',
    section_id        BIGINT UNSIGNED NULL,
    category_id       BIGINT UNSIGNED NULL,
    keywords          VARCHAR(500)    NULL,
    license           VARCHAR(80)     NULL,
    doi               VARCHAR(190)    NULL,
    uploader_id       BIGINT UNSIGNED NOT NULL,
    proxy_uploader_id BIGINT UNSIGNED NULL,
    status            VARCHAR(20)     NOT NULL DEFAULT 'pending',
    visibility        VARCHAR(20)     NOT NULL DEFAULT 'public',
    reject_reason     VARCHAR(1000)   NULL,
    review_note       VARCHAR(1000)   NULL,
    reviewed_by       BIGINT UNSIGNED NULL,
    reviewed_at       DATETIME        NULL,
    published_at      DATETIME        NULL,
    withdrawn_at      DATETIME        NULL,
    takedown_reason   VARCHAR(1000)   NULL,
    pdf_path          VARCHAR(255)    NULL,
    pdf_name          VARCHAR(255)    NULL,
    pdf_size          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    pdf_sha256        CHAR(64)        NULL,
    pdf_pages         INT             NULL,
    size_exempt       TINYINT(1)      NOT NULL DEFAULT 0,
    size_exempt_note  VARCHAR(500)    NULL,
    downloads         INT UNSIGNED    NOT NULL DEFAULT 0,
    views             INT UNSIGNED    NOT NULL DEFAULT 0,
    is_featured       TINYINT(1)      NOT NULL DEFAULT 0,
    submitted_at      DATETIME        NULL,
    created_at        DATETIME        NOT NULL,
    updated_at        DATETIME        NULL,
    version_no        INT             NOT NULL DEFAULT 1,
    language_custom   VARCHAR(80)     NULL,
    category_other    VARCHAR(190)    NULL,
    ai_status         VARCHAR(20)     NOT NULL DEFAULT 'none',
    ai_decision       VARCHAR(20)     NULL,
    ai_confidence     INT             NULL,
    ai_reason         TEXT            NULL,
    ai_model          VARCHAR(80)     NULL,
    ai_reviewed_at    DATETIME        NULL,
    ai_payload        MEDIUMTEXT      NULL,
    origin            VARCHAR(20)     NOT NULL DEFAULT 'submission',
    origin_source     VARCHAR(80)     NULL,
    origin_published_at DATE          NULL,
    copyright_expired_at DATE         NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_papers_uid (uid),
    KEY idx_papers_status (status),
    KEY idx_papers_section (section_id),
    KEY idx_papers_category (category_id),
    KEY idx_papers_uploader (uploader_id),
    KEY idx_papers_created (created_at),
    KEY idx_papers_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {prefix}paper_authors (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    paper_id         BIGINT UNSIGNED NOT NULL,
    position         INT             NOT NULL DEFAULT 0,
    name             VARCHAR(190)    NOT NULL,
    affiliation      VARCHAR(190)    NULL,
    email            VARCHAR(190)    NULL,
    orcid            VARCHAR(32)     NULL,
    user_id          BIGINT UNSIGNED NULL,
    is_corresponding TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_paper_authors_paper (paper_id),
    KEY idx_paper_authors_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {prefix}attachments (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    paper_id      BIGINT UNSIGNED NOT NULL,
    kind          VARCHAR(20)     NOT NULL DEFAULT 'archive',
    original_name VARCHAR(255)    NOT NULL,
    stored_name   VARCHAR(255)    NOT NULL,
    path          VARCHAR(255)    NOT NULL,
    size          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    mime          VARCHAR(120)    NULL,
    sha256        CHAR(64)        NULL,
    downloads     INT UNSIGNED    NOT NULL DEFAULT 0,
    size_exempt   TINYINT(1)      NOT NULL DEFAULT 0,
    uploaded_by   BIGINT UNSIGNED NULL,
    created_at    DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY idx_attachments_paper (paper_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {prefix}paper_links (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    paper_id   BIGINT UNSIGNED NOT NULL,
    kind       VARCHAR(20)     NOT NULL DEFAULT 'other',
    label      VARCHAR(120)    NOT NULL,
    url        VARCHAR(500)    NOT NULL,
    sort_order INT             NOT NULL DEFAULT 0,
    created_at DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY idx_paper_links_paper (paper_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- OpenTimestamps proofs (one row per stamped file)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {prefix}timestamps (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    paper_id        BIGINT UNSIGNED NOT NULL,
    -- Page proofs (target_type = 'page') keep paper_id = 0 and point here
    -- instead; a page proof is about the page's own content, not a paper.
    page_id         BIGINT UNSIGNED NULL,
    target_type     VARCHAR(20)     NOT NULL DEFAULT 'pdf',
    attachment_id   BIGINT UNSIGNED NULL,
    file_name       VARCHAR(255)    NULL,
    file_sha256     CHAR(64)        NOT NULL,
    algo            VARCHAR(16)     NOT NULL DEFAULT 'sha256',
    status          VARCHAR(20)     NOT NULL DEFAULT 'pending',
    ots_path        VARCHAR(255)    NULL,
    ots_name        VARCHAR(255)    NULL,
    calendars       TEXT            NULL,
    calendar_count  INT             NOT NULL DEFAULT 0,
    submitted_at    DATETIME        NULL,
    upgraded_at     DATETIME        NULL,
    bitcoin_height  INT             NULL,
    bitcoin_time    DATETIME        NULL,
    attempts        INT             NOT NULL DEFAULT 0,
    last_attempt_at DATETIME        NULL,
    last_error      VARCHAR(500)    NULL,
    created_at      DATETIME        NOT NULL,
    updated_at      DATETIME        NULL,
    PRIMARY KEY (id),
    KEY idx_timestamps_paper (paper_id),
    KEY idx_timestamps_page (page_id),
    KEY idx_timestamps_status (status),
    KEY idx_timestamps_hash (file_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Platform tables
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {prefix}settings (
    setting_key   VARCHAR(64)  NOT NULL,
    setting_value MEDIUMTEXT   NULL,
    updated_at    DATETIME     NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {prefix}audit_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id    BIGINT UNSIGNED NULL,
    actor_uid   VARCHAR(20)     NULL,
    action      VARCHAR(64)     NOT NULL,
    target_type VARCHAR(32)     NULL,
    target_id   VARCHAR(64)     NULL,
    meta        TEXT            NULL,
    ip          VARCHAR(45)     NULL,
    created_at  DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_actor (actor_id),
    KEY idx_audit_action (action),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {prefix}login_attempts (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip         VARCHAR(45)     NOT NULL,
    email      VARCHAR(190)    NOT NULL,
    success    TINYINT(1)      NOT NULL DEFAULT 0,
    created_at DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY idx_attempts_lookup (ip, email, created_at),
    KEY idx_attempts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Revision history (v2). One row per uploaded PDF; the old file and its proof
-- are kept forever.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {prefix}paper_versions (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    paper_id      BIGINT UNSIGNED NOT NULL,
    version_no    INT             NOT NULL DEFAULT 1,
    label         VARCHAR(40)     NULL,
    note          TEXT            NULL,
    pdf_path      VARCHAR(255)    NOT NULL,
    pdf_name      VARCHAR(255)    NULL,
    pdf_size      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    pdf_sha256    CHAR(64)        NULL,
    uploaded_by   BIGINT UNSIGNED NULL,
    size_exempt   TINYINT(1)      NOT NULL DEFAULT 0,
    created_at    DATETIME        NOT NULL,
    published_at  DATETIME        NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_paper_version (paper_id, version_no),
    KEY idx_paper_versions_paper (paper_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Admin-editable content pages (关于本站 / 投稿指南 / 关于 AthenXiv / 时间戳存证)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {prefix}pages (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(80)     NOT NULL,
    titles      TEXT            NULL,
    contents    MEDIUMTEXT      NULL,
    is_system   TINYINT(1)      NOT NULL DEFAULT 0,
    sort_order  INT             NOT NULL DEFAULT 0,
    updated_by  BIGINT UNSIGNED NULL,
    created_at  DATETIME        NOT NULL,
    updated_at  DATETIME        NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- One-time e-mail codes (registration verification, later password reset).
-- Only an HMAC of the code is stored.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS {prefix}email_verifications (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email       VARCHAR(190)    NOT NULL,
    code_hash   VARCHAR(120)    NOT NULL,
    purpose     VARCHAR(20)     NOT NULL DEFAULT 'register',
    attempts    INT             NOT NULL DEFAULT 0,
    expires_at  DATETIME        NOT NULL,
    created_at  DATETIME        NOT NULL,
    updated_at  DATETIME        NULL,
    ip          VARCHAR(64)     NULL,
    PRIMARY KEY (id),
    KEY idx_email_codes (email, purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
