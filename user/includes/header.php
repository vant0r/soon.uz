<?php
/**
 * User Panel Header
 */
if (!isset($pageTitle)) {
    $pageTitle = 'Foydalanuvchi Paneli';
}
?>
<header class="user-header glass-card">
    <div class="container header-content">
        <a href="/" class="logo">
            <span class="logo-text">WebHub</span>
        </a>
        
        <nav class="user-nav">
            <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                Asosiy
            </a>
            <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
                Profil
            </a>
            <a href="chat.php" class="<?= basename($_SERVER['PHP_SELF']) === 'chat.php' ? 'active' : '' ?>">
                Chat
            </a>
            <a href="logout.php" class="btn-logout">
                Chiqish
            </a>
        </nav>
        
        <button class="mobile-menu-btn" aria-label="Menyu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</header>
