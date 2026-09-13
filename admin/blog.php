<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$admin = getCurrentAdmin();
$message = '';
$messageType = '';
$uploadDir = __DIR__ . '/../uploads/blog';
$uploadUrl = 'uploads/blog/';
$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

function blogSlug(string $value): string
{
    $value = trim($value);
    $value = function_exists('iconv') ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value) : $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim($value, '-') ?: 'maqola-' . bin2hex(random_bytes(4));
}

function blogImageUpload(array $file, string $dir, string $url, array $types, int $maxSize): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Rasmni yuklashda xatolik yuz berdi');
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) throw new RuntimeException('Rasm hajmi ruxsat etilgan chegaradan oshdi');
    $mime = validateMimeType($file['tmp_name'], array_keys($types));
    if (!$mime || !isset($types[$mime]) || !@getimagesize($file['tmp_name'])) throw new RuntimeException('Rasm formati noto‘g‘ri');
    $name = bin2hex(random_bytes(24)) . '.' . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Rasmni saqlab bo‘lmadi');
    return rtrim($url, '/') . '/' . $name;
}

function deleteBlogImage(?string $path): void
{
    if (!$path || strpos($path, 'uploads/blog/') !== 0) return;
    $base = realpath(__DIR__ . '/../uploads/blog');
    $file = realpath(__DIR__ . '/../' . ltrim($path, '/'));
    if ($base && $file && strpos($file, $base . DIRECTORY_SEPARATOR) === 0 && is_file($file)) @unlink($file);
}

function cleanBlogContent(string $content): string
{
    return strip_tags($content, '<p><br><strong><b><em><i><u><ul><ol><li><a><blockquote><h2><h3><h4><code><pre>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Xavfsizlik tokeni noto‘g‘ri';
        $messageType = 'error';
    } else {
        try {
            $action = $_POST['action'] ?? '';
            if ($action === 'create' || $action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                $title = trim(sanitizeInput($_POST['title_uz'] ?? ''));
                $slug = trim($_POST['slug'] ?? '');
                $content = cleanBlogContent($_POST['content_uz'] ?? '');
                $excerpt = trim(sanitizeInput($_POST['excerpt_uz'] ?? ''));
                $category = trim(sanitizeInput($_POST['category'] ?? 'news'));
                $tagsRaw = trim($_POST['tags'] ?? '');
                $status = in_array($_POST['status'] ?? 'draft', ['draft', 'published', 'archived'], true) ? $_POST['status'] : 'draft';
                $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
                $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));
                $metaTitle = trim(sanitizeInput($_POST['meta_title'] ?? ''));
                $metaDescription = trim(sanitizeInput($_POST['meta_description'] ?? ''));
                $metaKeywords = trim(sanitizeInput($_POST['meta_keywords'] ?? ''));
                if ($title === '' || trim(strip_tags($content)) === '') throw new RuntimeException('Sarlavha va matn majburiy');
                $slug = blogSlug($slug !== '' ? $slug : $title);
                $existing = dbFetchOne('SELECT id FROM blog_posts WHERE slug = :slug AND id <> :id', ['slug' => $slug, 'id' => $id]);
                if ($existing) $slug .= '-' . bin2hex(random_bytes(3));
                $tags = null;
                if ($tagsRaw !== '') {
                    $tagItems = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $tagsRaw))));
                    $tags = json_encode($tagItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
                $maxMb = max(1, (int) getSiteSetting('max_upload_image_mb', 10));
                $newImage = blogImageUpload($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE], $uploadDir, $uploadUrl, $allowedTypes, $maxMb * 1024 * 1024);
                $data = [
                    'title_uz' => $title,
                    'slug' => $slug,
                    'content_uz' => $content,
                    'excerpt_uz' => $excerpt !== '' ? $excerpt : null,
                    'category' => $category !== '' ? $category : 'news',
                    'tags' => $tags,
                    'meta_title' => $metaTitle !== '' ? $metaTitle : null,
                    'meta_description' => $metaDescription !== '' ? $metaDescription : null,
                    'meta_keywords' => $metaKeywords !== '' ? $metaKeywords : null,
                    'status' => $status,
                    'is_featured' => $isFeatured,
                    'sort_order' => $sortOrder
                ];
                if ($action === 'create') {
                    $data['author_id'] = (int) $admin['id'];
                    $data['published_at'] = $publishedAt;
                    if (!$newImage) throw new RuntimeException('Muqova rasmi majburiy');
                    $data['featured_image'] = $newImage;
                    $newId = dbInsert('blog_posts', $data);
                    logAdminAction($admin['id'], 'blog_create', 'blog_post', $newId, ['title_uz' => $title]);
                    $message = 'Maqola qo‘shildi';
                } else {
                    $current = dbFetchOne('SELECT * FROM blog_posts WHERE id = :id', ['id' => $id]);
                    if (!$current) throw new RuntimeException('Maqola topilmadi');
                    if ($status === 'published' && empty($current['published_at'])) $data['published_at'] = $publishedAt;
                    if ($status !== 'published') $data['published_at'] = null;
                    if ($newImage) $data['featured_image'] = $newImage;
                    dbUpdate('blog_posts', $data, 'id = :id', ['id' => $id]);
                    if ($newImage) deleteBlogImage($current['featured_image'] ?? null);
                    logAdminAction($admin['id'], 'blog_update', 'blog_post', $id, ['title_uz' => $title]);
                    $message = 'Maqola yangilandi';
                }
                $messageType = 'success';
            } elseif ($action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                $item = dbFetchOne('SELECT * FROM blog_posts WHERE id = :id', ['id' => $id]);
                if (!$item) throw new RuntimeException('Maqola topilmadi');
                dbDelete('blog_posts', 'id = :id', ['id' => $id]);
                deleteBlogImage($item['featured_image'] ?? null);
                logAdminAction($admin['id'], 'blog_delete', 'blog_post', $id, ['title_uz' => $item['title_uz']]);
                $message = 'Maqola o‘chirildi';
                $messageType = 'success';
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

$blogPosts = dbFetchAll('SELECT * FROM blog_posts ORDER BY created_at DESC');
$editItem = isset($_GET['edit']) ? dbFetchOne('SELECT * FROM blog_posts WHERE id = :id', ['id' => (int) $_GET['edit']]) : null;
$csrf = generateCsrfToken();
$formItem = $editItem ?: ['id'=>0,'title_uz'=>'','slug'=>'','content_uz'=>'','excerpt_uz'=>'','category'=>'news','tags'=>null,'meta_title'=>'','meta_description'=>'','meta_keywords'=>'','status'=>'draft','is_featured'=>0,'sort_order'=>0,'featured_image'=>null];
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Blog — SOON Admin</title><link rel="stylesheet" href="../assets/css/main.css">
<style>
body{background:var(--bg-secondary)}.admin-layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}.sidebar{background:var(--bg-primary);border-right:1px solid var(--border-color);padding:24px;position:sticky;top:0;height:100vh;overflow-y:auto}.logo{font-size:1.5rem;font-weight:700;color:var(--primary);margin-bottom:32px;display:block;text-decoration:none}.nav-menu{display:flex;flex-direction:column;gap:8px}.nav-link{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;color:var(--text-secondary);text-decoration:none}.nav-link:hover,.nav-link.active{background:var(--bg-tertiary);color:var(--text-primary)}.nav-link.active{background:rgba(59,130,246,.1);color:var(--primary)}.main-content{padding:32px}.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;gap:16px;flex-wrap:wrap}.card{background:var(--bg-primary);border-radius:16px;padding:24px;margin-bottom:24px}.card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.blog-list{display:grid;gap:16px}.blog-item{display:flex;gap:16px;padding:16px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:14px}.blog-image{width:150px;height:100px;object-fit:cover;border-radius:10px;background:var(--bg-tertiary);flex-shrink:0}.blog-content{flex:1}.blog-title{font-size:1.1rem;font-weight:600;margin-bottom:8px}.blog-meta{font-size:.85rem;color:var(--text-muted);margin-bottom:8px}.status-badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:.8rem}.status-draft{background:#E5E7EB;color:#374151}.status-published{background:#D1FAE5;color:#047857}.status-archived{background:#FEE2E2;color:#B91C1C}.form-group{margin-bottom:16px}.form-label{display:block;margin-bottom:6px;font-weight:500}.form-input,.form-textarea,.form-select{width:100%;box-sizing:border-box;padding:10px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-secondary);color:var(--text-primary);font-size:1rem}.form-textarea{min-height:260px;resize:vertical}.btn-group,.blog-actions{display:flex;gap:8px;margin-top:16px}.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px}.alert-success{background:#D1FAE5;color:#047857}.alert-error{background:#FEE2E2;color:#B91C1C}.preview{max-width:320px;width:100%;border-radius:12px}.hidden{display:none}@media(max-width:1024px){.admin-layout{grid-template-columns:1fr}.sidebar{position:relative;height:auto}.main-content{padding:20px}}@media(max-width:640px){.blog-item{flex-direction:column}.blog-image{width:100%;height:180px}}
</style>
</head>
<body><div class="admin-layout"><aside class="sidebar"><a href="dashboard.php" class="logo">SOON Admin</a><nav class="nav-menu"><a href="dashboard.php" class="nav-link">📊 Boshqaruv</a><a href="services.php" class="nav-link">🛠 Xizmatlar</a><a href="portfolio.php" class="nav-link">📁 Portfolio</a><a href="blog.php" class="nav-link active">📝 Blog</a><a href="applications.php" class="nav-link">📋 Arizalar</a><a href="users.php" class="nav-link">👥 Foydalanuvchilar</a><a href="chat.php" class="nav-link">💬 Chat</a><a href="settings.php" class="nav-link">⚙ Sozlamalar</a><hr style="border:0;border-top:1px solid var(--border-color);margin:8px 0"><a href="../index.php" target="_blank" class="nav-link">🌐 Saytni ko‘rish</a><a href="logout.php" class="nav-link" style="color:var(--error)">🚪 Chiqish</a></nav></aside>
<main class="main-content"><div class="header"><div><h1 style="margin-bottom:4px">Blog</h1><p style="color:var(--text-muted)">Maqolalarni boshqarish</p></div><div style="display:flex;gap:12px"><button data-theme-toggle class="btn btn-secondary">🌓 Mavzu</button><?php if (!$editItem): ?><button class="btn btn-primary" onclick="document.getElementById('createForm').classList.toggle('hidden')">➕ Yangi maqola</button><?php else: ?><a href="blog.php" class="btn btn-secondary">✕ Bekor qilish</a><?php endif; ?></div></div>
<?php if($message): ?><div class="alert alert-<?php echo e($messageType); ?>"><?php echo e($message); ?></div><?php endif; ?>
<div id="<?php echo $editItem?'editForm':'createForm'; ?>" class="card<?php echo $editItem?'':' hidden'; ?>"><div class="card-header"><h3><?php echo $editItem?'Maqolani tahrirlash':'Yangi maqola qo‘shish'; ?></h3></div><form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="action" value="<?php echo $editItem?'update':'create'; ?>"><input type="hidden" name="id" value="<?php echo (int)$formItem['id']; ?>">
<div class="form-group"><label class="form-label">Sarlavha *</label><input class="form-input" name="title_uz" maxlength="500" value="<?php echo e($formItem['title_uz']); ?>" required></div><div class="form-group"><label class="form-label">Slug</label><input class="form-input" name="slug" maxlength="500" value="<?php echo e($formItem['slug']); ?>" placeholder="avtomatik-yaratiladi"></div><div class="form-group"><label class="form-label">Qisqa mazmun</label><textarea class="form-input" name="excerpt_uz" style="min-height:90px"><?php echo e($formItem['excerpt_uz']); ?></textarea></div><div class="form-group"><label class="form-label">Matn *</label><textarea class="form-textarea" name="content_uz" required><?php echo e($formItem['content_uz']); ?></textarea></div><div class="form-group"><label class="form-label">Kategoriya</label><input class="form-input" name="category" maxlength="100" value="<?php echo e($formItem['category']); ?>"></div><div class="form-group"><label class="form-label">Teglar</label><input class="form-input" name="tags" value="<?php echo e(is_string($formItem['tags'])?implode(', ',json_decode($formItem['tags'],true)?:[]):''); ?>" placeholder="IT, texnologiya, dasturlash"></div><div class="form-group"><label class="form-label">Meta sarlavha</label><input class="form-input" name="meta_title" maxlength="255" value="<?php echo e($formItem['meta_title']); ?>"></div><div class="form-group"><label class="form-label">Meta tavsif</label><textarea class="form-input" name="meta_description" style="min-height:90px"><?php echo e($formItem['meta_description']); ?></textarea></div><div class="form-group"><label class="form-label">Meta kalit so‘zlar</label><input class="form-input" name="meta_keywords" maxlength="500" value="<?php echo e($formItem['meta_keywords']); ?>"></div><div class="form-group"><label class="form-label">Holati</label><select class="form-select" name="status"><option value="draft" <?php echo $formItem['status']==='draft'?'selected':''; ?>>Qoralama</option><option value="published" <?php echo $formItem['status']==='published'?'selected':''; ?>>Chop etilgan</option><option value="archived" <?php echo $formItem['status']==='archived'?'selected':''; ?>>Arxiv</option></select></div><div class="form-group"><label class="form-label">Tartib raqami</label><input class="form-input" type="number" min="0" name="sort_order" value="<?php echo (int)$formItem['sort_order']; ?>"></div><div class="form-group"><label><input type="checkbox" name="is_featured" value="1" <?php echo !empty($formItem['is_featured'])?'checked':''; ?>> Tanlangan maqola</label></div><?php if($editItem&&$formItem['featured_image']): ?><div class="form-group"><label class="form-label">Joriy rasm</label><img class="preview" src="../<?php echo e($formItem['featured_image']); ?>" alt=""></div><?php endif; ?><div class="form-group"><label class="form-label">Muqova rasmi <?php echo $editItem?'':'*'; ?></label><input class="form-input" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" <?php echo $editItem?'':'required'; ?>></div><div class="btn-group"><button class="btn btn-primary" type="submit">Saqlash</button><?php if($editItem): ?><a href="blog.php" class="btn btn-secondary">Bekor qilish</a><?php endif; ?></div></form></div>
<div class="card"><div class="card-header"><h3>Barcha maqolalar</h3></div><?php if(!$blogPosts): ?><p style="text-align:center;color:var(--text-muted);padding:40px">Hozircha maqolalar yo‘q</p><?php else: ?><div class="blog-list"><?php foreach($blogPosts as $post): ?><div class="blog-item"><?php if($post['featured_image']): ?><img class="blog-image" src="../<?php echo e($post['featured_image']); ?>" alt="<?php echo e($post['title_uz']); ?>"><?php else: ?><div class="blog-image"></div><?php endif; ?><div class="blog-content"><div class="blog-title"><?php echo e($post['title_uz']); ?></div><div class="blog-meta"><span class="status-badge status-<?php echo e($post['status']); ?>"><?php echo $post['status']==='published'?'Chop etilgan':($post['status']==='archived'?'Arxiv':'Qoralama'); ?></span> · <?php echo e($post['category']); ?> · <?php echo date('d.m.Y',strtotime($post['created_at'])); ?></div><div class="blog-actions"><a href="?edit=<?php echo (int)$post['id']; ?>" class="btn btn-sm btn-secondary">Tahrirlash</a><form method="POST" onsubmit="return confirm('Bu maqolani o‘chirishni tasdiqlaysizmi?')"><input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>"><button class="btn btn-sm btn-danger" type="submit">O‘chirish</button></form></div></div></div><?php endforeach; ?></div><?php endif; ?></div></main></div><script src="../assets/js/main.js"></script></body></html>
