-- WebHub.uz To'liq Ma'lumotlar Bazasi Strukturasi
-- Versiya: 1.0.0
-- Kodlash: utf8mb4

-- Agar baza mavjud bo'lsa, o'chirish (ehtiyot bo'ling!)
-- DROP DATABASE IF EXISTS webhub_uz;

CREATE DATABASE IF NOT EXISTS webhub_uz 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE webhub_uz;

-- ============================================
-- 1. FOYDALANUVCHILAR (users)
-- ============================================
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
    INDEX idx_google_id (google_id),
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. ADMINLAR (admins)
-- ============================================
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
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. XIZMATLAR (services)
-- ============================================
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
    INDEX idx_status (status),
    INDEX idx_popular (is_popular),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. PORTFOLIO (portfolio)
-- ============================================
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
    INDEX idx_status (status),
    INDEX idx_featured (is_featured),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. BLOG POSTLAR (blog_posts)
-- ============================================
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
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_published (published_at),
    INDEX idx_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. ARIZALAR (applications)
-- ============================================
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
    INDEX idx_user_id (user_id),
    INDEX idx_service_id (service_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. ARIZA HOLATI TARIXI (application_history)
-- ============================================
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
    INDEX idx_application_id (application_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. CHAT THREADLARI (chat_threads)
-- ============================================
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
    INDEX idx_user_id (user_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_status (status),
    INDEX idx_last_message (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. CHAT XABARLARI (chat_messages)
-- ============================================
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    sender_type ENUM('user', 'admin') NOT NULL,
    message TEXT NOT NULL,
    file_path VARCHAR(500) NULL,
    file_type VARCHAR(50) NULL,
    file_size INT NULL,
    is_read TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE,
    INDEX idx_thread_id (thread_id),
    INDEX idx_sender (sender_id, sender_type),
    INDEX idx_is_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. BILDIRISHNOMALAR (notifications)
-- ============================================
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
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_type (type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. SAYT SOZLAMALARI (settings)
-- ============================================
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json', 'text') DEFAULT 'string',
    group_name VARCHAR(50) DEFAULT 'general',
    description VARCHAR(255) NULL,
    is_public TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_group (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12. AUDIT LOG (audit_log)
-- ============================================
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
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 13. SESSIONS (sessions) - ixtiyoriy
-- ============================================
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    user_type ENUM('admin', 'user') DEFAULT 'user',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload TEXT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 14. RATE LIMITING (rate_limits)
-- ============================================
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

-- ============================================
-- BOSHLANG'ICH MA'LUMOTLAR
-- ============================================

-- Default Admin (parol: admin123 - o'zgartiring!)
INSERT INTO admins (username, password_hash, full_name, email, role, status) VALUES
('admin', '$argon2id$v=19$m=65536,t=4,p=1$c29tZXNhbHQ$RdescudvJCsgt3ub+b+dWRWJTmaaJObG', 'Bosh Admin', 'admin@webhub.uz', 'super_admin', 'active')
ON DUPLICATE KEY UPDATE username=username;

-- Default Xizmatlar
INSERT INTO services (title_uz, description_uz, icon, price_from, price_to, duration_days, is_popular, sort_order, status) VALUES
('Web Sayt Ishlab Chiqish', 'Zamonaviy, tezkor va SEO-optimal veb-saytlar yaratamiz. Landing page, korporativ saytlar, onlayn do\'konlar.', 'code', 500000, 5000000, 14, 1, 1, 'active'),
('Mobil Ilovalar', 'iOS va Android uchun native va cross-platform mobil ilovalar ishlab chiqish.', 'smartphone', 1000000, 8000000, 30, 1, 2, 'active'),
('UI/UX Dizayn', 'Foydalanuvchi tajribasini maksimal darajada oshiruvchi interfeys dizaynlari.', 'palette', 300000, 2000000, 7, 0, 3, 'active'),
('SEO Optimallashtirish', 'Saytingizni qidiruv tizimlarida yuqori o\'rinlarga chiqarish.', 'search', 400000, 3000000, 30, 0, 4, 'active'),
('Texnik Qo\'llab-quvvatlash', 'Sayt va ilovalaringizni 24/7 rejimida qo\'llab-quvvatlash va yangilash.', 'support', 200000, 1500000, 30, 0, 5, 'active'),
('SMM va Raqamli Marketing', 'Ijtimoiy tarmoqlarda brendingizni rivojlantirish va sotuvlarni oshirish.', 'megaphone', 300000, 2500000, 30, 1, 6, 'active');

-- Default Sozlamalar
INSERT INTO settings (setting_key, setting_value, setting_type, group_name, description, is_public) VALUES
('site_name', 'WebHub.uz', 'string', 'general', 'Sayt nomi', 1),
('site_title', 'WebHub.uz - Professional Web Dasturlash Xizmatlari', 'string', 'seo', 'SEO Title', 1),
('site_description', 'O\'zbekistonda professional web dasturlash, mobil ilovalar yaratish va SEO xizmatlari.', 'text', 'seo', 'Meta description', 1),
('site_keywords', 'web dasturlash, sayt yaratish, mobil ilova, SEO, O\'zbekiston', 'string', 'seo', 'Meta keywords', 1),
('contact_phone', '+998 90 123 45 67', 'string', 'contact', 'Aloqa telefoni', 1),
('contact_email', 'info@webhub.uz', 'string', 'contact', 'Aloqa email', 1),
('contact_telegram', '@webhub_uz', 'string', 'contact', 'Telegram username', 1),
('contact_instagram', 'webhub.uz', 'string', 'contact', 'Instagram username', 1),
('contact_address', 'Toshkent sh., Chilonzor tumani', 'string', 'contact', 'Manzil', 1),
('theme_color', '#6366f1', 'string', 'design', 'Asosiy rang', 1),
('upload_max_filesize', '5242880', 'number', 'uploads', 'Maksimal fayl hajmi (bayt)', 0),
('allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,xlsx', 'string', 'uploads', 'Ruxsat etilgan fayl turlari', 0),
('maintenance_mode', '0', 'boolean', 'system', 'Texnik ishlar rejimi', 0),
('registration_enabled', '1', 'boolean', 'system', 'Ro\'yxatdan o\'tish ruxsati', 0);

-- Demo Portfolio
INSERT INTO portfolio (title_uz, description_uz, image_path, category, technologies, client_name, is_featured, status) VALUES
('Onlayn Do\'kon "TechMarket"', 'Elektronika mahsulotlari uchun to\'liq funksional onlayn do\'kon. Admin panel, to\'lov tizimlari integratsiyasi.', '/uploads/portfolio/techmarket.jpg', 'ecommerce', '["Laravel", "Vue.js", "MySQL", "Redis"]', 'TechMarket LLC', 1, 'active'),
('Korporativ Sayt "QurilishPro"', 'Qurilish kompaniyasi uchun zamonaviy korporativ sayt. Portfolio, loyihalar kalkulyatori.', '/uploads/portfolio/qurilishpro.jpg', 'corporate', '["WordPress", "PHP", "Bootstrap"]', 'QurilishPro', 1, 'active'),
('Mobil Ilova "FoodDelivery"', 'Oziq-ovqat yetkazib berish xizmati uchun iOS va Android ilovalari.', '/uploads/portfolio/fooddelivery.jpg', 'mobile', '["Flutter", "Firebase", "Node.js"]', 'FoodDelivery Uz', 1, 'active'),
('CRM Tizimi "AutoService"', 'Avtoservis uchun mijozlar va buyurtmalar boshqaruv tizimi.', '/uploads/portfolio/autoservice.jpg', 'web-app', '["React", "Node.js", "PostgreSQL"]', 'AutoService', 0, 'active');

-- Demo Blog Postlar
INSERT INTO blog_posts (title_uz, slug, content_uz, excerpt_uz, featured_image, category, tags, status, published_at, is_featured) VALUES
('2024-yilda Web Dasturlash Trendlari', '2024-yilda-web-dasturlash-trendlari', '<p>2024-yil web dasturlash sohasida yangi texnologiyalar va yondashuvlarni olib keladi...</p>', 'Yangi texnologiyalar haqida batafsil...', '/uploads/blog/trends-2024.jpg', 'technology', '["web", "trend", "2024"]', 'published', NOW(), 1),
('SEO Optimallashtirish Asoslari', 'seo-optimallashtirish-asoslari', '<p>Qidiruv tizimlarida yuqori o\'rinlarga chiqish uchun asosiy qoidalar...</p>', 'SEO haqida barchasi...', '/uploads/blog/seo-basics.jpg', 'seo', '["seo", "google", "optimization"]', 'published', DATE_SUB(NOW(), INTERVAL 7 DAY), 0),
('Mobil Ilova Yaratish Bosqichlari', 'mobil-ilova-yaratish-bosqichlari', '<p>Muvaffaqiyatli mobil ilova yaratishning 10 ta asosiy bosqichi...</p>', 'Qadam-baqadam qo\'llanma...', '/uploads/blog/mobile-dev.jpg', 'development', '["mobile", "app", "tutorial"]', 'published', DATE_SUB(NOW(), INTERVAL 14 DAY), 0);

-- ============================================
-- TRIGGERLAR VA FUNKSIYALAR
-- ============================================

-- Application status o'zgarganda historyga yozish
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS trg_application_status_change
AFTER UPDATE ON applications
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO application_history (application_id, old_status, new_status, changed_by_type, comment)
        VALUES (NEW.id, OLD.status, NEW.status, 'system', CONCAT('Status o\'zgardi: ', OLD.status, ' -> ', NEW.status));
    END IF;
END$$
DELIMITER ;

-- Chat xabari yozilganda last_message_at yangilash
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS trg_chat_message_insert
AFTER INSERT ON chat_messages
FOR EACH ROW
BEGIN
    UPDATE chat_threads 
    SET last_message_at = NEW.created_at,
        last_message_by = NEW.sender_type,
        unread_user_count = CASE WHEN NEW.sender_type = 'admin' THEN unread_user_count + 1 ELSE unread_user_count END,
        unread_admin_count = CASE WHEN NEW.sender_type = 'user' THEN unread_admin_count + 1 ELSE unread_admin_count END
    WHERE id = NEW.thread_id;
END$$
DELIMITER ;

-- ============================================
-- KO'RINISHLAR (VIEWS)
-- ============================================

-- Oxirgi arizalar ko'rinishi
CREATE OR REPLACE VIEW vw_recent_applications AS
SELECT 
    a.id,
    a.full_name,
    a.email,
    a.phone,
    s.title_uz as service_name,
    a.status,
    a.priority,
    a.created_at,
    u.full_name as user_name
FROM applications a
LEFT JOIN services s ON a.service_id = s.id
LEFT JOIN users u ON a.user_id = u.id
ORDER BY a.created_at DESC
LIMIT 50;

-- Foydalanuvchi statistikasi
CREATE OR REPLACE VIEW vw_user_stats AS
SELECT 
    u.id,
    u.full_name,
    u.email,
    COUNT(DISTINCT a.id) as total_applications,
    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_applications,
    COUNT(DISTINCT ct.id) as total_chats,
    SUM(CASE WHEN n.is_read = 0 THEN 1 ELSE 0 END) as unread_notifications
FROM users u
LEFT JOIN applications a ON u.id = a.user_id
LEFT JOIN chat_threads ct ON u.id = ct.user_id
LEFT JOIN notifications n ON u.id = n.user_id
GROUP BY u.id;

-- Admin statistikasi
CREATE OR REPLACE VIEW vw_admin_dashboard_stats AS
SELECT 
    (SELECT COUNT(*) FROM users WHERE status = 'active') as total_users,
    (SELECT COUNT(*) FROM applications WHERE status IN ('new', 'in_review')) as pending_applications,
    (SELECT COUNT(*) FROM applications WHERE status = 'completed') as completed_applications,
    (SELECT COUNT(*) FROM chat_threads WHERE status = 'open') as active_chats,
    (SELECT SUM(unread_admin_count) FROM chat_threads) as total_unread_messages,
    (SELECT COUNT(*) FROM notifications WHERE is_read = 0) as total_unread_notifications;

-- ============================================
-- YAKUNLASH
-- ============================================

SELECT 'Ma\'lumotlar bazasi muvaffaqiyatli yaratildi!' as status;
