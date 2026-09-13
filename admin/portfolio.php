<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$admin = getCurrentAdmin();
$message = '';
$messageType = '';
$uploadDir = __DIR__ . '/../uploads/portfolio';
$uploadUrl = 'uploads/portfolio/';
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif'
];

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

function portfolioImageUpload(array $file, string $uploadDir, string $uploadUrl, array $allowedTypes, int $maxSize): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Rasmni yuklashda xatolik yuz berdi');
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
        throw new RuntimeException('Rasm hajmi ruxsat etilgan chegaradan oshdi');
    }
    $mime = validateMimeType($file['tmp_name'], array_keys($allowedTypes));
    if (!$mime || !isset($allowedTypes[$mime])) {
        throw new RuntimeException('Faqat JPG, PNG, WEBP yoki GIF formatidagi rasmlar qabul qilinadi');
    }
    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo || ($imageInfo[0] ?? 0) < 1 || ($imageInfo[1] ?? 0) < 1) {
        throw new RuntimeException('Rasm fayli yaroqsiz');
    }
    $filename = bin2hex(random_bytes(24)) . '.' . $allowedTypes[$mime];
    $destination = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Rasmni saqlab bo‘lmadi');
    }
    return rtrim($uploadUrl, '/') . '/' . $filename;
}

function deletePortfolioImage(?string $path): void
{
    if (!$path || strpos($path, 'uploads/portfolio/') !== 0) {
        return;
    }
    $base = realpath(__DIR__ . '/../uploads/portfolio');
    $file = realpath(__DIR__ . '/../' . ltrim($path, '/'));
    if ($base && $file && strpos($file, $base . DIRECTORY_SEPARATOR) === 0 && is_file($file)) {
        @unlink($file);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Xavfsizlik tokeni noto‘g‘ri';
        $messageType = 'error';
    } else {
        try {
            if ($action === 'create' || $action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                $title = trim(sanitizeInput($_POST['title_uz'] ?? ''));
                $description = trim(sanitizeInput($_POST['description_uz'] ?? ''));
                $clientName = trim(sanitizeInput($_POST['client_name'] ?? ''));
                $projectUrl = trim($_POST['project_url'] ?? '');
                $githubUrl = trim($_POST['github_url'] ?? '');
                $category = trim(sanitizeInput($_POST['category'] ?? 'website'));
                $technologiesRaw = trim($_POST['technologies'] ?? '');
                $completedDate = trim($_POST['completed_date'] ?? '');
                $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));
                $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
                $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

                if ($title === '') {
                    throw new RuntimeException('Loyiha nomi majburiy');
                }
                if ($projectUrl !== '' && !filter_var($projectUrl, FILTER_VALIDATE_URL)) {
                    throw new RuntimeException('Veb-sayt havolasi noto‘g‘ri');
                }
                if ($githubUrl !== '' && !filter_var($githubUrl, FILTER_VALIDATE_URL)) {
                    throw new RuntimeException('GitHub havolasi noto‘g‘ri');
                }
                if ($completedDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $completedDate)) {
                    throw new RuntimeException('Tugallangan sana noto‘g‘ri');
                }

                $technologies = null;
                if ($technologiesRaw !== '') {
                    $items = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $technologiesRaw))));
                    $technologies = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                $maxMb = max(1, (int) getSiteSetting('max_upload_image_mb', 10));
                $newImage = portfolioImageUpload($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE], $uploadDir, $uploadUrl, $allowedTypes, $maxMb * 1024 * 1024);

                if ($action === 'create') {
                    if (!$newImage) {
                        throw new RuntimeException('Loyiha rasmi majburiy');
                    }
                    $newId = dbInsert('portfolio', [
                        'title_uz' => $title,
                        'description_uz' => $description !== '' ? $description : null,
                        'image_path' => $newImage,
                        'project_url' => $projectUrl !== '' ? $projectUrl : null,
                        'github_url' => $githubUrl !== '' ? $githubUrl : null,
                        'category' => $category !== '' ? $category : 'website',
                        'technologies' => $technologies,
                        'client_name' => $clientName !== '' ? $clientName : null,
                        'completed_date' => $completedDate !== '' ? $completedDate : null,
                        'is_featured' => $isFeatured,
                        'sort_order' => $sortOrder,
                        'status' => $status
                    ]);
                    logAdminAction($admin['id'], 'portfolio_create', 'portfolio', $newId, ['title_uz' => $title]);
                    $message = 'Portfolio qo‘shildi';
                    $messageType = 'success';
                } else {
                    $current = dbFetchOne('SELECT * FROM portfolio WHERE id = :id', ['id' => $id]);
                    if (!$current) {
                        throw new RuntimeException('Portfolio topilmadi');
                    }
                    $data = [
                        'title_uz' => $title,
                        'description_uz' => $description !== '' ? $description : null,
                        'project_url' => $projectUrl !== '' ? $projectUrl : null,
                        'github_url' => $githubUrl !== '' ? $githubUrl : null,
                        'category' => $category !== '' ? $category : 'website',
                        'technologies' => $technologies,
                        'client_name' => $clientName !== '' ? $clientName : null,
                        'completed_date' => $completedDate !== '' ? $completedDate : null,
                        'is_featured' => $isFeatured,
                        'sort_order' => $sortOrder,
                        'status' => $status
                    ];
                    if ($newImage) {
                        $data['image_path'] = $newImage;
                    }
                    dbUpdate('portfolio', $data, 'id = :id', ['id' => $id]);
                    if ($newImage) {
                        deletePortfolioImage($current['image_path'] ?? null);
                    }
                    logAdminAction($admin['id'], 'portfolio_update', 'portfolio', $id, ['title_uz' => $title]);
                    $message = 'Portfolio yangilandi';
                    $messageType = 'success';
                }
            } elseif ($action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                $item = dbFetchOne('SELECT * FROM portfolio WHERE id = :id', ['id' => $id]);
                if (!$item) {
                    throw new RuntimeException('Portfolio topilmadi');
                }
                dbDelete('portfolio', 'id = :id', ['id' => $id]);
                deletePortfolioImage($item['image_path'] ?? null);
                deletePortfolioImage($item['thumbnail_path'] ?? null);
                logAdminAction($admin['id'], 'portfolio_delete', 'portfolio', $id, ['title_uz' => $item['title_uz']]);
                $message = 'Portfolio o‘chirildi';
                $messageType = 'success';
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

$portfolioItems = dbFetchAll('SELECT * FROM portfolio ORDER BY sort_order ASC, created_at DESC');
$editItem = null;
if (isset($_GET['edit'])) {
    $editItem = dbFetchOne('SELECT * FROM portfolio WHERE id = :id', ['id' => (int) $_GET['edit']]);
}
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Portfolio — SOON Admin</title>
<link rel="stylesheet" href="../assets/css/main.css">
<style>
body{background:var(--bg-secondary)}
.admin-layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}.sidebar{background:var(--bg-primary);border-right:1px solid var(--border-color);padding:24px;position:sticky;top:0;height:100vh;overflow-y:auto}.logo{font-size:1.5rem;font-weight:700;color:var(--primary);margin-bottom:32px;display:block;text-decoration:none}.nav-menu{display:flex;flex-direction:column;gap:8px}.nav-link{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;color:var(--text-secondary);text-decoration:none;transition:.2s}.nav-link:hover,.nav-link.active{background:var(--bg-tertiary);color:var(--text-primary)}.nav-link.active{background:rgba(59,130,246,.1);color:var(--primary)}.main-content{padding:32px}.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;flex-wrap:wrap;gap:16px}.card{background:var(--bg-primary);border-radius:16px;padding:24px;margin-bottom:24px}.card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.portfolio-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px}.portfolio-item{background:var(--bg-secondary);border-radius:12px;overflow:hidden;border:1px solid var(--border-color)}.portfolio-image{width:100%;height:200px;object-fit:cover;background:var(--bg-tertiary)}.portfolio-body{padding:16px}.portfolio-title{font-size:1.1rem;font-weight:600;margin-bottom:8px}.portfolio-meta{font-size:.85rem;color:var(--text-muted);margin-bottom:12px}.portfolio-actions{display:flex;gap:8px}.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px}.alert-success{background:#D1FAE5;color:#047857}.alert-error{background:#FEE2E2;color:#B91C1C}.form-group{margin-bottom:16px}.form-label{display:block;margin-bottom:6px;font-weight:500}.form-input,.form-textarea,.form-select{width:100%;padding:10px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-secondary);color:var(--text-primary);font-size:1rem;box-sizing:border-box}.form-textarea{min-height:100px;resize:vertical}.btn-group{display:flex;gap:8px;margin-top:16px}.preview{max-width:320px;width:100%;border-radius:12px;margin-top:8px}.hidden{display:none}@media(max-width:1024px){.admin-layout{grid-template-columns:1fr}.sidebar{position:relative;height:auto}.main-content{padding:20px}}
</style>
</head>
<body>
<div class="admin-layout">
<aside class="sidebar">
<a href="dashboard.php" class="logo">SOON Admin</a>
<nav class="nav-menu">
<a href="dashboard.php" class="nav-link">📊 Boshqaruv</a>
<a href="services.php" class="nav-link">🛠 Xizmatlar</a>
<a href="portfolio.php" class="nav-link active">📁 Portfolio</a>
<a href="blog.php" class="nav-link">📝 Blog</a>
<a href="applications.php" class="nav-link">📋 Arizalar</a>
<a href="users.php" class="nav-link">👥 Foydalanuvchilar</a>
<a href="chat.php" class="nav-link">💬 Chat</a>
<a href="settings.php" class="nav-link">⚙ Sozlamalar</a>
<hr style="border:0;border-top:1px solid var(--border-color);margin:8px 0">
<a href="../index.php" target="_blank" class="nav-link">🌐 Saytni ko‘rish</a>
<a href="logout.php" class="nav-link" style="color:var(--error)">🚪 Chiqish</a>
</nav>
</aside>
<main class="main-content">
<div class="header">
<div><h1 style="margin-bottom:4px">Portfolio</h1><p style="color:var(--text-muted)">Loyihalarni boshqarish</p></div>
<div style="display:flex;gap:12px"><button data-theme-toggle class="btn btn-secondary">🌓 Mavzu</button><?php if (!$editItem): ?><button class="btn btn-primary" onclick="document.getElementById('createForm').classList.toggle('hidden')">➕ Yangi portfolio</button><?php else: ?><a href="portfolio.php" class="btn btn-secondary">✕ Bekor qilish</a><?php endif; ?></div>
</div>
<?php if ($message): ?><div class="alert alert-<?php echo e($messageType); ?>"><?php echo e($message); ?></div><?php endif; ?>
<?php $formItem = $editItem ?: ['id'=>0,'title_uz'=>'','description_uz'=>'','client_name'=>'','project_url'=>'','github_url'=>'','category'=>'website','technologies'=>null,'completed_date'=>'','sort_order'=>0,'is_featured'=>0,'status'=>'active','image_path'=>null]; ?>
<div id="<?php echo $editItem ? 'editForm' : 'createForm'; ?>" class="card<?php echo $editItem ? '' : ' hidden'; ?>">
<div class="card-header"><h3><?php echo $editItem ? 'Portfolioni tahrirlash' : 'Yangi portfolio qo‘shish'; ?></h3></div>
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="action" value="<?php echo $editItem ? 'update' : 'create'; ?>"><input type="hidden" name="id" value="<?php echo (int)$formItem['id']; ?>">
<div class="form-group"><label class="form-label">Nomi *</label><input type="text" name="title_uz" class="form-input" maxlength="255" value="<?php echo e($formItem['title_uz']); ?>" required></div>
<div class="form-group"><label class="form-label">Tavsif</label><textarea name="description_uz" class="form-textarea"><?php echo e($formItem['description_uz']); ?></textarea></div>
<div class="form-group"><label class="form-label">Mijoz nomi</label><input type="text" name="client_name" class="form-input" maxlength="255" value="<?php echo e($formItem['client_name']); ?>"></div>
<div class="form-group"><label class="form-label">Kategoriya</label><input type="text" name="category" class="form-input" maxlength="100" value="<?php echo e($formItem['category']); ?>"></div>
<div class="form-group"><label class="form-label">Texnologiyalar</label><input type="text" name="technologies" class="form-input" value="<?php echo e(is_string($formItem['technologies']) ? implode(', ', json_decode($formItem['technologies'], true) ?: []) : ''); ?>" placeholder="PHP, MySQL, JavaScript"></div>
<div class="form-group"><label class="form-label">Veb-sayt havolasi</label><input type="url" name="project_url" class="form-input" maxlength="500" value="<?php echo e($formItem['project_url']); ?>" placeholder="https://..."></div>
<div class="form-group"><label class="form-label">GitHub havolasi</label><input type="url" name="github_url" class="form-input" maxlength="500" value="<?php echo e($formItem['github_url']); ?>" placeholder="https://github.com/..."></div>
<div class="form-group"><label class="form-label">Tugallangan sana</label><input type="date" name="completed_date" class="form-input" value="<?php echo e($formItem['completed_date']); ?>"></div>
<div class="form-group"><label class="form-label">Tartib raqami</label><input type="number" min="0" name="sort_order" class="form-input" value="<?php echo (int)$formItem['sort_order']; ?>"></div>
<div class="form-group"><label class="form-label">Holati</label><select name="status" class="form-select"><option value="active" <?php echo $formItem['status']==='active'?'selected':''; ?>>Faol</option><option value="inactive" <?php echo $formItem['status']==='inactive'?'selected':''; ?>>Nofaol</option></select></div>
<div class="form-group"><label><input type="checkbox" name="is_featured" value="1" <?php echo !empty($formItem['is_featured'])?'checked':''; ?>> Tanlangan loyiha</label></div>
<?php if ($editItem && !empty($formItem['image_path'])): ?><div class="form-group"><label class="form-label">Joriy rasm</label><img class="preview" src="../<?php echo e($formItem['image_path']); ?>" alt="<?php echo e($formItem['title_uz']); ?>"></div><?php endif; ?>
<div class="form-group"><label class="form-label">Rasm <?php echo $editItem ? '' : '*'; ?></label><input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/webp,image/gif" <?php echo $editItem ? '' : 'required'; ?>></div>
<div class="btn-group"><button type="submit" class="btn btn-primary">Saqlash</button><?php if ($editItem): ?><a href="portfolio.php" class="btn btn-secondary">Bekor qilish</a><?php endif; ?></div>
</form>
</div>
<div class="card"><div class="card-header"><h3>Barcha portfoliolar</h3></div>
<?php if (!$portfolioItems): ?><p style="color:var(--text-muted);text-align:center;padding:40px 0">Hozircha portfolio loyihalari yo‘q</p><?php else: ?><div class="portfolio-grid"><?php foreach ($portfolioItems as $item): ?><div class="portfolio-item">
<?php if (!empty($item['image_path'])): ?><img src="../<?php echo e($item['image_path']); ?>" alt="<?php echo e($item['title_uz']); ?>" class="portfolio-image"><?php else: ?><div class="portfolio-image" style="display:flex;align-items:center;justify-content:center;color:var(--text-muted)">Rasm yo‘q</div><?php endif; ?>
<div class="portfolio-body"><div class="portfolio-title"><?php echo e($item['title_uz']); ?></div><?php if ($item['client_name']): ?><div class="portfolio-meta">Mijoz: <?php echo e($item['client_name']); ?></div><?php endif; ?><div class="portfolio-meta"><?php echo e($item['category']); ?> · <?php echo $item['status']==='active'?'Faol':'Nofaol'; ?><?php echo !empty($item['is_featured'])?' · Tanlangan':''; ?></div><div class="portfolio-actions"><a href="?edit=<?php echo (int)$item['id']; ?>" class="btn btn-sm btn-secondary">Tahrirlash</a><form method="POST" onsubmit="return confirm('Bu portfolioni o‘chirishni tasdiqlaysizmi?')"><input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">O‘chirish</button></form></div></div>
</div><?php endforeach; ?></div><?php endif; ?></div>
</main></div>
<script src="../assets/js/main.js"></script>
</body></html>
