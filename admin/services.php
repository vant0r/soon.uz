<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
requireAdminPermission('services.view');

$admin = getCurrentAdmin();
$message = '';
$error = '';
$canManage = adminHasPermission('services.manage', $admin);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        http_response_code(403);
        exit('403 Forbidden');
    }
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Xavfsizlik tokeni noto\'g\'ri.';
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($action === 'delete' && $id > 0) {
                dbDelete('services', 'id = :id', ['id' => $id]);
                logAdminAction((int)$admin['id'], 'delete_service', 'services', $id);
                $message = 'Xizmat o\'chirildi.';
            } elseif (in_array($action, ['create', 'update'], true)) {
                $titleUz = trim((string)($_POST['title_uz'] ?? ''));
                $titleRu = trim((string)($_POST['title_ru'] ?? ''));
                $titleEn = trim((string)($_POST['title_en'] ?? ''));
                $descUz = trim((string)($_POST['description_uz'] ?? ''));
                $descRu = trim((string)($_POST['description_ru'] ?? ''));
                $descEn = trim((string)($_POST['description_en'] ?? ''));
                if ($titleUz === '' || $descUz === '') throw new InvalidArgumentException('O\'zbekcha nom va tavsif majburiy.');
                $priceFrom = ($_POST['price_from'] ?? '') !== '' ? max(0, (float)$_POST['price_from']) : null;
                $priceTo = ($_POST['price_to'] ?? '') !== '' ? max(0, (float)$_POST['price_to']) : null;
                if ($priceFrom !== null && $priceTo !== null && $priceTo < $priceFrom) throw new InvalidArgumentException('Yuqori narx pastki narxdan kichik bo\'lmasligi kerak.');
                $status = in_array($_POST['status'] ?? 'active', ['active', 'inactive'], true) ? $_POST['status'] : 'active';
                $data = [
                    'title_uz' => $titleUz, 'title_ru' => $titleRu ?: null, 'title_en' => $titleEn ?: null,
                    'description_uz' => $descUz, 'description_ru' => $descRu ?: null, 'description_en' => $descEn ?: null,
                    'icon' => trim((string)($_POST['icon'] ?? 'code')) ?: 'code',
                    'price_from' => $priceFrom, 'price_to' => $priceTo,
                    'duration_days' => ($_POST['duration_days'] ?? '') !== '' ? max(0, (int)$_POST['duration_days']) : null,
                    'is_popular' => isset($_POST['is_popular']) ? 1 : 0,
                    'sort_order' => (int)($_POST['sort_order'] ?? 0), 'status' => $status
                ];
                if ($action === 'create') {
                    $id = dbInsert('services', $data);
                    logAdminAction((int)$admin['id'], 'create_service', 'services', $id);
                    $message = 'Xizmat qo\'shildi.';
                } else {
                    if ($id <= 0) throw new InvalidArgumentException('Xizmat ID noto\'g\'ri.');
                    dbUpdate('services', $data, 'id = :id', ['id' => $id]);
                    logAdminAction((int)$admin['id'], 'update_service', 'services', $id);
                    $message = 'Xizmat yangilandi.';
                }
            }
        } catch (Throwable $e) {
            $error = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Amalni bajarishda xatolik yuz berdi.';
        }
    }
}

$services = dbFetchAll("SELECT * FROM services ORDER BY sort_order ASC, id ASC");
$editing = null;
if (isset($_GET['edit'])) $editing = dbFetchOne('SELECT * FROM services WHERE id = :id', ['id' => (int)$_GET['edit']]);
function field(array $row, string $key, string $default = ''): string { return e((string)($row[$key] ?? $default)); }
?>
<!doctype html>
<html lang="uz"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Xizmatlar — SOON Admin</title><link rel="stylesheet" href="../assets/css/main.css">
<style>
body{background:var(--bg-secondary)}.wrap{max-width:1200px;margin:auto;padding:32px}.top{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}.card{background:var(--bg-primary);border:1px solid var(--border-color);border-radius:18px;padding:24px;margin-top:24px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.full{grid-column:1/-1}label{display:block;margin-bottom:7px;font-weight:600}input,textarea,select{width:100%;box-sizing:border-box;padding:11px 13px;border:1px solid var(--border-color);border-radius:11px;background:var(--bg-secondary);color:var(--text-primary)}textarea{min-height:100px;resize:vertical}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}.btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 15px;border-radius:11px;border:1px solid var(--border-color);text-decoration:none;cursor:pointer;background:var(--bg-primary);color:var(--text-primary)}.primary{background:var(--primary);color:#fff;border-color:var(--primary)}.danger{color:#b91c1c}.alert{padding:12px 15px;border-radius:11px;margin-top:18px}.ok{background:#dcfce7;color:#166534}.bad{background:#fee2e2;color:#991b1b}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:13px 10px;text-align:left;border-bottom:1px solid var(--border-color)}.muted{color:var(--text-muted)}@media(max-width:800px){.grid{grid-template-columns:1fr}.full{grid-column:auto}.wrap{padding:18px}.table{min-width:760px}.scroll{overflow:auto}}
</style></head><body><main class="wrap">
<div class="top"><div><div class="muted">SOON Admin</div><h1 style="margin:.2rem 0">Xizmatlar</h1></div><a class="btn" href="dashboard.php">← Dashboard</a></div>
<?php if($message): ?><div class="alert ok"><?php echo e($message); ?></div><?php endif; ?>
<?php if($error): ?><div class="alert bad"><?php echo e($error); ?></div><?php endif; ?>
<?php if($canManage): ?><div class="card"><h2><?php echo $editing ? 'Xizmatni tahrirlash' : 'Yangi xizmat'; ?></h2>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>"><input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>"><input type="hidden" name="id" value="<?php echo (int)($editing['id'] ?? 0); ?>">
<div class="grid">
<div><label>Nomi (UZ) *</label><input required name="title_uz" value="<?php echo field($editing ?? [],'title_uz'); ?>"></div>
<div><label>Nomi (RU)</label><input name="title_ru" value="<?php echo field($editing ?? [],'title_ru'); ?>"></div>
<div><label>Nomi (EN)</label><input name="title_en" value="<?php echo field($editing ?? [],'title_en'); ?>"></div>
<div class="full"><label>Tavsif (UZ) *</label><textarea required name="description_uz"><?php echo field($editing ?? [],'description_uz'); ?></textarea></div>
<div><label>Tavsif (RU)</label><textarea name="description_ru"><?php echo field($editing ?? [],'description_ru'); ?></textarea></div>
<div><label>Tavsif (EN)</label><textarea name="description_en"><?php echo field($editing ?? [],'description_en'); ?></textarea></div>
<div><label>Icon</label><input name="icon" value="<?php echo field($editing ?? [],'icon','code'); ?>"></div>
<div><label>Narxdan</label><input type="number" min="0" step="0.01" name="price_from" value="<?php echo field($editing ?? [],'price_from'); ?>"></div>
<div><label>Narxgacha</label><input type="number" min="0" step="0.01" name="price_to" value="<?php echo field($editing ?? [],'price_to'); ?>"></div>
<div><label>Muddat (kun)</label><input type="number" min="0" name="duration_days" value="<?php echo field($editing ?? [],'duration_days'); ?>"></div>
<div><label>Tartib</label><input type="number" name="sort_order" value="<?php echo field($editing ?? [],'sort_order','0'); ?>"></div>
<div><label>Status</label><select name="status"><option value="active" <?php echo (($editing['status'] ?? 'active')==='active')?'selected':''; ?>>Faol</option><option value="inactive" <?php echo (($editing['status'] ?? '')==='inactive')?'selected':''; ?>>Nofaol</option></select></div>
<div><label><input style="width:auto" type="checkbox" name="is_popular" <?php echo !empty($editing['is_popular'])?'checked':''; ?>> Mashhur xizmat</label></div>
</div><div class="actions"><button class="btn primary" type="submit"><?php echo $editing?'Saqlash':'Qo\'shish'; ?></button><?php if($editing): ?><a class="btn" href="services.php">Bekor qilish</a><?php endif; ?></div></form></div><?php endif; ?>
<div class="card"><h2>Mavjud xizmatlar</h2><div class="scroll"><table class="table"><thead><tr><th>#</th><th>Nomi</th><th>Narx</th><th>Status</th><?php if($canManage): ?><th>Amal</th><?php endif; ?></tr></thead><tbody>
<?php foreach($services as $s): ?><tr><td><?php echo (int)$s['id']; ?></td><td><strong><?php echo e($s['title_uz']); ?></strong><div class="muted"><?php echo e(mb_substr($s['description_uz'] ?? '',0,90)); ?></div></td><td><?php echo $s['price_from'] !== null ? number_format((float)$s['price_from'],0,',',' ').' so\'m' : 'Kelishiladi'; ?></td><td><?php echo e($s['status']); ?></td><?php if($canManage): ?><td><div class="actions"><a class="btn" href="?edit=<?php echo (int)$s['id']; ?>">Tahrirlash</a><form method="post" onsubmit="return confirm('O\'chirishni tasdiqlaysizmi?')"><input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>"><button class="btn danger" type="submit">O\'chirish</button></form></div></td><?php endif; ?></tr><?php endforeach; ?>
<?php if(!$services): ?><tr><td colspan="5" class="muted">Hozircha xizmatlar yo\'q.</td></tr><?php endif; ?></tbody></table></div></div></main></body></html>
