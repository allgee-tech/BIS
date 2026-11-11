CREATE TABLE IF NOT EXISTS c2c_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student','employer','institution_admin','admin') NOT NULL DEFAULT 'student',
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_c2c_users_email (email),
    KEY idx_c2c_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_institutions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    code VARCHAR(40) NULL,
    location VARCHAR(160) NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'Ghana',
    website VARCHAR(255) NULL,
    logo_path VARCHAR(255) NULL,
    description TEXT NULL,
    contact_email VARCHAR(190) NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_c2c_institutions_name (name),
    UNIQUE KEY uq_c2c_institutions_code (code),
    KEY idx_c2c_institutions_active (is_active, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_student_profiles (
    user_id BIGINT UNSIGNED NOT NULL,
    institution_id BIGINT UNSIGNED NULL,
    programme VARCHAR(180) NULL,
    academic_level VARCHAR(80) NULL,
    graduation_year SMALLINT UNSIGNED NULL,
    skills TEXT NULL,
    career_interests TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY idx_c2c_student_institution (institution_id),
    CONSTRAINT fk_c2c_student_user FOREIGN KEY (user_id) REFERENCES c2c_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_c2c_student_institution FOREIGN KEY (institution_id) REFERENCES c2c_institutions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_organizations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    organization_type VARCHAR(100) NOT NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'Ghana',
    website VARCHAR(255) NULL,
    phone VARCHAR(60) NULL,
    description TEXT NULL,
    verification_details TEXT NULL,
    verification_status ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_c2c_organization_owner (owner_user_id),
    KEY idx_c2c_organization_verification (verification_status, name),
    CONSTRAINT fk_c2c_organization_owner FOREIGN KEY (owner_user_id) REFERENCES c2c_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_organization_representatives (
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role_title VARCHAR(100) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (organization_id, user_id),
    UNIQUE KEY uq_c2c_org_rep_user (user_id),
    KEY idx_c2c_org_rep_active (is_active),
    CONSTRAINT fk_c2c_org_rep_org FOREIGN KEY (organization_id) REFERENCES c2c_organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_c2c_org_rep_user FOREIGN KEY (user_id) REFERENCES c2c_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_institution_admins (
    institution_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (institution_id, user_id),
    UNIQUE KEY uq_c2c_institution_admin_user (user_id),
    CONSTRAINT fk_c2c_inst_admin_institution FOREIGN KEY (institution_id) REFERENCES c2c_institutions (id) ON DELETE CASCADE,
    CONSTRAINT fk_c2c_inst_admin_user FOREIGN KEY (user_id) REFERENCES c2c_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_opportunity_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(110) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_c2c_category_name (name),
    UNIQUE KEY uq_c2c_category_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_opportunities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NULL,
    institution_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description LONGTEXT NOT NULL,
    responsibilities TEXT NULL,
    requirements TEXT NULL,
    qualifications TEXT NULL,
    required_skills VARCHAR(1000) NULL,
    location VARCHAR(180) NULL,
    work_arrangement ENUM('on_site','hybrid','remote','not_specified') NOT NULL DEFAULT 'not_specified',
    compensation VARCHAR(180) NULL,
    application_deadline DATE NULL,
    start_date DATE NULL,
    duration VARCHAR(100) NULL,
    application_method ENUM('platform','external') NOT NULL DEFAULT 'platform',
    application_url VARCHAR(1000) NULL,
    contact_email VARCHAR(190) NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('draft','pending_review','approved','published','rejected','suspended','expired','closed') NOT NULL DEFAULT 'draft',
    rejection_reason VARCHAR(1000) NULL,
    published_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_c2c_opportunity_public (status, application_deadline, published_at),
    KEY idx_c2c_opportunity_category (category_id, status),
    KEY idx_c2c_opportunity_organization (organization_id, created_by_user_id),
    KEY idx_c2c_opportunity_institution (institution_id, status),
    FULLTEXT KEY ft_c2c_opportunity_search (title, description, required_skills, location),
    CONSTRAINT fk_c2c_opportunity_org FOREIGN KEY (organization_id) REFERENCES c2c_organizations (id) ON DELETE SET NULL,
    CONSTRAINT fk_c2c_opportunity_institution FOREIGN KEY (institution_id) REFERENCES c2c_institutions (id) ON DELETE SET NULL,
    CONSTRAINT fk_c2c_opportunity_author FOREIGN KEY (created_by_user_id) REFERENCES c2c_users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_c2c_opportunity_category FOREIGN KEY (category_id) REFERENCES c2c_opportunity_categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_applications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    opportunity_id BIGINT UNSIGNED NOT NULL,
    student_user_id BIGINT UNSIGNED NOT NULL,
    cover_letter TEXT NULL,
    status ENUM('submitted','under_review','shortlisted','interview','accepted','rejected','withdrawn') NOT NULL DEFAULT 'submitted',
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_c2c_application_student_opportunity (student_user_id, opportunity_id),
    KEY idx_c2c_application_opportunity_status (opportunity_id, status, submitted_at),
    KEY idx_c2c_application_student_status (student_user_id, status, submitted_at),
    CONSTRAINT fk_c2c_application_opportunity FOREIGN KEY (opportunity_id) REFERENCES c2c_opportunities (id) ON DELETE CASCADE,
    CONSTRAINT fk_c2c_application_student FOREIGN KEY (student_user_id) REFERENCES c2c_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    application_id BIGINT UNSIGNED NULL,
    kind ENUM('cv','supporting') NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_c2c_document_owner (owner_user_id, kind, created_at),
    KEY idx_c2c_document_application (application_id),
    CONSTRAINT fk_c2c_document_owner FOREIGN KEY (owner_user_id) REFERENCES c2c_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_c2c_document_application FOREIGN KEY (application_id) REFERENCES c2c_applications (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_saved_opportunities (
    user_id BIGINT UNSIGNED NOT NULL,
    opportunity_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, opportunity_id),
    KEY idx_c2c_saved_opportunity (opportunity_id),
    CONSTRAINT fk_c2c_saved_user FOREIGN KEY (user_id) REFERENCES c2c_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_c2c_saved_opportunity FOREIGN KEY (opportunity_id) REFERENCES c2c_opportunities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    message VARCHAR(1000) NOT NULL,
    link VARCHAR(500) NULL,
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_c2c_notification_user_unread (user_id, read_at, created_at),
    CONSTRAINT fk_c2c_notification_user FOREIGN KEY (user_id) REFERENCES c2c_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_deadline_notices (
    user_id BIGINT UNSIGNED NOT NULL,
    opportunity_id BIGINT UNSIGNED NOT NULL,
    deadline DATE NOT NULL,
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, opportunity_id, deadline),
    CONSTRAINT fk_c2c_deadline_notice_user FOREIGN KEY (user_id) REFERENCES c2c_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_c2c_deadline_notice_opportunity FOREIGN KEY (opportunity_id) REFERENCES c2c_opportunities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_resources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    author_user_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    category VARCHAR(100) NOT NULL,
    summary VARCHAR(500) NOT NULL,
    content LONGTEXT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    published_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_c2c_resource_slug (slug),
    KEY idx_c2c_resource_published (is_published, published_at),
    CONSTRAINT fk_c2c_resource_author FOREIGN KEY (author_user_id) REFERENCES c2c_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_announcements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    author_user_id BIGINT UNSIGNED NULL,
    institution_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    published_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_c2c_announcement_public (is_published, published_at),
    KEY idx_c2c_announcement_institution (institution_id, is_published, published_at),
    CONSTRAINT fk_c2c_announcement_author FOREIGN KEY (author_user_id) REFERENCES c2c_users (id) ON DELETE SET NULL,
    CONSTRAINT fk_c2c_announcement_institution FOREIGN KEY (institution_id) REFERENCES c2c_institutions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reporter_user_id BIGINT UNSIGNED NULL,
    opportunity_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    status ENUM('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_c2c_report_status (status, created_at),
    CONSTRAINT fk_c2c_report_reporter FOREIGN KEY (reporter_user_id) REFERENCES c2c_users (id) ON DELETE SET NULL,
    CONSTRAINT fk_c2c_report_opportunity FOREIGN KEY (opportunity_id) REFERENCES c2c_opportunities (id) ON DELETE CASCADE,
    CONSTRAINT fk_c2c_report_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES c2c_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_contact_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    sender_name VARCHAR(160) NOT NULL,
    sender_email VARCHAR(190) NOT NULL,
    subject VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new','handled') NOT NULL DEFAULT 'new',
    handled_by_user_id BIGINT UNSIGNED NULL,
    handled_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_c2c_contact_status (status, created_at),
    CONSTRAINT fk_c2c_contact_user FOREIGN KEY (user_id) REFERENCES c2c_users (id) ON DELETE SET NULL,
    CONSTRAINT fk_c2c_contact_handler FOREIGN KEY (handled_by_user_id) REFERENCES c2c_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_institution_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    requested_by_user_id BIGINT UNSIGNED NULL,
    institution_name VARCHAR(180) NOT NULL,
    location VARCHAR(160) NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'Ghana',
    contact_email VARCHAR(190) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    review_note VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_c2c_institution_request_status (status, created_at),
    CONSTRAINT fk_c2c_inst_request_user FOREIGN KEY (requested_by_user_id) REFERENCES c2c_users (id) ON DELETE SET NULL,
    CONSTRAINT fk_c2c_inst_request_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES c2c_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_password_resets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_c2c_password_reset_token (token_hash),
    KEY idx_c2c_password_reset_user (user_id, expires_at),
    CONSTRAINT fk_c2c_password_reset_user FOREIGN KEY (user_id) REFERENCES c2c_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_email_verifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_c2c_email_verification_token (token_hash),
    KEY idx_c2c_email_verification_user (user_id, expires_at),
    CONSTRAINT fk_c2c_email_verification_user FOREIGN KEY (user_id) REFERENCES c2c_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_auth_attempts (
    ip_hash CHAR(64) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_started TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (ip_hash),
    KEY idx_c2c_auth_attempt_expiry (window_started, blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS c2c_audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id BIGINT UNSIGNED NULL,
    action_key VARCHAR(60) NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    after_data JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_c2c_audit_created (created_at),
    KEY idx_c2c_audit_entity (entity_type, entity_id, created_at),
    CONSTRAINT fk_c2c_audit_actor FOREIGN KEY (actor_user_id) REFERENCES c2c_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO c2c_institutions (name, code, location, country, is_verified, is_active)
VALUES ('Palm University College', 'PUC', 'Accra', 'Ghana', 0, 1);

INSERT IGNORE INTO c2c_opportunity_categories (name, slug, sort_order) VALUES
('Internships', 'internships', 1),
('Full-time jobs', 'full-time-jobs', 2),
('Part-time jobs', 'part-time-jobs', 3),
('Remote jobs', 'remote-jobs', 4),
('Graduate programmes', 'graduate-programmes', 5),
('Scholarships', 'scholarships', 6),
('Fellowships', 'fellowships', 7),
('Volunteering', 'volunteering', 8),
('Training programmes', 'training-programmes', 9),
('Career development programmes', 'career-development-programmes', 10),
('Practical projects', 'practical-projects', 11),
('Industry challenges', 'industry-challenges', 12),
('Competitions', 'competitions', 13),
('Other opportunities', 'other-opportunities', 14);
