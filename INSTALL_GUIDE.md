# WebHub.uz - Professional Web Dasturlash Platformasi

![WebHub.uz](https://img.shields.io/badge/version-1.0.0-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![PHP](https://img.shields.io/badge/PHP-8.0+-purple)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange)

## 📋 Tavsif

**WebHub.uz** - O'zbekistonda professional web dasturlash, mobil ilovalar yaratish, UI/UX dizayn, SEO optimallashtirish va raqamli marketing xizmatlarini taqdim etuvchi to'liq funksional platforma.

Platforma quyidagi imkoniyatlarni taqdim etadi:
- 🏢 Korporativ sayt (landing page, xizmatlar, portfolio, blog)
- 👥 Foydalanuvchi kabineti (Google OAuth orqali kirish)
- 🔐 Admin panel (barcha kontentni boshqarish)
- 💬 Chat tizimi (foydalanuvchi-admin muloqoti)
- 📱 Responsive dizayn (mobile-friendly)
- 🌐 Ko'p tillilik (O'zbek, Rus, Ingliz)
- 🎨 Dark/Light tema

## 🚀 Xususiyatlar

### Frontend (Mijozlar uchun)
- ✅ Zamonaviy glassmorphism dizayn
- ✅ Xizmatlar katalogi (narxlar bilan)
- ✅ Portfolio (loyihalar galereyasi)
- ✅ Blog (SEO optimallangan)
- ✅ Ariza yuborish formasi
- ✅ Aloqa ma'lumotlari
- ✅ Light/Dark tema almashtirish
- ✅ Mobil qurilmalarga moslashuvchanlik

### User Kabinet
- ✅ Google OAuth 2.0 orqali kirish
- ✅ Shaxsiy arizalarni ko'rish
- ✅ Ariza holatini kuzatish
- ✅ Admin bilan chat qilish
- ✅ Bildirishnomalar
- ✅ Profilni boshqarish

### Admin Panel
- ✅ Dashboard (statistika)
- ✅ Xizmatlarni boshqarish (CRUD)
- ✅ Portfolioni boshqarish (rasm yuklash bilan)
- ✅ Blog postlar yaratish/tahrirlash
- ✅ Arizalarni ko'rib chiqish (status o'zgartirish)
- ✅ Foydalanuvchilarni boshqarish
- ✅ Chat orqali javob berish
- ✅ Sayt sozlamalari (SEO, kontaktlar, mavzu)
- ✅ Audit log (barcha amallar tarixi)

## 🛠 Texnologik Stack

| Komponent | Texnologiya |
|-----------|-------------|
| Backend | PHP 8.0+ |
| Ma'lumotlar bazasi | MySQL 5.7+ / MariaDB |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Dizayn | Glassmorphism, Responsive |
| Autentifikatsiya | Google OAuth 2.0, Session-based |
| Xavfsizlik | CSRF, XSS himoyasi, Prepared Statements, Brute Force himoyasi |
| Fayl yuklash | Validated upload, MIME type check |

## 📁 Loyiha Strukturasi

```
webhub-uz/
├── admin/                  # Admin panel sahifalari
│   ├── dashboard.php       # Boshqaruv paneli
│   ├── services.php        # Xizmatlar boshqaruvi
│   ├── portfolio.php       # Portfolio boshqaruvi
│   ├── blog.php            # Blog boshqaruvi
│   ├── applications.php    # Arizalar boshqaruvi
│   ├── users.php           # Foydalanuvchilar
│   ├── chat.php            # Chat
│   └── settings.php        # Sozlamalar
├── user/                   # Foydalanuvchi kabineti
│   ├── dashboard.php       # User dashboard
│   ├── profile.php         # Profil
│   ├── chat.php            # Chat bilan admin
│   └── login.php           # Kirish (Google OAuth)
├── api/                    # REST API endpoints
│   ├── services.php
│   ├── portfolio.php
│   ├── blog.php
│   ├── chat-thread.php
│   └── ...
├── includes/               # Core fayllar
│   ├── config.php.dist     # Config shabloni
│   ├── database.php        # DB ulanish
│   ├── functions.php       # Helper funksiyalar
│   └── security.php        # Xavfsizlik
├── assets/                 # Statik fayllar
│   ├── css/main.css        # Asosiy stil
│   ├── js/main.js          # JavaScript
│   └── images/             # Rasmlar
├── database/               # Ma'lumotlar bazasi
│   ├── schema.sql          # To'liq struktura
│   └── install.php         # DB o'rnatish
├── uploads/                # Yuklangan fayllar
│   ├── blog/
│   ├── portfolio/
│   └── chat/
├── index.php               # Bosh sahifa
├── quick-install.php       # Tezkor o'rnatish
├── install.php             # To'liq o'rnatish
├── submit-application.php  # Ariza handler
├── thank-you.php           # Muvaffaqiyat sahifasi
├── 404.php                 # 404 xato
├── 500.php                 # 500 xato
├── privacy.php             # Maxfiylik siyosati
├── terms.php               # Foydalanish shartlari
├── robots.txt              # Robots fayl
└── sitemap.xml             # Sitemap
```

## ⚡ Tezkor O'rnatish

### Talablar
- PHP 8.0 yoki undan yuqori
- MySQL 5.7 yoki undan yuqori / MariaDB 10.3+
- mod_rewrite yoqilgan Apache yoki Nginx
- HTTPS (tavsiya etiladi)

### 1-Qadam: Fayllarni Serverga Yuklash

```bash
# Barcha fayllarni hosting/serverga yuklang
# yoki Git orqali:
git clone https://github.com/username/webhub-uz.git
cd webhub-uz
```

### 2-Qadam: O'rnatish Sehrkorini Ishga Tushirish

Brauzerda quyidagi manzilni oching:
```
https://saytingiz.uz/quick-install.php
```

Formani to'ldiring:
1. **Ma'lumotlar bazasi**: Host, baza nomi, foydalanuvchi, parol
2. **Admin hisobi**: Login, email, parol, ism
3. **Google OAuth** (ixtiyoriy): Client ID, Secret, Redirect URI

"O'rnatishni boshlash" tugmasini bosing.

### 3-Qadam: Xavfsizlik

O'rnatish tugagandan keyin, o'rnatish fayllarini o'chiring:

```bash
rm quick-install.php install.php database/install.php
```

### 4-Qadam: Admin Panelga Kirish

```
https://saytingiz.uz/admin/login.php
```

Default admin ma'lumotlari (agar o'zgartirmagan bo'lsangiz):
- **Login**: `admin`
- **Parol**: `[o'rnatishda kiritgan parolingiz]`

## 🔐 Google OAuth Sozlash

1. [Google Cloud Console](https://console.cloud.google.com/) ga kiring
2. Yangi loyiha yarating
3. "APIs & Services" > "Credentials" ga o'ting
4. "Create Credentials" > "OAuth client ID" ni tanlang
5. Application type: **Web application**
6. Authorized redirect URIs: `https://saytingiz.uz/user/oauth-callback.php`
7. Client ID va Client Secret ni nusxalab, admin panel > Sozlamalar ga qo'shing

## 🎨 Konfiguratsiya

### .htaccess (Apache)

`.htaccess` fayli allaqachon mavjud va quyidagilarni ta'minlaydi:
- Clean URLs (`/page` o'rniga `/page.php`)
- HTTPS majburlash
- Xavfsizlik headerlari
- Fayl yuklash limitlari

### Nginx Konfiguratsiyasi

```nginx
server {
    listen 80;
    server_name saytingiz.uz www.saytingiz.uz;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name saytingiz.uz www.saytingiz.uz;
    
    root /var/www/webhub-uz;
    index index.php;
    
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.ht {
        deny all;
    }
    
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|doc)$ {
        expires 30d;
    }
}
```

## 📊 Ma'lumotlar Bazasi Jadvallari

| Jadval | Tavsif |
|--------|--------|
| `users` | Foydalanuvchilar (Google OAuth) |
| `admins` | Adminlar |
| `services` | Xizmatlar |
| `portfolio` | Portfolio loyihalar |
| `blog_posts` | Blog postlar |
| `applications` | Arizalar |
| `application_history` | Ariza status tarixi |
| `chat_threads` | Chat threadlar |
| `chat_messages` | Chat xabarlar |
| `notifications` | Bildirishnomalar |
| `settings` | Sayt sozlamalari |
| `audit_log` | Audit log |
| `rate_limits` | Rate limiting |

## 🛡 Xavfsizlik

Platforma quyidagi xavfsizlik choralarini ta'minlaydi:

- ✅ **SQL Injection himoyasi**: PDO Prepared Statements
- ✅ **XSS himoyasi**: `htmlspecialchars()` va `e()` funksiyasi
- ✅ **CSRF himoyasi**: Token-based verification
- ✅ **Brute Force himoyasi**: Login urinishlarini cheklash
- ✅ **Rate Limiting**: API chaqiruvlarini cheklash
- ✅ **Secure Sessions**: HttpOnly, Secure cookies
- ✅ **Password Hashing**: Argon2id algoritmi
- ✅ **File Upload Validation**: MIME type va extension tekshiruvi
- ✅ **Audit Logging**: Barcha muhim amallar qayd etiladi

## 🌐 API Endpoints

Platforma RESTful API taqdim etadi:

| Endpoint | Method | Tavsif |
|----------|--------|--------|
| `/api/services.php` | GET | Xizmatlar ro'yxati |
| `/api/portfolio.php` | GET | Portfolio ro'yxati |
| `/api/blog.php` | GET | Blog postlar |
| `/api/user-profile.php` | GET | User profili |
| `/api/user-applications.php` | GET | User arizalari |
| `/api/chat-thread.php` | GET/POST | Chat threadlar |
| `/api/notifications.php` | GET | Bildirishnomalar |

## 🎯 Keyingi Qadamlar

O'rnatishdan keyin:

1. ✅ Admin panelga kirib, parolni o'zgartiring
2. ✅ Sayt sozlamalarini o'z ehtiyojlaringizga moslang
3. ✅ Xizmatlarni tahrirlang/yangilarini qo'shing
4. ✅ Portfolio loyihalarini yuklang
5. ✅ Blog postlar yozing
6. ✅ Google Analytics va Search Console ulang
7. ✅ SSL sertifikat o'rnating
8. ✅ Backup strategiyasini yarating

## 📝 Litsenziya

MIT License - Barcha huquqlar himoyalangan.

## 👨‍💻 Yaratuvchi

**WebHub.uz Team**

- Website: [webhub.uz](https://webhub.uz)
- Email: info@webhub.uz
- Telegram: @webhub_uz

## 🤝 Qo'llab-quvvatlash

Savollar yoki muammolar bo'lsa:
- 📧 Email: support@webhub.uz
- 💬 Telegram: @webhub_support
- 📚 Hujjatlar: [wiki.webhub.uz](https://wiki.webhub.uz)

---

**© 2024 WebHub.uz. Barcha huquqlar himoyalangan.**
