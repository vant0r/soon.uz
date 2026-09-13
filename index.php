<?php
/**
 * WebHub.uz - Public Homepage
 * Phase 5: Built after Admin Content Management has seeded data
 */

require_once __DIR__ . '/includes/functions.php';
startSecureSession();

// Fetch homepage data
$services = dbFetchAll("SELECT * FROM services ORDER BY sort_order, id");
$portfolio = dbFetchAll("SELECT * FROM portfolio ORDER BY sort_order, id LIMIT 6");
$blogPosts = dbFetchAll("SELECT * FROM blog_posts WHERE status = 'published' ORDER BY created_at DESC LIMIT 3");

// Get stats (computed with optional override per Section 1, Resolution 4)
$statProjects = getStatCount('portfolio', 'stat_projects_override');
$statClients = getStatCount('users', 'stat_clients_override');
$statCompleted = getStatCount('applications');

// Site settings
$settings = getAllSiteSettings();

$pageTitle = $settings['seo_meta_description'] ?? 'WebHub - Professional IT xizmatlar';
$pageKeywords = $settings['seo_keywords'] ?? '';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($settings['hero_headline'] ?? 'WebHub.uz'); ?></title>
    <meta name="description" content="<?php echo e($settings['seo_meta_description'] ?? ''); ?>">
    <meta name="keywords" content="<?php echo e($settings['seo_keywords'] ?? ''); ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo e($settings['hero_headline'] ?? 'WebHub.uz'); ?>">
    <meta property="og:description" content="<?php echo e($settings['seo_meta_description'] ?? ''); ?>">
    <meta property="og:type" content="website">
    
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        /* Homepage specific styles */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 100px 20px 60px;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, var(--primary) 0%, transparent 70%);
            opacity: 0.1;
            animation: rotate 30s linear infinite;
        }
        
        @keyframes rotate {
            to { transform: rotate(360deg); }
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 900px;
        }
        
        .hero h1 {
            margin-bottom: 24px;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero p {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hero-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .stats-bar {
            display: flex;
            gap: 40px;
            justify-content: center;
            margin-top: 60px;
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary);
            display: block;
        }
        
        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .service-card {
            position: relative;
            overflow: hidden;
        }
        
        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .service-card:hover::before {
            transform: scaleX(1);
        }
        
        .service-price {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
            margin-top: 16px;
        }
        
        .process-timeline {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .process-timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 100%;
            background: var(--border-color);
        }
        
        .process-step {
            display: flex;
            align-items: center;
            gap: 40px;
            margin-bottom: 40px;
            position: relative;
        }
        
        .process-step:nth-child(even) {
            flex-direction: row-reverse;
        }
        
        .process-step::after {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 20px;
            background: var(--primary);
            border-radius: 50%;
            border: 4px solid var(--bg-secondary);
        }
        
        .process-content {
            flex: 1;
            max-width: 350px;
        }
        
        .process-number {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        @media (max-width: 768px) {
            .process-timeline::before {
                left: 20px;
            }
            
            .process-step,
            .process-step:nth-child(even) {
                flex-direction: row;
                padding-left: 60px;
            }
            
            .process-step::after {
                left: 20px;
            }
            
            .process-content {
                max-width: none;
            }
            
            .stats-bar {
                gap: 20px;
            }
        }
        
        .contact-form {
            max-width: 600px;
            margin: 0 auto;
        }
        
        footer {
            background: var(--bg-primary);
            padding: 40px 0;
            border-top: 1px solid var(--border-color);
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .social-links {
            display: flex;
            gap: 16px;
        }
        
        .social-link {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--bg-tertiary);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .social-link:hover {
            background: var(--primary);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="glass" style="position: fixed; top: 0; left: 0; right: 0; z-index: 1000; padding: 16px 0;">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <a href="/" style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);">WebHub</a>
            
            <div class="hide-mobile" style="display: flex; gap: 24px; align-items: center;">
                <a href="#services" style="color: var(--text-secondary);">Xizmatlar</a>
                <a href="#portfolio" style="color: var(--text-secondary);">Portfolio</a>
                <a href="#blog" style="color: var(--text-secondary);">Blog</a>
                <a href="#contact" style="color: var(--text-secondary);">Aloqa</a>
                <button data-theme-toggle class="btn btn-secondary btn-sm" aria-label="Mavzu o'zgartirish">
                    🌓
                </button>
                <?php if (isUserLoggedIn()): ?>
                <a href="user/dashboard.php" class="btn btn-primary">Kabinet</a>
                <?php else: ?>
                <a href="user/login.php" class="btn btn-primary">Kirish</a>
                <?php endif; ?>
            </div>
            
            <button class="show-mobile btn btn-secondary" data-mobile-nav-toggle aria-label="Menyu">
                ☰
            </button>
        </div>
    </nav>
    
    <!-- Mobile Menu Overlay -->
    <div data-mobile-nav-overlay style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1001;" 
         onclick="this.previousElementSibling.classList.remove('active'); this.classList.remove('active'); document.body.style.overflow=''"></div>
    
    <div data-mobile-nav-menu style="position: fixed; top: 0; right: -100%; width: 80%; max-width: 300px; height: 100vh; background: var(--bg-primary); z-index: 1002; padding: 80px 24px 24px; transition: right 0.3s ease;">
        <button data-mobile-nav-close class="btn btn-secondary" style="position: absolute; top: 20px; right: 20px;">✕</button>
        <nav style="display: flex; flex-direction: column; gap: 16px;">
            <a href="#services" style="padding: 12px; color: var(--text-primary);">Xizmatlar</a>
            <a href="#portfolio" style="padding: 12px; color: var(--text-primary);">Portfolio</a>
            <a href="#blog" style="padding: 12px; color: var(--text-primary);">Blog</a>
            <a href="#contact" style="padding: 12px; color: var(--text-primary);">Aloqa</a>
            <hr style="border: none; border-top: 1px solid var(--border-color);">
            <?php if (isUserLoggedIn()): ?>
            <a href="user/dashboard.php" class="btn btn-primary">Kabinet</a>
            <?php else: ?>
            <a href="user/login.php" class="btn btn-primary">Kirish</a>
            <?php endif; ?>
            <button data-theme-toggle class="btn btn-secondary">🌓 Mavzuni o'zgartirish</button>
        </nav>
    </div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1 class="scroll-reveal"><?php echo e($settings['hero_headline'] ?? 'Kelajak Texnologiyalari Bugun'); ?></h1>
            <p class="scroll-reveal"><?php echo e($settings['hero_description'] ?? 'Biznesingizni raqamli dunyoda rivojlantirish uchun professional IT yechimlar'); ?></p>
            <div class="hero-buttons scroll-reveal">
                <a href="#contact" class="btn btn-primary btn-lg">Bepul maslahat olish</a>
                <a href="#services" class="btn btn-secondary btn-lg">Xizmatlarni ko\'rish</a>
            </div>
            
            <div class="stats-bar scroll-reveal">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $statProjects; ?>+</span>
                    <span class="stat-label">Loyihalar</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $statClients; ?>+</span>
                    <span class="stat-label">Mijozlar</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $statCompleted; ?>+</span>
                    <span class="stat-label">Bajarilgan</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="section">
        <div class="container">
            <h2 class="text-center mb-4 scroll-reveal">Xizmatlarimiz</h2>
            <div class="grid grid-2 scroll-reveal">
                <?php foreach ($services as $service): ?>
                <div class="glass-card service-card">
                    <h3><?php echo e($service['title']); ?></h3>
                    <p style="color: var(--text-secondary); margin: 16px 0;"><?php echo e($service['description']); ?></p>
                    <?php if ($service['price'] > 0): ?>
                    <div class="service-price">
                        <?php echo number_format($service['price'], 0, ',', ' '); ?> so'mdan
                    </div>
                    <?php endif; ?>
                    <a href="#contact" class="btn btn-primary" style="margin-top: 16px; width: 100%;">Buyurtma berish</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Portfolio Section -->
    <section id="portfolio" class="section" style="background: var(--bg-secondary);">
        <div class="container">
            <h2 class="text-center mb-4 scroll-reveal">Portfoliomiz</h2>
            <?php if (empty($portfolio)): ?>
            <p class="text-center" style="color: var(--text-muted);">Hozircha loyihalar yo\'q. Tez orada qo\'shiladi.</p>
            <?php else: ?>
            <div class="grid grid-3 scroll-reveal">
                <?php foreach ($portfolio as $project): ?>
                <div class="glass-card" style="padding: 0; overflow: hidden;">
                    <?php if ($project['image']): ?>
                    <img src="<?php echo e($project['image']); ?>" alt="<?php echo e($project['title']); ?>" 
                         style="width: 100%; height: 200px; object-fit: cover;" loading="lazy">
                    <?php endif; ?>
                    <div style="padding: 20px;">
                        <h4><?php echo e($project['title']); ?></h4>
                        <?php if ($project['client_name']): ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem;"><?php echo e($project['client_name']); ?></p>
                        <?php endif; ?>
                        <?php if ($project['link']): ?>
                        <a href="<?php echo e($project['link']); ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="margin-top: 12px;">Ko\'rish →</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- How We Work Section -->
    <section class="section">
        <div class="container">
            <h2 class="text-center mb-4 scroll-reveal">Qanday ishlaymiz</h2>
            <div class="process-timeline scroll-reveal">
                <div class="process-step">
                    <div class="process-number">1</div>
                    <div class="process-content glass-card">
                        <h4><?php echo e($settings['how_we_work_step1_title'] ?? 'G\'oya'); ?></h4>
                        <p style="color: var(--text-secondary);"><?php echo e($settings['how_we_work_step1_desc'] ?? 'Loyihangiz g\'oyasi va talablarini muhokama qilamiz'); ?></p>
                    </div>
                </div>
                <div class="process-step">
                    <div class="process-number">2</div>
                    <div class="process-content glass-card">
                        <h4><?php echo e($settings['how_we_work_step2_title'] ?? 'Reja'); ?></h4>
                        <p style="color: var(--text-secondary);"><?php echo e($settings['how_we_work_step2_desc'] ?? 'Texnik topshiriq va ish rejasi tuzamiz'); ?></p>
                    </div>
                </div>
                <div class="process-step">
                    <div class="process-number">3</div>
                    <div class="process-content glass-card">
                        <h4><?php echo e($settings['how_we_work_step3_title'] ?? 'Rivojlantirish'); ?></h4>
                        <p style="color: var(--text-secondary);"><?php echo e($settings['how_we_work_step3_desc'] ?? 'Loyihani sifatli va o\'z vaqtida bajarib beramiz'); ?></p>
                    </div>
                </div>
                <div class="process-step">
                    <div class="process-number">4</div>
                    <div class="process-content glass-card">
                        <h4><?php echo e($settings['how_we_work_step4_title'] ?? 'Natija'); ?></h4>
                        <p style="color: var(--text-secondary);"><?php echo e($settings['how_we_work_step4_desc'] ?? 'Tayyor mahsulotni taqdim etamiz va qo\'llab-quvvatlaymiz'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Blog Section -->
    <section id="blog" class="section" style="background: var(--bg-secondary);">
        <div class="container">
            <h2 class="text-center mb-4 scroll-reveal">Yangiliklar</h2>
            <?php if (empty($blogPosts)): ?>
            <p class="text-center" style="color: var(--text-muted);">Hozircha yangiliklar yo\'q.</p>
            <?php else: ?>
            <div class="grid grid-3 scroll-reveal">
                <?php foreach ($blogPosts as $post): ?>
                <div class="glass-card" style="padding: 0; overflow: hidden;">
                    <?php if ($post['image']): ?>
                    <img src="<?php echo e($post['image']); ?>" alt="<?php echo e($post['title']); ?>" 
                         style="width: 100%; height: 180px; object-fit: cover;" loading="lazy">
                    <?php endif; ?>
                    <div style="padding: 20px;">
                        <h4><?php echo e($post['title']); ?></h4>
                        <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 8px;">
                            <?php echo date('d.m.Y', strtotime($post['created_at'])); ?>
                        </p>
                        <a href="blog-view.php?id=<?php echo $post['id']; ?>" class="btn btn-secondary btn-sm" style="margin-top: 12px;">O\'qish →</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Contact Form Section -->
    <section id="contact" class="section">
        <div class="container">
            <h2 class="text-center mb-4 scroll-reveal">Bog\'lanish</h2>
            <p class="text-center mb-4" style="color: var(--text-secondary); max-width: 600px; margin-left: auto; margin-right: auto;">
                Loyihangiz bormi? Biz bilan bog\'laning va bepul maslahat oling!
            </p>
            
            <form class="contact-form glass-card scroll-reveal" method="POST" action="submit-application.php" data-validate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <div style="margin-bottom: 20px;">
                    <label for="name">Ismingiz</label>
                    <input type="text" id="name" name="name" required placeholder="Ismingizni kiriting">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label for="phone">Telefon raqamingiz</label>
                    <input type="tel" id="phone" name="phone" required placeholder="+998XXXXXXXXX" pattern="\+998[0-9]{9}">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label for="service_type">Xizmat turi</label>
                    <select id="service_type" name="service_type" required>
                        <option value="">Tanlang...</option>
                        <?php foreach ($services as $service): ?>
                        <option value="<?php echo $service['id']; ?>"><?php echo e($service['title']); ?></option>
                        <?php endforeach; ?>
                        <option value="other">Boshqa</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label for="message">Xabar</label>
                    <textarea id="message" name="message" rows="4" required placeholder="Loyihangiz haqida qisqacha..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Yuborish</button>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div>
                    <strong style="font-size: 1.25rem;">WebHub</strong>
                    <p style="color: var(--text-muted); margin-top: 8px;">Professional IT yechimlar</p>
                </div>
                
                <div class="social-links">
                    <?php if (!empty($settings['contact_telegram'])): ?>
                    <a href="<?php echo e($settings['contact_telegram']); ?>" target="_blank" rel="noopener" class="social-link" aria-label="Telegram">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21.198 2.433a2.25 2.25 0 0 0-1.022-.215c-1.693.21-14.587 5.79-14.587 5.79S.92 9.486.562 10.25c-.357.765.263 1.145.263 1.145l3.716 1.205 1.36 2.722s.15.31.48.41c.33.1.54-.155.54-.155l2.14-1.927 4.282 3.142s.54.395 1.115.263c.575-.132.863-.648.863-.648l3.92-15.532s.318-1.117-.943-1.042Z"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($settings['contact_instagram'])): ?>
                    <a href="<?php echo e($settings['contact_instagram']); ?>" target="_blank" rel="noopener" class="social-link" aria-label="Instagram">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="20" height="20" x="2" y="2" rx="5" ry="5"/>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                            <line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($settings['contact_phone'])): ?>
                    <a href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', $settings['contact_phone'])); ?>" class="social-link" aria-label="Telefon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <p style="text-align: center; color: var(--text-muted); margin-top: 24px; font-size: 0.85rem;">
                &copy; <?php echo date('Y'); ?> WebHub.uz. Barcha huquqlar himoyalangan.
            </p>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>
