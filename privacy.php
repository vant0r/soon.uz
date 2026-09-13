<?php
/**
 * Privacy Policy Page (Maxfiylik Siyosati)
 * WebHub.uz - Professional IT Services
 */

require_once __DIR__ . '/includes/functions.php';
startSecureSession();
$pdo = getDbConnection();
$settings = getSiteSettings($pdo);
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maxfiylik Siyosati | WebHub.uz</title>
    <meta name="description" content="WebHub.uz maxfiylik siyosati - shaxsiy ma'lumotlarni qayta ishlash">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="policy-page">
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <main class="policy-content">
        <div class="container">
            <article class="glass-card policy-article">
                <h1>Maxfiylik Siyosati</h1>
                <p class="last-updated">Oxirgi yangilanish: <?= date('d.m.Y') ?></p>
                
                <section>
                    <h2>1. Umumiy qoidalar</h2>
                    <p>WebHub.uz (keyingi o'rinlarda — Sayt) foydalanuvchilarning shaxsiy ma'lumotlarini himoya qilishga jiddiy yondashadi. Ushbu Maxfiylik siyosati (keyingi o'rinlarda — Siyosat) Saytdan foydalanish jarayonida to'planadigan ma'lumotlarni qanday qayta ishlashni tushuntiradi.</p>
                </section>
                
                <section>
                    <h2>2. To'planadigan ma'lumotlar</h2>
                    <p>Biz quyidagi ma'lumotlarni to'plashimiz mumkin:</p>
                    <ul>
                        <li><strong>Shaxsiy ma'lumotlar:</strong> Ism, familiya, elektron pochta, telefon raqami</li>
                        <li><strong>Google hisobi ma'lumotlari:</strong> Google OAuth orqali kirganingizda — ism, elektron pochta, profil rasmi</li>
                        <li><strong>Texnik ma'lumotlar:</strong> IP adres, brauzer turi, qurilma ma'lumotlari</li>
                        <li><strong>Xabarlar va arizalar:</strong> Bizga yuborgan xabarlaringiz va buyurtmalaringiz mazmuni</li>
                    </ul>
                </section>
                
                <section>
                    <h2>3. Ma'lumotlardan foydalanish maqsadlari</h2>
                    <p>Sizning ma'lumotlaringiz quyidagi maqsadlarda ishlatiladi:</p>
                    <ul>
                        <li>Xizmatlar ko'rsatish va buyurtmalarni bajarish</li>
                        <li>Siz bilan bog'lanish va savollarga javob berish</li>
                        <li>Xizmat sifatini yaxshilash</li>
                        <li>Xavfsizlikni ta'minlash va firibgarlikni oldini olish</li>
                    </ul>
                </section>
                
                <section>
                    <h2>4. Ma'lumotlarni saqlash</h2>
                    <p>Shaxsiy ma'lumotlar faqat zarurat davomida va O'zbekiston Respublikasining "Shaxsiy ma'lumotlar to'g'risida"gi Qonuniga muvofiq saqlanadi. Siz har qachon ma'lumotlaringizni o'chirishni so'rashingiz mumkin.</p>
                </section>
                
                <section>
                    <h2>5. Ma'lumotlarni ulashish</h2>
                    <p>Biz sizning shaxsiy ma'lumotlaringizni uchinchi shaxslarga sotmaymiz, ijaraga bermaymiz yoki boshqa shaklda bermaymiz. Istisno holatlar:</p>
                    <ul>
                        <li>Sizning roziligingiz bilan</li>
                        <li>Qonun talabiga ko'ra</li>
                        <li>Xizmat ko'rsatish uchun zarur bo'lgan ishonchli hamkorlarga (masalan, hosting provayderi)</li>
                    </ul>
                </section>
                
                <section>
                    <h2>6. Xavfsizlik choralari</h2>
                    <p>Biz ma'lumotlaringizni himoya qilish uchun zamonaviy texnologiyalardan foydalanamiz:</p>
                    <ul>
                        <li>SSL/TLS shifrlash</li>
                        <li>Xavfsiz serverlar</li>
                        <li>Muntazam xavfsizlik tekshirovlari</li>
                        <li>Faqat vakolatli shaxslar kirishi mumkin</li>
                    </ul>
                </section>
                
                <section>
                    <h2>7. Cookie fayllar</h2>
                    <p>Sayt cookie fayllaridan foydalanadi:</p>
                    <ul>
                        <li>Sessiyani boshqarish (tizimga kirish)</li>
                        <li>Tema tanlovini saqlash (kunduzi/tun rejimi)</li>
                        <li>Xavfsizlik (CSRF tokenlar)</li>
                    </ul>
                    <p>Siz brauzer sozlamalari orqali cookie fayllarni bloklashingiz mumkin, ammo bu saytning ba'zi funksiyalarini cheklashi mumkin.</p>
                </section>
                
                <section>
                    <h2>8. Foydalanuvchi huquqlari</h2>
                    <p>Siz quyidagi huquqlarga egasiz:</p>
                    <ul>
                        <li>Shaxsiy ma'lumotlaringiz bilan tanishish</li>
                        <li>Noto'g'ri ma'lumotlarni tuzatish</li>
                        <li>Ma'lumotlaringizni o'chirish</li>
                        <li>Ma'lumotlarni qayta ishlashga rozilikni bekor qilish</li>
                    </ul>
                </section>
                
                <section>
                    <h2>9. Aloqa</h2>
                    <p>Maxfiylik siyosati bo'yicha savollaringiz bo'lsa, biz bilan bog'laning:</p>
                    <ul>
                        <li>Email: <?= e(getSetting($pdo, 'contact_email', 'info@webhub.uz')) ?></li>
                        <li>Telefon: <?= e(formatPhone(getSetting($pdo, 'contact_phone', '+998901234567'))) ?></li>
                        <li>Telegram: <?= e(getSetting($pdo, 'contact_telegram', '@webhub_uz')) ?></li>
                    </ul>
                </section>
                
                <section>
                    <h2>10. O'zgarishlar</h2>
                    <p>Biz ushbu Siyosatga o'zgartirishlar kiritish huquqini o'zimizda qoldiramiz. O'zgarishlar Saytda joylashtirilgan kundan kuchga kiradi.</p>
                </section>
                
                <div class="policy-actions">
                    <a href="/" class="btn btn-primary">Bosh sahifaga qaytish</a>
                    <a href="/terms.php" class="btn btn-outline">Foydalanish shartlari</a>
                </div>
            </article>
        </div>
    </main>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <style>
        .policy-page {
            min-height: 100vh;
        }
        
        .policy-content {
            padding: 4rem 1rem;
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%);
        }
        
        .policy-article {
            max-width: 800px;
            margin: 0 auto;
            padding: 3rem;
            border-radius: 24px;
        }
        
        .policy-article h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        .last-updated {
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }
        
        .policy-article section {
            margin-bottom: 2rem;
        }
        
        .policy-article h2 {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .policy-article p {
            line-height: 1.8;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .policy-article ul {
            list-style: none;
            padding-left: 0;
        }
        
        .policy-article li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
            color: var(--text-secondary);
            line-height: 1.7;
        }
        
        .policy-article li::before {
            content: "•";
            position: absolute;
            left: 0;
            color: var(--primary);
            font-weight: bold;
        }
        
        .policy-actions {
            display: flex;
            gap: 1rem;
            margin-top: 3rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .policy-article {
                padding: 2rem 1.5rem;
            }
            
            .policy-article h1 {
                font-size: 2rem;
            }
            
            .policy-article h2 {
                font-size: 1.3rem;
            }
        }
    </style>
</body>
</html>
