CREATE TABLE IF NOT EXISTS admin_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(100) UNIQUE NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_role_permissions (
    role ENUM('super_admin', 'admin', 'manager') NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role, permission_id),
    FOREIGN KEY (permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_permissions (permission_key, description) VALUES
('dashboard.view', 'Dashboard ko‘rish'),
('applications.view', 'Arizalarni ko‘rish'),
('applications.manage', 'Arizalarni boshqarish'),
('users.view', 'Foydalanuvchilarni ko‘rish'),
('users.manage', 'Foydalanuvchilarni boshqarish'),
('services.view', 'Xizmatlarni ko‘rish'),
('services.manage', 'Xizmatlarni boshqarish'),
('portfolio.view', 'Portfolio ko‘rish'),
('portfolio.manage', 'Portfolio boshqarish'),
('blog.view', 'Blog ko‘rish'),
('blog.manage', 'Blog boshqarish'),
('chat.view', 'Chat ko‘rish'),
('chat.manage', 'Chat boshqarish'),
('branding.manage', 'Brendingni boshqarish'),
('settings.manage', 'Sozlamalarni boshqarish'),
('admins.manage', 'Adminlarni boshqarish'),
('audit.view', 'Audit jurnalini ko‘rish')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT IGNORE INTO admin_role_permissions (role, permission_id)
SELECT 'super_admin', id FROM admin_permissions;

INSERT IGNORE INTO admin_role_permissions (role, permission_id)
SELECT 'admin', id FROM admin_permissions
WHERE permission_key NOT IN ('admins.manage', 'settings.manage', 'audit.view');

INSERT IGNORE INTO admin_role_permissions (role, permission_id)
SELECT 'manager', id FROM admin_permissions
WHERE permission_key IN ('dashboard.view', 'applications.view', 'applications.manage', 'users.view', 'services.view', 'portfolio.view', 'blog.view', 'chat.view', 'chat.manage');
