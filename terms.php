<?php
/**
 * Terms of Use Page (Foydalanish Shartlari)
 * WebHub.uz - Professional IT Services
 */

require_once __DIR__ . '/includes/functions.php';
startSecureSession();
$pdo = getDbConnection();
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foydalanish Shartlari | WebHub.uz</title>
    <meta name="description" content="WebHub.uz saytidan foydalanish shartlari va qoidalari">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="policy-page">
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <main class="policy-content">
        <div class="container">
            <article class="glass-card policy-article">
                <h1>Foydalanish Shartlari</h1>
                <p class="last-updated">Oxirgi yangilanish: <?= date('d.m.Y') ?></p>
                
                <section>
                    <h2>1. Umumiy qoidalar</h2>
                    <p>Ushbu Foydalanish shartlari (keyingi o'rinlarda — Shartlar) WebHub.uz sayti (keyingi o'rinlarda — Sayt) xizmatlaridan foydalanish tartibini belgilaydi. Saytdan foydalanish orqali siz ushbu Shartlarga to'liq rozilik bildirasiz.</p>
                </section>
                
                <section>
                    <h2>2. Xizmatlar tavsifi</h2>
                    <p>WebHub.uz quyidagi IT xizmatlarini taqdim etadi:</p>
                    <ul>
                        <li>Veb-saytlar ishlab chiqish</li>
                        <li>Telegram botlar yaratish</li>
                        <li>Sun'iy intellekt yechimlari</li>
                        <li>Mobil ilovalar ishlab chiqish</li>
                        <li>Boshqa raqamli xizmatlar</li>
                    </ul>
                </section>
                
                <section>
                    <h2>3. Ro'yxatdan o'tish</h2>
                    <p>Saytning ba'zi funksiyalaridan foydalanish uchun Google OAuth orqali ro'yxatdan o'tish talab qilinishi mumkin. Siz:</p>
                    <ul>
                        <li>To'g'ri va dolzarb ma'lumotlarni taqdim etishingiz kerak</li>
                        <li>Hisobingiz maxfiyligini saqlashingiz kerak</li>
                        <li>Hisobingiz orqali amalga oshiriladigan barcha harakatlar uchun mas'ul bo'lasiz</li>
                    </ul>
                </section>
                
                <section>
                    <h2>4. Foydalanuvchi majburiyatlari</h2>
                    <p>Saytdan foydalanish jarayonida siz quyidagilarni bajarmaslikka kelishasiz:</p>
                    <ul>
                        <li>Qonunga zid maqsadlarda foydalanish</li>
                        <li>Boshqa foydalanuvchilarni haqorat qilish yoki kamsitish</li>
                        <li>Spam yoki reklama xabarlari yuborish</li>
                        <li>Sayt xavfsizligiga putur yetkazish</li>
                        <li>Boshqa shaxslarning ma'lumotlarini ruxsatsiz tarqatish</li>
                    </ul>
                </section>
                
                <section>
                    <h2>5. Buyurtma berish</h2>
                    <p>Xizmatlardan buyurtma berish orqali siz:</p>
                    <ul>
                        <li>Tanlagan xizmatning narxi va shartlarini qabul qilasiz</li>
                        <li>To'lovni belgilangan muddatda amalga oshirishga rozilik bildirasiz</li>
                        <li>Buyurtma bekor qilish shartlari bilan tanishgan bo'lasiz</li>
                    </ul>
                </section>
                
                <section>
                    <h2>6. Intellektual mulk</h2>
                    <p>Saytdagi barcha kontent (logotip, dizayn, matnlar, kod) WebHub.uz mulki hisoblanadi va mualliflik huquqi bilan himoyalangan. Ruxsatsiz foydalanish taqiqlanadi.</p>
                </section>
                
                <section>
                    <h2>7. Javobgarlikni cheklash</h2>
                    <p>WebHub.uz quyidagi holatlarda javobgar emas:</p>
                    <ul>
                        <li>Foydalanuvchi tomonidan noto'g'ri ma'lumot taqdim etilganda</li>
                        <li>Texnik nosozliklar natijasida vaqtincha ishlamay qolganda</li>
                        <li>Uchinchi shaxslar harakatlari natijasida ma'lumotlar oshkor bo'lganda (agar bizning aybimiz bo'lmasa)</li>
                        <li>Kuchli vaziyatlar (force majeure) sababli majburiyatlar bajarilmaganda</li>
                    </ul>
                </section>
                
                <section>
                    <h2>8. Xizmat ko'rsatish kafolatlari</h2>
                    <p>WebHub.uz o'z navbatida quyidagilarni kafolatlaydi:</p>
                    <ul>
                        <li>Xizmatlarni professional darajada bajarish</li>
                        <li>Foydalanuvchi ma'lumotlarini maxfiy saqlash</li>
                        <li>Belgilangan muddatlarda buyurtmalarni bajarish</li>
                        <li>Texnik qo'llab-quvvatlash ko'rsatish</li>
                    </ul>
                </section>
                
                <section>
                    <h2>9. Shartlarni o'zgartirish</h2>
                    <p>Administratsiya ushbu Shartlarga o'zgartirishlar kiritish huquqini o'zida qoldiradi. O'zgarishlar Saytda joylashtirilgan kundan kuchga kiradi. Saytdan davomli foydalanish yangi Shartlarni qabul qilish hisoblanadi.</p>
                </section>
                
                <section>
                    <h2>10. Munozaralarni hal qilish</h2>
                    <p>Bahsli vaziyatlar kelib chiqqanda tomonlar muzokara yo'li bilan hal qilishga harakat qiladilar. Muzokara natijasiga erishilmasa, bahs O'zbekiston Respublikasi qonunchiligiga muvofiq hal qilinadi.</p>
                </section>
                
                <section>
                    <h2>11. Aloqa ma'lumotlari</h2>
                    <p>Savollar yoki takliflar uchun:</p>
                    <ul>
                        <li>Email: <?= e(getSetting($pdo, 'contact_email', 'info@webhub.uz')) ?></li>
                        <li>Telefon: <?= e(formatPhone(getSetting($pdo, 'contact_phone', '+998901234567'))) ?></li>
                        <li>Telegram: <?= e(getSetting($pdo, 'contact_telegram', '@webhub_uz')) ?></li>
                        <li>Manzil: Farg'ona, O'zbekiston</li>
                    </ul>
                </section>
                
                <div class="policy-actions">
                    <a href="/" class="btn btn-primary">Bosh sahifaga qaytish</a>
                    <a href="/privacy.php" class="btn btn-outline">Maxfiylik siyosati</a>
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
