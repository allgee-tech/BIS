CREATE TABLE IF NOT EXISTS c2c_application_status_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NOT NULL,
    status ENUM('submitted','under_review','shortlisted','interview','accepted','rejected','withdrawn') NOT NULL,
    changed_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_c2c_application_history (application_id, created_at, id),
    CONSTRAINT fk_c2c_application_history_application FOREIGN KEY (application_id) REFERENCES c2c_applications (id) ON DELETE CASCADE,
    CONSTRAINT fk_c2c_application_history_actor FOREIGN KEY (changed_by_user_id) REFERENCES c2c_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
