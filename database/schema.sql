CREATE DATABASE IF NOT EXISTS soon_uz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE soon_uz;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    google_id VARCHAR(255) UNIQUE NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    avatar_url VARCHAR(500) NULL,
    status ENUM('active', 'blocked', 'deleted') DEFAULT 'active',
    email_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL,
    INDEX idx_google_id (google_id), INDEX idx_email (email), INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    role ENUM('super_admin', 'admin', 'manager') DEFAULT 'admin',
    status ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username), INDEX idx_email (email), INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title_uz VARCHAR(255) NOT NULL,
    title_ru VARCHAR(255) NULL,
    title_en VARCHAR(255) NULL,
    description_uz TEXT NOT NULL,
    description_ru TEXT NULL,
    description_en TEXT NULL,
    icon VARCHAR(100) NOT NULL DEFAULT 'code',
    price_from DECIMAL(10,2) NULL,
    price_to DECIMAL(10,2) NULL,
    duration_days INT NULL,
    is_popular TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status), INDEX idx_popular (is_popular), INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title_uz VARCHAR(255) NOT NULL,
    title_ru VARCHAR(255) NULL,
    title_en VARCHAR(255) NULL,
    description_uz TEXT NULL,
    description_ru TEXT NULL,
    description_en TEXT NULL,
    image_path VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500) NULL,
    project_url VARCHAR(500) NULL,
    github_url VARCHAR(500) NULL,
    category VARCHAR(100) DEFAULT 'website',
    technologies JSON NULL,
    client_name VARCHAR(255) NULL,
    completed_date DATE NULL,
    is_featured TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    views_count INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status), INDEX idx_featured (is_featured), INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title_uz VARCHAR(500) NOT NULL,
    title_ru VARCHAR(500) NULL,
    title_en VARCHAR(500) NULL,
    slug VARCHAR(500) UNIQUE NOT NULL,
    content_uz LONGTEXT NOT NULL,
    content_ru LONGTEXT NULL,
    content_en LONGTEXT NULL,
    excerpt_uz TEXT NULL,
    excerpt_ru TEXT NULL,
    excerpt_en TEXT NULL,
    featured_image VARCHAR(500) NULL,
    author_id INT UNSIGNED NULL,
    category VARCHAR(100) DEFAULT 'news',
    tags JSON NULL,
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    meta_keywords VARCHAR(500) NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    views_count INT UNSIGNED DEFAULT 0,
    is_featured TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_slug (slug), INDEX idx_status (status), INDEX idx_published (published_at), INDEX idx_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    service_id INT UNSIGNED NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    company_name VARCHAR(255) NULL,
    message TEXT NULL,
    budget_min DECIMAL(10,2) NULL,
    budget_max DECIMAL(10,2) NULL,
    deadline_date DATE NULL,
    status ENUM('new', 'in_review', 'contacted', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'new',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    assigned_admin_id INT UNSIGNED NULL,
    source VARCHAR(50) DEFAULT 'website',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id), INDEX idx_service_id (service_id), INDEX idx_status (status), INDEX idx_priority (priority), INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NOT NULL,
    changed_by INT UNSIGNED NULL,
    changed_by_type ENUM('admin', 'system', 'user') DEFAULT 'system',
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_application_id (application_id), INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_threads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NOT NULL,
    admin_id INT UNSIGNED NULL,
    subject VARCHAR(255) NULL,
    status ENUM('open', 'closed', 'archived') DEFAULT 'open',
    last_message_at TIMESTAMP NULL,
    last_message_by ENUM('user', 'admin') NULL,
    unread_user_count INT DEFAULT 0,
    unread_admin_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id), INDEX idx_admin_id (admin_id), INDEX idx_status (status), INDEX idx_last_message (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    sender_type ENUM('user', 'admin') NOT NULL,
    message TEXT NULL,
    file_path VARCHAR(500) NULL,
    file_type VARCHAR(50) NULL,
    file_size INT NULL,
    is_read TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE,
    INDEX idx_thread_id (thread_id), INDEX idx_sender (sender_id, sender_type), INDEX idx_is_read (is_read), INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'application_update', 'chat_message') DEFAULT 'info',
    related_type VARCHAR(50) NULL,
    related_id INT UNSIGNED NULL,
    is_read TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id), INDEX idx_is_read (is_read), INDEX idx_type (type), INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json', 'text') DEFAULT 'string',
    group_name VARCHAR(50) DEFAULT 'general',
    description VARCHAR(255) NULL,
    is_public TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key), INDEX idx_group (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    user_type ENUM('admin', 'user', 'system') DEFAULT 'system',
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id), INDEX idx_action (action), INDEX idx_entity (entity_type, entity_id), INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    user_type ENUM('admin', 'user') DEFAULT 'user',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload TEXT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id), INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    action VARCHAR(100) NOT NULL,
    attempts INT DEFAULT 1,
    last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL,
    UNIQUE KEY unique_identifier_action (identifier, action),
    INDEX idx_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO services (title_uz, description_uz, icon, price_from, price_to, duration_days, is_popular, sort_order, status) VALUES
('Veb-sayt ishlab chiqish', 'Zamonaviy, tezkor va moslashuvchan veb-saytlar yaratish.', 'code', 500000, 5000000, 14, 1, 1, 'active'),
('Mobil ilovalar', 'Android va iOS uchun qulay va zamonaviy mobil ilovalar.', 'smartphone', 1000000, 8000000, 30, 1, 2, 'active'),
('UI/UX dizayn', 'Foydalanuvchi tajribasiga yo‘naltirilgan interfeyslar.', 'palette', 300000, 2000000, 7, 0, 3, 'active');

INSERT INTO settings (setting_key, setting_value, setting_type, group_name, description, is_public) VALUES
('site_name', 'SOON', 'string', 'general', 'Sayt nomi', 1),
('site_title', 'SOON — raqamli mahsulotlar va IT xizmatlari', 'string', 'seo', 'SEO sarlavhasi', 1),
('site_description', 'SOON — zamonaviy raqamli mahsulotlar va IT xizmatlari.', 'text', 'seo', 'Meta tavsifi', 1),
('site_keywords', 'SOON, IT, web, mobil ilova, dasturlash, O‘zbekiston', 'string', 'seo', 'Meta kalit so‘zlari', 1),
('site_url', 'https://soon.uz', 'string', 'general', 'Canonical URL', 1),
('site_tagline', '', 'string', 'general', 'Tagline', 1),
('founder_name', '', 'string', 'general', 'Asoschi', 1),
('contact_phone', '', 'string', 'contact', 'Aloqa telefoni', 1),
('contact_email', '', 'string', 'contact', 'Aloqa emaili', 1),
('contact_telegram', '', 'string', 'contact', 'Telegram', 1),
('contact_instagram', '', 'string', 'contact', 'Instagram', 1),
('contact_address', '', 'string', 'contact', 'Manzil', 1),
('primary_color', '#6366f1', 'string', 'design', 'Asosiy rang', 1),
('accent_color', '#06b6d4', 'string', 'design', 'Accent rang', 1),
('theme_color', '#6366f1', 'string', 'design', 'Moslik uchun asosiy rang', 1),
('logo_path', '', 'string', 'branding', 'Logo', 1),
('favicon_path', '', 'string', 'branding', 'Favicon', 1),
('hero_banner_path', '', 'string', 'branding', 'Hero banner', 1),
('og_image_path', '', 'string', 'branding', 'OG image', 1),
('stat_projects_override', '', 'number', 'stats', 'Portfolio statistikasi', 1),
('stat_clients_override', '', 'number', 'stats', 'Mijozlar statistikasi', 1),
('max_upload_image_mb', '10', 'number', 'uploads', 'Rasm maksimal hajmi', 0),
('max_upload_doc_mb', '20', 'number', 'uploads', 'Hujjat maksimal hajmi', 0),
('upload_max_filesize', '5242880', 'number', 'uploads', 'Moslik uchun maksimal fayl hajmi', 0),
('allowed_file_types', 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xlsx', 'string', 'uploads', 'Ruxsat etilgan fayl turlari', 0),
('maintenance_mode', '0', 'boolean', 'system', 'Texnik ishlar rejimi', 0),
('registration_enabled', '1', 'boolean', 'system', 'Ro‘yxatdan o‘tish ruxsati', 0)
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
