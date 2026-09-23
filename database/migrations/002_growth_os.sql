-- Upgrade an existing SYSCOM GrowthHub database (created from the first schema.sql) to the
-- "growth OS" version: categories, activity timeline, organic distribution, opportunity states,
-- SEO override fields and lead attribution. Fresh installs do not need this: schema.sql already
-- contains everything. Run once:  mysql -u root -p syscom_growthhub < database/migrations/002_growth_os.sql

ALTER TABLE users ADD COLUMN notifications_seen_at DATETIME NULL AFTER last_login_at;

ALTER TABLE services
    ADD COLUMN benefits TEXT NULL AFTER external_url,
    ADD COLUMN cta_text VARCHAR(150) NOT NULL DEFAULT 'Talk to our team' AFTER benefits,
    ADD COLUMN canonical_url VARCHAR(255) NULL AFTER meta_description,
    ADD COLUMN og_title VARCHAR(100) NULL AFTER canonical_url,
    ADD COLUMN og_description VARCHAR(200) NULL AFTER og_title;

CREATE TABLE IF NOT EXISTS categories (
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

ALTER TABLE posts
    ADD COLUMN canonical_url VARCHAR(255) NULL AFTER featured_image_alt,
    ADD COLUMN og_title VARCHAR(100) NULL AFTER canonical_url,
    ADD COLUMN og_description VARCHAR(200) NULL AFTER og_title,
    ADD COLUMN schema_type ENUM('Article', 'BlogPosting', 'TechArticle') NOT NULL DEFAULT 'Article' AFTER og_description,
    ADD COLUMN category_id INT UNSIGNED NULL AFTER schema_type,
    ADD KEY idx_posts_category (category_id),
    ADD CONSTRAINT fk_posts_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL;

ALTER TABLE landing_pages
    ADD COLUMN canonical_url VARCHAR(255) NULL AFTER cta_text,
    ADD COLUMN og_title VARCHAR(100) NULL AFTER canonical_url,
    ADD COLUMN og_description VARCHAR(200) NULL AFTER og_title;

ALTER TABLE keywords
    ADD COLUMN content_type ENUM('guide', 'landing_page', 'service_page', 'home', 'faq') NULL AFTER target_url,
    ADD COLUMN service_id INT UNSIGNED NULL AFTER content_type,
    ADD CONSTRAINT fk_keywords_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL;

ALTER TABLE backlinks ADD COLUMN verification_message VARCHAR(255) NULL AFTER last_checked_at;

-- Lead statuses renamed: won -> converted, lost -> closed.
ALTER TABLE leads MODIFY status ENUM('new', 'contacted', 'qualified', 'won', 'lost', 'converted', 'closed', 'spam') NOT NULL DEFAULT 'new';
UPDATE leads SET status = 'converted' WHERE status = 'won';
UPDATE leads SET status = 'closed' WHERE status = 'lost';
ALTER TABLE leads
    MODIFY status ENUM('new', 'contacted', 'qualified', 'converted', 'closed', 'spam') NOT NULL DEFAULT 'new',
    ADD COLUMN keyword VARCHAR(150) NULL AFTER source_page,
    ADD COLUMN campaign VARCHAR(100) NULL AFTER keyword;

CREATE TABLE IF NOT EXISTS distribution_posts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    platform     ENUM('linkedin', 'x', 'facebook', 'reddit', 'youtube', 'quora', 'other') NOT NULL,
    title        VARCHAR(200) NOT NULL,
    post_id      INT UNSIGNED NULL,
    url          VARCHAR(500) NULL,
    status       ENUM('planned', 'published', 'skipped') NOT NULL DEFAULT 'planned',
    published_at DATE NULL,
    notes        TEXT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_distribution_status (status, platform),
    CONSTRAINT fk_distribution_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(40)  NOT NULL,
    entity_type VARCHAR(40)  NOT NULL,
    entity_id   INT UNSIGNED NULL,
    label       VARCHAR(255) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activity_created (created_at),
    KEY idx_activity_entity (entity_type, entity_id),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opportunity_states (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opportunity_key CHAR(40) NOT NULL,
    status          ENUM('in_progress', 'done', 'dismissed') NOT NULL,
    updated_by      INT UNSIGNED NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_opportunity_key (opportunity_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
