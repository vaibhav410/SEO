-- SYSCOM GrowthHub database schema
-- MySQL 5.7+/8.0 or MariaDB 10.4+, InnoDB, utf8mb4.
-- Import via phpMyAdmin (Import tab) or: mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS syscom_growthhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE syscom_growthhub;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_log, opportunity_states, distribution_posts, login_attempts, seo_audits, internal_links,
    backlinks, leads, faqs, landing_pages, keywords, posts, categories, services, settings, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Admin accounts
-- ---------------------------------------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin', 'editor') NOT NULL DEFAULT 'editor',
    last_login_at DATETIME NULL,
    notifications_seen_at DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_hash      CHAR(64) NOT NULL,
    email        VARCHAR(190) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_ip_time (ip_hash, attempted_at),
    KEY idx_login_email_time (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Services offered by SYSCOM (/services/{slug})
-- ---------------------------------------------------------------------------
CREATE TABLE services (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(150) NOT NULL,
    slug             VARCHAR(120) NOT NULL,
    icon             VARCHAR(40)  NOT NULL DEFAULT 'server',
    description      VARCHAR(300) NOT NULL,
    content          MEDIUMTEXT   NOT NULL,
    features         TEXT         NULL COMMENT 'One feature per line',
    primary_keyword  VARCHAR(150) NULL,
    external_url     VARCHAR(255) NULL COMMENT 'Matching product page on syscom.co.in',
    benefits         TEXT         NULL COMMENT 'One benefit per line',
    cta_text         VARCHAR(150) NOT NULL DEFAULT 'Talk to our team',
    meta_title       VARCHAR(70)  NULL,
    meta_description VARCHAR(170) NULL,
    canonical_url    VARCHAR(255) NULL COMMENT 'Override only when another URL is the canonical version',
    og_title         VARCHAR(100) NULL,
    og_description   VARCHAR(200) NULL,
    sort_order       SMALLINT     NOT NULL DEFAULT 0,
    status           ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_services_slug (slug),
    KEY idx_services_status_order (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Blog categories (/blog/category/{slug})
-- ---------------------------------------------------------------------------
CREATE TABLE categories (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(80)  NOT NULL,
    slug             VARCHAR(80)  NOT NULL,
    description      VARCHAR(300) NOT NULL DEFAULT '',
    meta_description VARCHAR(170) NULL,
    sort_order       SMALLINT NOT NULL DEFAULT 0,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Blog articles (/blog/{slug})
-- ---------------------------------------------------------------------------
CREATE TABLE posts (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title              VARCHAR(200) NOT NULL,
    slug               VARCHAR(120) NOT NULL,
    excerpt            VARCHAR(300) NOT NULL DEFAULT '',
    content            MEDIUMTEXT   NOT NULL,
    primary_keyword    VARCHAR(150) NULL,
    meta_title         VARCHAR(70)  NULL,
    meta_description   VARCHAR(170) NULL,
    featured_image     VARCHAR(255) NULL,
    featured_image_alt VARCHAR(200) NULL,
    canonical_url      VARCHAR(255) NULL,
    og_title           VARCHAR(100) NULL,
    og_description     VARCHAR(200) NULL,
    schema_type        ENUM('Article', 'BlogPosting', 'TechArticle') NOT NULL DEFAULT 'Article',
    category_id        INT UNSIGNED NULL,
    service_id         INT UNSIGNED NULL COMMENT 'Related service for cross-linking',
    author_id          INT UNSIGNED NULL,
    status             ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    published_at       DATETIME NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_posts_slug (slug),
    KEY idx_posts_status_published (status, published_at),
    KEY idx_posts_service (service_id),
    KEY idx_posts_category (category_id),
    FULLTEXT KEY ft_posts_search (title, excerpt, content),
    CONSTRAINT fk_posts_author  FOREIGN KEY (author_id)  REFERENCES users (id)    ON DELETE SET NULL,
    CONSTRAINT fk_posts_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL,
    CONSTRAINT fk_posts_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- SEO landing pages (/{slug}) built from a fixed section template
-- ---------------------------------------------------------------------------
CREATE TABLE landing_pages (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(200) NOT NULL COMMENT 'Visible H1',
    slug             VARCHAR(120) NOT NULL,
    primary_keyword  VARCHAR(150) NOT NULL,
    meta_title       VARCHAR(70)  NULL,
    meta_description VARCHAR(170) NULL,
    hero_subtitle    VARCHAR(300) NOT NULL DEFAULT '',
    problem          TEXT NULL,
    solution         TEXT NULL,
    features         TEXT NULL COMMENT 'One per line: Title: description',
    benefits         TEXT NULL COMMENT 'One per line',
    use_cases        TEXT NULL COMMENT 'One per line: Title: description',
    content          MEDIUMTEXT NULL COMMENT 'Optional long-form Markdown section',
    cta_text         VARCHAR(150) NOT NULL DEFAULT 'Talk to our team',
    canonical_url    VARCHAR(255) NULL,
    og_title         VARCHAR(100) NULL,
    og_description   VARCHAR(200) NULL,
    service_id       INT UNSIGNED NULL,
    status           ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_landing_slug (slug),
    KEY idx_landing_status (status),
    CONSTRAINT fk_landing_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- FAQs: attach to a service, article or landing page, or leave all NULL for the general /faq page
-- ---------------------------------------------------------------------------
CREATE TABLE faqs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id      INT UNSIGNED NULL,
    post_id         INT UNSIGNED NULL,
    landing_page_id INT UNSIGNED NULL,
    question        VARCHAR(255) NOT NULL,
    answer          TEXT NOT NULL,
    sort_order      SMALLINT NOT NULL DEFAULT 0,
    status          ENUM('draft', 'published') NOT NULL DEFAULT 'published',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_faqs_service (service_id, status),
    KEY idx_faqs_post (post_id, status),
    KEY idx_faqs_landing (landing_page_id, status),
    CONSTRAINT fk_faqs_service FOREIGN KEY (service_id)      REFERENCES services (id)      ON DELETE CASCADE,
    CONSTRAINT fk_faqs_post    FOREIGN KEY (post_id)         REFERENCES posts (id)         ON DELETE CASCADE,
    CONSTRAINT fk_faqs_landing FOREIGN KEY (landing_page_id) REFERENCES landing_pages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Keyword targeting plan. No search-volume column on purpose: volumes must come from
-- a real research tool (Google Keyword Planner / Search Console) and go in notes with the source.
-- ---------------------------------------------------------------------------
CREATE TABLE keywords (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    keyword    VARCHAR(150) NOT NULL,
    intent     ENUM('informational', 'navigational', 'commercial', 'transactional') NOT NULL,
    priority   ENUM('high', 'medium', 'low') NOT NULL DEFAULT 'medium',
    target_url VARCHAR(255) NULL COMMENT 'App path such as /services/web-hosting',
    content_type ENUM('guide', 'landing_page', 'service_page', 'home', 'faq') NULL,
    service_id INT UNSIGNED NULL COMMENT 'Primary service this keyword supports',
    status     ENUM('researching', 'targeting', 'mapped', 'paused') NOT NULL DEFAULT 'researching',
    notes      TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_keywords_keyword (keyword),
    KEY idx_keywords_status_priority (status, priority),
    CONSTRAINT fk_keywords_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Internal linking rules: phrase -> URL, applied once per article at render time
-- ---------------------------------------------------------------------------
CREATE TABLE internal_links (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    keyword    VARCHAR(150) NOT NULL,
    target_url VARCHAR(255) NOT NULL,
    priority   TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1 (low) - 10 (high)',
    status     ENUM('active', 'paused') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_internal_links_keyword (keyword),
    KEY idx_internal_links_status (status, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Off-page SEO work log. Records manual outreach; the app never creates links itself.
-- ---------------------------------------------------------------------------
CREATE TABLE backlinks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    platform    VARCHAR(120) NOT NULL,
    type        ENUM('directory', 'social', 'guest_post', 'citation', 'forum', 'pr', 'partner', 'other') NOT NULL DEFAULT 'other',
    source_url  VARCHAR(500) NULL,
    target_url  VARCHAR(500) NOT NULL,
    anchor_text VARCHAR(200) NULL,
    rel         ENUM('follow', 'nofollow', 'ugc', 'sponsored', 'unknown') NOT NULL DEFAULT 'unknown',
    status      ENUM('opportunity', 'submitted', 'pending', 'live', 'rejected') NOT NULL DEFAULT 'opportunity',
    notes       TEXT NULL,
    last_checked_at DATETIME NULL,
    verification_message VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_backlinks_status (status),
    KEY idx_backlinks_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Leads from contact / landing page forms
-- ---------------------------------------------------------------------------
CREATE TABLE leads (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(190) NOT NULL,
    phone       VARCHAR(20)  NULL,
    company     VARCHAR(150) NULL,
    interest    VARCHAR(120) NULL COMMENT 'Requirement chosen on the form',
    message     TEXT NOT NULL,
    source_page VARCHAR(255) NOT NULL DEFAULT '/',
    keyword     VARCHAR(150) NULL COMMENT 'Primary keyword of the page that captured the lead',
    campaign    VARCHAR(100) NULL COMMENT 'utm_campaign / utm_source when present',
    status      ENUM('new', 'contacted', 'qualified', 'converted', 'closed', 'spam') NOT NULL DEFAULT 'new',
    admin_notes TEXT NULL,
    ip_hash     CHAR(64) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_leads_status_created (status, created_at),
    KEY idx_leads_ip_created (ip_hash, created_at),
    KEY idx_leads_source (source_page)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- SEO audit history. Scores are an internal 0-100 diagnostic, not a Google metric.
-- ---------------------------------------------------------------------------
CREATE TABLE seo_audits (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url                 VARCHAR(500) NOT NULL,
    final_url           VARCHAR(500) NULL,
    http_status         SMALLINT UNSIGNED NULL,
    response_ms         INT UNSIGNED NULL,
    title_score         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    description_score   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    h1_score            TINYINT UNSIGNED NOT NULL DEFAULT 0,
    heading_score       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    canonical_score     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    robots_score        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    schema_score        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    alt_score           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    internal_link_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    og_score            TINYINT UNSIGNED NOT NULL DEFAULT 0,
    mobile_score        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    overall_score       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    issues              JSON NOT NULL COMMENT 'List of {check, status, message}',
    stats               JSON NULL COMMENT 'Counts: words, links, images, headings',
    created_by          INT UNSIGNED NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audits_created (created_at),
    KEY idx_audits_url (url(191)),
    CONSTRAINT fk_audits_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Organic distribution: where content was shared (social, communities, video)
-- ---------------------------------------------------------------------------
CREATE TABLE distribution_posts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    platform     ENUM('linkedin', 'x', 'facebook', 'reddit', 'youtube', 'quora', 'other') NOT NULL,
    title        VARCHAR(200) NOT NULL,
    post_id      INT UNSIGNED NULL COMMENT 'Article being distributed',
    url          VARCHAR(500) NULL COMMENT 'Public URL of the social post',
    status       ENUM('planned', 'published', 'skipped') NOT NULL DEFAULT 'planned',
    published_at DATE NULL,
    notes        TEXT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_distribution_status (status, platform),
    CONSTRAINT fk_distribution_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Admin activity timeline
-- ---------------------------------------------------------------------------
CREATE TABLE activity_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(40)  NOT NULL COMMENT 'created, updated, published, deleted, audited, verified, status',
    entity_type VARCHAR(40)  NOT NULL COMMENT 'post, service, landing, keyword, lead, backlink, audit, ...',
    entity_id   INT UNSIGNED NULL,
    label       VARCHAR(255) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activity_created (created_at),
    KEY idx_activity_entity (entity_type, entity_id),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Opportunity centre: remembers which computed opportunities were done or dismissed
-- ---------------------------------------------------------------------------
CREATE TABLE opportunity_states (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opportunity_key CHAR(40) NOT NULL COMMENT 'sha1 of the opportunity identity',
    status          ENUM('in_progress', 'done', 'dismissed') NOT NULL,
    updated_by      INT UNSIGNED NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_opportunity_key (opportunity_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Site settings (key/value)
-- ---------------------------------------------------------------------------
CREATE TABLE settings (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(60) NOT NULL,
    setting_value TEXT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
